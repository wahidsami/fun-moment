import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

enum RepeatMode { off, all, one }

class MusicPlayerService extends ChangeNotifier {
  final AudioPlayer _player;
  final http.Client _httpClient;

  List<CatalogTrackModel> _queue = [];
  List<CatalogTrackModel> _originalQueue = [];
  int _currentIndex = -1;

  RepeatMode _repeatMode = RepeatMode.off;
  bool _isShuffle = false;

  bool _isPlaying = false;
  bool _isBuffering = false;
  bool _isLoadingTicket = false;

  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;

  String _playbackMode = 'preview'; // 'preview' or 'full'
  String? _errorMessage;

  String? _currentSessionId;
  String? _sessionToken;
  String? _guestSessionId;

  bool _hasProgressReported = false;
  int _playRequestId = 0;
  bool _isCompleting = false;

  StreamSubscription? _stateSub;
  StreamSubscription? _posSub;
  StreamSubscription? _durSub;
  StreamSubscription? _compSub;

  MusicPlayerService({AudioPlayer? player, http.Client? httpClient})
      : _player = player ?? AudioPlayer(),
        _httpClient = httpClient ?? http.Client() {
    _initPlayer();
    _initGuestSessionId();
  }

  // Getters
  List<CatalogTrackModel> get queue => List.unmodifiable(_queue);
  int get currentIndex => _currentIndex;
  CatalogTrackModel? get currentTrack =>
      (_currentIndex >= 0 && _currentIndex < _queue.length) ? _queue[_currentIndex] : null;

  bool get hasActiveTrack => currentTrack != null;
  RepeatMode get repeatMode => _repeatMode;
  bool get isShuffle => _isShuffle;

  bool get isPlaying => _isPlaying;
  bool get isBuffering => _isBuffering;
  bool get isLoadingTicket => _isLoadingTicket;

  Duration get position => _position;
  Duration get duration => _duration;

  String get playbackMode => _playbackMode;
  String? get errorMessage => _errorMessage;

  String? get currentSessionId => _currentSessionId;
  String? get sessionToken => _sessionToken;

  bool get hasNext {
    if (_queue.isEmpty) return false;
    if (_repeatMode == RepeatMode.one || _repeatMode == RepeatMode.all) return true;
    return _currentIndex < _queue.length - 1;
  }

  bool get hasPrevious {
    if (_queue.isEmpty) return false;
    if (_repeatMode == RepeatMode.all) return true;
    return _currentIndex > 0 || _position.inSeconds > 3;
  }

  void _initPlayer() {
    _stateSub = _player.onPlayerStateChanged.listen((PlayerState state) {
      _isPlaying = state == PlayerState.playing;
      _isBuffering = false;
      notifyListeners();
    });

    _posSub = _player.onPositionChanged.listen((Duration p) {
      _position = p;
      _checkProgressTelemetry();
      notifyListeners();
    });

    _durSub = _player.onDurationChanged.listen((Duration d) {
      _duration = d;
      notifyListeners();
    });

    _compSub = _player.onPlayerComplete.listen((_) {
      _onPlaybackCompleted();
    });
  }

