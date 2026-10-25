import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class MusicPlayerService extends ChangeNotifier {
  final AudioPlayer _player = AudioPlayer();

  CatalogTrackModel? _currentTrack;
  CatalogTrackModel? get currentTrack => _currentTrack;

  bool _isPlaying = false;
  bool get isPlaying => _isPlaying;

  bool _isBuffering = false;
  bool get isBuffering => _isBuffering;

  bool _isLoadingTicket = false;
  bool get isLoadingTicket => _isLoadingTicket;

  Duration _position = Duration.zero;
  Duration get position => _position;

  Duration _duration = Duration.zero;
  Duration get duration => _duration;

  String _playbackMode = 'preview'; // 'preview' or 'full'
  String get playbackMode => _playbackMode;

  String? _errorMessage;
  String? get errorMessage => _errorMessage;

  String? _currentSessionId;
  String? get currentSessionId => _currentSessionId;

  String? _sessionToken;
  String? get sessionToken => _sessionToken;

  String? _guestSessionId;

  bool _hasProgressReported = false;
  StreamSubscription? _stateSub;
  StreamSubscription? _posSub;
  StreamSubscription? _durSub;
  StreamSubscription? _compSub;

  MusicPlayerService() {
    _initPlayer();
    _initGuestSessionId();
  }

  bool get hasActiveTrack => _currentTrack != null;

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
    final prefs = await SharedPreferences.getInstance();
    _guestSessionId = prefs.getString('music_guest_session_id');
    if (_guestSessionId == null) {
      _guestSessionId = _generateUuid();
      await prefs.setString('music_guest_session_id', _guestSessionId!);
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
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('token');
  }

  /// Request stream ticket and start playback
  Future<void> playTrack(CatalogTrackModel track) async {
    // If clicking same track that's currently paused, resume
    if (_currentTrack?.id == track.id && !_isPlaying && _position > Duration.zero) {
      await resume();
      return;
    }

    // Stop existing playback first to avoid overlapping sessions
    await stop();

    _currentTrack = track;
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
      final res = await http.get(ticketUrl, headers: {
        'Accept': 'application/json',
        if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
      });

      if (res.statusCode != 200) {
        final body = jsonDecode(res.body);
        _errorMessage = body['message'] ?? 'Streaming authorization denied';
        _isLoadingTicket = false;
        notifyListeners();
        _sendTelemetry('playback_failed', errorMessage: _errorMessage);
        return;
      }

      final body = jsonDecode(res.body);
      final data = body['data'] as Map<String, dynamic>;
      final mode = data['mode']?.toString() ?? 'preview';
      final streamUrl = data['stream_url']?.toString();

      if (streamUrl == null || streamUrl.isEmpty) {
        _errorMessage = 'Invalid stream endpoint';
        _isLoadingTicket = false;
        notifyListeners();
        _sendTelemetry('playback_failed', errorMessage: _errorMessage);
        return;
      }

      _currentSessionId = data['playback_session_id']?.toString() ?? _currentSessionId;
      _sessionToken = data['session_token']?.toString() ?? data['ticket']?.toString();
      _playbackMode = mode;
      _isLoadingTicket = false;
      _isBuffering = true;
      notifyListeners();

      // Play through UrlSource with streaming URL
      await _player.play(UrlSource(streamUrl));

      _sendTelemetry('playback_started');
    } catch (e) {
      debugPrint('MusicPlayerService.playTrack error: $e');
      _errorMessage = 'Playback error: $e';
      _isLoadingTicket = false;
      _isBuffering = false;
      notifyListeners();
      _sendTelemetry('playback_failed', errorMessage: _errorMessage);
    }
  }

  /// Refresh stream ticket for the active track without creating overlapping playback sessions
  Future<bool> refreshTicket() async {
    if (_currentTrack == null) return false;

    try {
      final token = await _getToken();
      final ticketUrl = Uri.parse('$baseApi/music/tracks/${_currentTrack!.id}/stream-ticket');
      final res = await http.get(ticketUrl, headers: {
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
    if (_currentTrack != null) {
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
    _currentTrack = null;
    _currentSessionId = null;
    _sessionToken = null;
    notifyListeners();
  }

  void _checkProgressTelemetry() {
    if (_hasProgressReported || _currentTrack == null || _currentSessionId == null) return;

    // Report progress when position reaches 15s or 50%
    final currentSec = _position.inSeconds;
    final totalSec = _duration.inSeconds > 0 ? _duration.inSeconds : _currentTrack!.durationSeconds;

    if (currentSec >= 15 || (totalSec > 0 && (currentSec / totalSec) >= 0.5)) {
      _hasProgressReported = true;
      _sendTelemetry('listening_progress');
    }
  }

  void _onPlaybackCompleted() {
    _isPlaying = false;
    _position = _duration;
    notifyListeners();

    if (_playbackMode == 'preview') {
      _sendTelemetry('preview_completed');
    } else {
      _sendTelemetry('full_track_completed');
    }
  }

  void _sendTelemetry(String eventType, {String? errorMessage}) async {
    if (_currentTrack == null || _currentSessionId == null) return;

    try {
      final token = await _getToken();
      final url = Uri.parse('$baseApi/music/analytics/playback-event');
      final payload = {
        'track_id': _currentTrack!.id,
        'playback_session_id': _currentSessionId,
        'event_type': eventType,
        'playback_mode': _playbackMode,
        'position_seconds': _position.inSeconds,
        'duration_seconds': _duration.inSeconds > 0 ? _duration.inSeconds : _currentTrack!.durationSeconds,
        'idempotency_key': '$_currentSessionId-$eventType-${_position.inSeconds}',
        if (_sessionToken != null) 'session_token': _sessionToken,
        if (_guestSessionId != null) 'guest_session_id': _guestSessionId,
        if (errorMessage != null) 'error_message': errorMessage,
      };

      await http.post(
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