  Future<void> _initGuestSessionId() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      _guestSessionId = prefs.getString('music_guest_session_id');
      if (_guestSessionId == null) {
        _guestSessionId = _generateUuid();
        await prefs.setString('music_guest_session_id', _guestSessionId!);
      }
    } catch (_) {
      _guestSessionId ??= _generateUuid();
    }
  }

  String _generateUuid() {
    final random = Random.secure();
    final values = List<int>.generate(16, (i) => random.nextInt(256));
    values[6] = (values[6] & 0x0f) | 0x40; // Version 4
    values[8] = (values[8] & 0x3f) | 0x80; // Variant 10
    final hex = values.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  Future<String?> _getToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString('token');
    } catch (_) {
      return null;
    }
  }

  /// Load a full list of tracks into the queue and start playing at the designated index.
  Future<void> loadQueue(
    List<CatalogTrackModel> tracks, {
    int startIndex = 0,
    bool autoPlay = true,
  }) async {
    if (tracks.isEmpty) {
      await clearQueue();
      return;
    }

    _originalQueue = List<CatalogTrackModel>.from(tracks);
    final clampedIndex = startIndex.clamp(0, tracks.length - 1);
    final targetTrack = tracks[clampedIndex];

    if (_isShuffle) {
      final remaining = List<CatalogTrackModel>.from(tracks)..removeAt(clampedIndex);
      remaining.shuffle(Random());
      _queue = [targetTrack, ...remaining];
      _currentIndex = 0;
    } else {
      _queue = List<CatalogTrackModel>.from(tracks);
      _currentIndex = clampedIndex;
    }

    if (autoPlay) {
      await _playCurrentQueueTrack();
    } else {
      _position = Duration.zero;
      _duration = Duration(seconds: targetTrack.durationSeconds);
      notifyListeners();
    }
  }

  /// Request stream ticket and start playback for a single track (backward-compatible).
  Future<void> playTrack(CatalogTrackModel track) async {
    // If clicking same track that's currently paused, resume
    if (currentTrack?.id == track.id && !_isPlaying && _position > Duration.zero) {
      await resume();
      return;
    }

    // If track is already in the queue, jump to it
    final existingIndex = _queue.indexWhere((t) => t.id == track.id);
    if (existingIndex >= 0) {
      _currentIndex = existingIndex;
      await _playCurrentQueueTrack();
      return;
    }

    // Otherwise, load as a single-track queue
    await loadQueue([track], startIndex: 0, autoPlay: true);
  }

  /// Jump to a specific track index in the active queue.
  Future<void> playAtIndex(int index) async {
    if (index < 0 || index >= _queue.length) return;
    _currentIndex = index;
    await _playCurrentQueueTrack();
  }

  /// Append track to end of queue.
  void addToQueue(CatalogTrackModel track) {
    _originalQueue.add(track);
    _queue.add(track);
    if (_currentIndex == -1) {
      _currentIndex = 0;
      _playCurrentQueueTrack();
    } else {
      notifyListeners();
    }
  }

  /// Insert track to play immediately after the currently playing track.
  void insertNext(CatalogTrackModel track) {
    if (_queue.isEmpty || _currentIndex == -1) {
      loadQueue([track], startIndex: 0, autoPlay: true);
      return;
    }

    final insertIdx = _currentIndex + 1;
    _queue.insert(insertIdx, track);

    final currentId = currentTrack?.id;
    final origIdx = _originalQueue.indexWhere((t) => t.id == currentId);
    if (origIdx >= 0) {
      _originalQueue.insert(origIdx + 1, track);
    } else {
      _originalQueue.add(track);
    }

    notifyListeners();
  }

  /// Remove track at index from the queue.
  Future<void> removeAt(int index) async {
    if (index < 0 || index >= _queue.length) return;

    final trackToRemove = _queue[index];

    if (_queue.length == 1) {
      await clearQueue();
      return;
    }

    if (index == _currentIndex) {
      _queue.removeAt(index);
      _originalQueue.removeWhere((t) => t.id == trackToRemove.id);

      if (_currentIndex >= _queue.length) {
        _currentIndex = _queue.length - 1;
      }
      await _playCurrentQueueTrack();
    } else {
      _queue.removeAt(index);
      _originalQueue.removeWhere((t) => t.id == trackToRemove.id);

      if (index < _currentIndex) {
        _currentIndex--;
      }
      notifyListeners();
    }
  }

  /// Reorder queue items (used with ReorderableListView).
  void reorderQueue(int oldIndex, int newIndex) {
    if (oldIndex < 0 || oldIndex >= _queue.length) return;
    if (newIndex < 0 || newIndex > _queue.length) return;

    if (newIndex > oldIndex) {
      newIndex -= 1;
    }

    final current = currentTrack;
    final item = _queue.removeAt(oldIndex);
    _queue.insert(newIndex, item);

    if (current != null) {
      final updatedIdx = _queue.indexOf(current);
      if (updatedIdx >= 0) {
        _currentIndex = updatedIdx;
      }
    }

    notifyListeners();
  }

  /// Safely clear queue and stop playback.
  Future<void> clearQueue() async {
    await stop();
    _queue.clear();
    _originalQueue.clear();
    _currentIndex = -1;
    _currentSessionId = null;
    _sessionToken = null;
    _errorMessage = null;
    notifyListeners();
  }

  /// Toggle shuffle on or off. Preserves original track order when disabled.
  void toggleShuffle() {
    if (_queue.isEmpty) {
      _isShuffle = !_isShuffle;
      notifyListeners();
      return;
    }

    final current = currentTrack;
    if (!_isShuffle) {
      _isShuffle = true;
      _originalQueue = List<CatalogTrackModel>.from(_queue);

      final remaining = List<CatalogTrackModel>.from(_queue);
      if (current != null) {
        remaining.removeWhere((t) => t.id == current.id);
      }
      remaining.shuffle(Random());
      if (current != null) {
        _queue = [current, ...remaining];
        _currentIndex = 0;
      } else {
        _queue = remaining;
        _currentIndex = -1;
      }
    } else {
      _isShuffle = false;
      if (_originalQueue.isNotEmpty) {
        _queue = List<CatalogTrackModel>.from(_originalQueue);
        if (current != null) {
          final idx = _queue.indexWhere((t) => t.id == current.id);
          _currentIndex = idx >= 0 ? idx : 0;
        }
      }
    }
    notifyListeners();
  }

  /// Cycle through RepeatMode: off -> all -> one -> off.
  void toggleRepeatMode() {
    switch (_repeatMode) {
      case RepeatMode.off:
        _repeatMode = RepeatMode.all;
        break;
      case RepeatMode.all:
        _repeatMode = RepeatMode.one;
        break;
      case RepeatMode.one:
        _repeatMode = RepeatMode.off;
        break;
    }
    notifyListeners();
  }

  void setRepeatMode(RepeatMode mode) {
    _repeatMode = mode;
    notifyListeners();
  }

  /// Play next track in queue respecting repeat modes.
  Future<void> playNext({bool isAutoAdvance = false}) async {
    if (_queue.isEmpty) return;

    if (isAutoAdvance && _repeatMode == RepeatMode.one) {
      // Repeat one: rewind and replay current track
      await seek(Duration.zero);
      await resume();
      return;
    }

    if (_currentIndex < _queue.length - 1) {
      _currentIndex++;
      await _playCurrentQueueTrack();
    } else if (_repeatMode == RepeatMode.all) {
      _currentIndex = 0;
      await _playCurrentQueueTrack();
    } else {
      // End of queue in repeat off
      if (isAutoAdvance) {
        await stop();
        _position = _duration;
        notifyListeners();
      } else if (_queue.length == 1) {
        await seek(Duration.zero);
      }
    }
  }

  /// Play previous track in queue, or restart track if past 3 seconds.
  Future<void> playPrevious() async {
    if (_queue.isEmpty) return;

    // Standard player behavior: if playing for more than 3 seconds, restart current track
    if (_position.inSeconds > 3) {
      await seek(Duration.zero);
      return;
    }

    if (_currentIndex > 0) {
      _currentIndex--;
      await _playCurrentQueueTrack();
    } else if (_repeatMode == RepeatMode.all && _queue.isNotEmpty) {
      _currentIndex = _queue.length - 1;
      await _playCurrentQueueTrack();
    } else {
      await seek(Duration.zero);
    }
  }

  /// Core private playback executor with sequence token protection against race conditions.
  Future<void> _playCurrentQueueTrack() async {
    if (_currentIndex < 0 || _currentIndex >= _queue.length) return;

    final track = _queue[_currentIndex];
    final thisRequestId = ++_playRequestId;

    await stop();

    _isLoadingTicket = true;
    _errorMessage = null;
    _position = Duration.zero;
    _duration = Duration(seconds: track.durationSeconds);
    _hasProgressReported = false;
    _currentSessionId = _generateUuid();
    notifyListeners();

    try {
      final token = await _getToken();
      final ticketUrl = Uri.parse('$baseApi/music/tracks/${track.id}/stream-ticket');
      final res = await _httpClient.get(ticketUrl, headers: {
        'Accept': 'application/json',
        if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
      });

      // Discard response if a newer request was dispatched while waiting
      if (thisRequestId != _playRequestId) {
        return;
      }

      if (res.statusCode != 200) {
        final body = jsonDecode(res.body);
        _errorMessage = body['message'] ?? 'Streaming authorization denied';
        _isLoadingTicket = false;
        notifyListeners();
        _sendTelemetry('playback_failed', errorMessage: _errorMessage);
        return;
      }

      final body = jsonDecode(res.body);
      final Map<String, dynamic> data = (body is Map<String, dynamic> && body['data'] is Map<String, dynamic>)
          ? body['data'] as Map<String, dynamic>
          : (body is Map<String, dynamic> ? body : <String, dynamic>{});
      final mode = data['mode']?.toString() ?? 'preview';
      final streamUrl = data['stream_url']?.toString();

      if (streamUrl == null || streamUrl.isEmpty) {
        _errorMessage = 'Invalid stream endpoint';
        _isLoadingTicket = false;
        notifyListeners();
        _sendTelemetry('playback_failed', errorMessage: _errorMessage);
        return;
      }

      if (thisRequestId != _playRequestId) {
        return;
      }

      _currentSessionId = data['playback_session_id']?.toString() ?? _currentSessionId;
      _sessionToken = data['session_token']?.toString() ?? data['ticket']?.toString();
      _playbackMode = mode;
      _isLoadingTicket = false;
      _isBuffering = true;
      notifyListeners();

      await _player.play(UrlSource(streamUrl));
      _sendTelemetry('playback_started');
    } catch (e) {
      if (thisRequestId != _playRequestId) return;
      debugPrint('MusicPlayerService.playTrack error: $e');
      _errorMessage = 'Playback error: $e';
      _isLoadingTicket = false;
      _isBuffering = false;
      notifyListeners();
      _sendTelemetry('playback_failed', errorMessage: _errorMessage);
    }
  }

  /// Refresh stream ticket for the active track without creating overlapping playback sessions.
  Future<bool> refreshTicket() async {
    final track = currentTrack;
    if (track == null) return false;

    try {
      final token = await _getToken();
      final ticketUrl = Uri.parse('$baseApi/music/tracks/${track.id}/stream-ticket');
      final res = await _httpClient.get(ticketUrl, headers: {
        'Accept': 'application/json',
        if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final data = body['data'] as Map<String, dynamic>;
        _sessionToken = data['session_token']?.toString() ?? data['ticket']?.toString();
        _playbackMode = data['mode']?.toString() ?? _playbackMode;
        return true;
      }
    } catch (e) {
      debugPrint('MusicPlayerService.refreshTicket error: $e');
    }
    return false;
  }

  Future<void> togglePlayPause() async {
    if (_isPlaying) {
      await pause();
    } else {
      await resume();
    }
  }

  Future<void> pause() async {
    await _player.pause();
    _isPlaying = false;
    notifyListeners();
  }

  Future<void> resume() async {
    if (currentTrack != null) {
      await _player.resume();
      _isPlaying = true;
      notifyListeners();
    }
  }

  Future<void> seek(Duration target) async {
    await _player.seek(target);
    _position = target;
    notifyListeners();
  }

  Future<void> stop() async {
    await _player.stop();
    _isPlaying = false;
    _isBuffering = false;
    _position = Duration.zero;
    notifyListeners();
  }

  void closePlayer() {
    stop();
    clearQueue();
  }

  void _checkProgressTelemetry() {
    if (_hasProgressReported || currentTrack == null || _currentSessionId == null) return;

    final currentSec = _position.inSeconds;
    final totalSec = _duration.inSeconds > 0 ? _duration.inSeconds : currentTrack!.durationSeconds;

    if (currentSec >= 15 || (totalSec > 0 && (currentSec / totalSec) >= 0.5)) {
      _hasProgressReported = true;
      _sendTelemetry('listening_progress');
    }
  }

  void _onPlaybackCompleted() async {
    if (_isCompleting) return;
    _isCompleting = true;

    try {
      _isPlaying = false;
      _position = _duration;
      notifyListeners();

      if (_playbackMode == 'preview') {
        _sendTelemetry('preview_completed');
      } else {
        _sendTelemetry('full_track_completed');
      }

      await playNext(isAutoAdvance: true);
    } finally {
      _isCompleting = false;
    }
  }

  void _sendTelemetry(String eventType, {String? errorMessage}) async {
    if (currentTrack == null || _currentSessionId == null) return;

    try {
      final token = await _getToken();
      final url = Uri.parse('$baseApi/music/analytics/playback-event');
      final payload = {
        'track_id': currentTrack!.id,
        'playback_session_id': _currentSessionId,
        'event_type': eventType,
        'playback_mode': _playbackMode,
        'position_seconds': _position.inSeconds,
        'duration_seconds': _duration.inSeconds > 0 ? _duration.inSeconds : currentTrack!.durationSeconds,
        'idempotency_key': '$_currentSessionId-$eventType-${_position.inSeconds}',
        if (_sessionToken != null) 'session_token': _sessionToken,
        if (_guestSessionId != null) 'guest_session_id': _guestSessionId,
        if (errorMessage != null) 'error_message': errorMessage,
      };

      await _httpClient.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
          if (_sessionToken != null) 'X-Stream-Ticket': _sessionToken!,
        },
        body: jsonEncode(payload),
      );
    } catch (e) {
      debugPrint('MusicPlayerService._sendTelemetry error: $e');
    }
  }

  @override
  void dispose() {
    _stateSub?.cancel();
    _posSub?.cancel();
    _durSub?.cancel();
    _compSub?.cancel();
    _player.dispose();
    super.dispose();
  }
}
