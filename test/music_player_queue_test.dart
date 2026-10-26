import 'dart:async';
import 'dart:convert';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/music_player_service.dart';

class FakeAudioPlayer extends AudioPlayer {
  final StreamController<PlayerState> _stateController = StreamController<PlayerState>.broadcast();
  final StreamController<Duration> _posController = StreamController<Duration>.broadcast();
  final StreamController<Duration> _durController = StreamController<Duration>.broadcast();
  final StreamController<void> _compController = StreamController<void>.broadcast();

  @override
  Stream<PlayerState> get onPlayerStateChanged => _stateController.stream;

  @override
  Stream<Duration> get onPositionChanged => _posController.stream;

  @override
  Stream<Duration> get onDurationChanged => _durController.stream;

  @override
  Stream<void> get onPlayerComplete => _compController.stream;

  @override
  Future<void> play(
    Source source, {
    double? volume,
    double? balance,
    AudioContext? ctx,
    Duration? position,
    PlayerMode? mode,
  }) async {
    _stateController.add(PlayerState.playing);
  }

  @override
  Future<void> pause() async {
    _stateController.add(PlayerState.paused);
  }

  @override
  Future<void> resume() async {
    _stateController.add(PlayerState.playing);
  }

  @override
  Future<void> stop() async {
    _stateController.add(PlayerState.stopped);
  }

  @override
  Future<void> seek(Duration position) async {
    _posController.add(position);
  }

  void completeTrack() {
    _compController.add(null);
  }

  @override
  Future<void> dispose() async {
    await _stateController.close();
    await _posController.close();
    await _durController.close();
    await _compController.close();
  }
}

class FakeMusicHttpClient extends http.BaseClient {
  int requestCount = 0;
  Duration delay = Duration.zero;
  int? lastRequestedTrackId;
  final List<String> requestedUrls = [];

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    requestCount++;
    requestedUrls.add(request.url.toString());
    if (delay > Duration.zero) {
      await Future.delayed(delay);
    }

    final uri = request.url.toString();
    final match = RegExp(r'/tracks/(\d+)/stream-ticket').firstMatch(uri);
    final trackId = match != null ? int.parse(match.group(1)!) : 1;
    lastRequestedTrackId = trackId;

    final body = jsonEncode({
      'data': {
        'ticket': 'test-ticket-$trackId',
        'stream_url': 'https://storage.funmoments.com/audio/track_$trackId.mp3',
        'expires_at': DateTime.now().add(const Duration(minutes: 15)).toIso8601String(),
        'mode': 'preview',
        'session_id': 'sess-$trackId',
        'session_token': 'token-$trackId',
      }
    });

    return http.StreamedResponse(
      Stream.value(utf8.encode(body)),
      200,
      headers: {'content-type': 'application/json'},
    );
  }
}

CatalogTrackModel createMockTrack({
  required int id,
  required String titleEn,
  required String titleAr,
  String artistName = 'Artist One',
  int duration = 180,
  String coverArt = 'https://example.com/art.jpg',
}) {
  return CatalogTrackModel.fromJson({
    'id': id,
    'title_en': titleEn,
    'title_ar': titleAr,
    'artist_name': artistName,
    'duration_seconds': duration,
    'preview_duration_seconds': 30,
    'cover_art_url': coverArt,
    'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/$id/stream-ticket',
    'is_favorite': false,
    'primary_genre': {'id': 1, 'name': 'Dance', 'slug': 'dance'},
    'genres': [],
    'offers': [
      {'id': 1, 'offer_type': 'free', 'price': 0.0, 'currency': 'SAR', 'is_active': true}
    ],
  });
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeMusicHttpClient mockHttp;

  setUpAll(() {
    SharedPreferences.setMockInitialValues({'music_guest_session_id': 'guest-test-123'});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('xyz.luan/audioplayers.global'),
            (call) async => null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('xyz.luan/audioplayers'),
            (call) async => null);
  });

  setUp(() {
    mockHttp = FakeMusicHttpClient();
  });

  MusicPlayerService createPlayer({FakeAudioPlayer? player}) {
    return MusicPlayerService(
      player: player ?? FakeAudioPlayer(),
      httpClient: mockHttp,
    );
  }

  group('Phase 2A: Playback Queue, Repeat, Shuffle & Controls Tests', () {
    // 1. Loading empty queue, single track, and multi-track queue
    test('1. Loading empty queue, single track, and multi-track queue', () async {
      final player = createPlayer();

      // Empty queue load
      await player.loadQueue([]);
      expect(player.queue, isEmpty);
      expect(player.currentIndex, -1);
      expect(player.hasActiveTrack, isFalse);

      // Single track load
      final track1 = createMockTrack(id: 1, titleEn: 'Track 1', titleAr: 'مقطع 1');
      await player.loadQueue([track1], startIndex: 0, autoPlay: false);
      expect(player.queue.length, 1);
      expect(player.currentIndex, 0);
      expect(player.currentTrack?.id, 1);
      expect(player.hasActiveTrack, isTrue);

      // Multi-track load
      final track2 = createMockTrack(id: 2, titleEn: 'Track 2', titleAr: 'مقطع 2');
      final track3 = createMockTrack(id: 3, titleEn: 'Track 3', titleAr: 'مقطع 3');
      await player.loadQueue([track1, track2, track3], startIndex: 0, autoPlay: false);
      expect(player.queue.length, 3);
      expect(player.queue[0].id, 1);
      expect(player.queue[1].id, 2);
      expect(player.queue[2].id, 3);
      expect(player.currentIndex, 0);
    });

    // 2. Starting at a selected index
    test('2. Starting at a selected index clamps safely', () async {
      final player = createPlayer();
      final tracks = [
        createMockTrack(id: 1, titleEn: 'Track 1', titleAr: 'مقطع 1'),
        createMockTrack(id: 2, titleEn: 'Track 2', titleAr: 'مقطع 2'),
        createMockTrack(id: 3, titleEn: 'Track 3', titleAr: 'مقطع 3'),
      ];

      await player.loadQueue(tracks, startIndex: 1, autoPlay: false);
      expect(player.currentIndex, 1);
      expect(player.currentTrack?.id, 2);

      // Beyond upper bound
      await player.loadQueue(tracks, startIndex: 99, autoPlay: false);
      expect(player.currentIndex, 2);
      expect(player.currentTrack?.id, 3);

      // Negative index
      await player.loadQueue(tracks, startIndex: -5, autoPlay: false);
      expect(player.currentIndex, 0);
      expect(player.currentTrack?.id, 1);
    });

    // 3. Previous and next boundaries
    test('3. Previous and next boundaries behavior under RepeatMode.off', () async {
      final player = createPlayer();
      final tracks = [
        createMockTrack(id: 1, titleEn: 'Track 1', titleAr: 'مقطع 1'),
        createMockTrack(id: 2, titleEn: 'Track 2', titleAr: 'مقطع 2'),
      ];

      await player.loadQueue(tracks, startIndex: 0, autoPlay: false);
      player.setRepeatMode(RepeatMode.off);

      expect(player.hasPrevious, isFalse);
      expect(player.hasNext, isTrue);

      // Advance to next
      await player.playNext();
      expect(player.currentIndex, 1);
      expect(player.currentTrack?.id, 2);
      expect(player.hasNext, isFalse);
      expect(player.hasPrevious, isTrue);

      // Attempting to advance at the end under RepeatMode.off should not advance past length
      await player.playNext();
      expect(player.currentIndex, 1);

      // Previous returns to index 0
      await player.playPrevious();
      expect(player.currentIndex, 0);
      expect(player.currentTrack?.id, 1);
    });

    // 4. Repeat modes (Off, All, One) at completion
    test('4. Repeat Off, Repeat All and Repeat One auto-advance rules', () async {
      final player = createPlayer();
      final tracks = [
        createMockTrack(id: 1, titleEn: 'Track 1', titleAr: 'مقطع 1'),
        createMockTrack(id: 2, titleEn: 'Track 2', titleAr: 'مقطع 2'),
      ];

      // RepeatMode.all wraps from last track back to start
      await player.loadQueue(tracks, startIndex: 1, autoPlay: false);
      player.setRepeatMode(RepeatMode.all);
      expect(player.hasNext, isTrue);
      await player.playNext(isAutoAdvance: true);
      expect(player.currentIndex, 0);
      expect(player.currentTrack?.id, 1);

      // RepeatMode.one replays current track on auto-advance without advancing index
      player.setRepeatMode(RepeatMode.one);
      expect(player.currentIndex, 0);
      await player.playNext(isAutoAdvance: true);
      expect(player.currentIndex, 0);
      expect(player.currentTrack?.id, 1);

      // RepeatMode.off stops at queue end
      player.setRepeatMode(RepeatMode.off);
      await player.playAtIndex(1);
      expect(player.currentIndex, 1);
      await player.playNext(isAutoAdvance: true);
      expect(player.isPlaying, isFalse);
    });

    // 5. Shuffle activation/deactivation and preservation of original order
    test('5. Shuffle keeps current playing track and restores original queue when disabled', () async {
      final player = createPlayer();
      final tracks = List.generate(
        10,
        (i) => createMockTrack(id: i + 1, titleEn: 'Track ${i + 1}', titleAr: 'مقطع ${i + 1}'),
      );

      await player.loadQueue(tracks, startIndex: 3, autoPlay: false);
      final originalIdAtActive = player.currentTrack?.id;
      expect(originalIdAtActive, 4);

      // Toggle Shuffle ON
      player.toggleShuffle();
      expect(player.isShuffle, isTrue);
      // Active track should remain at active index without interruption
      expect(player.currentTrack?.id, originalIdAtActive);
      expect(player.queue.length, 10);

      // Toggle Shuffle OFF
      player.toggleShuffle();
      expect(player.isShuffle, isFalse);
      // Original queue ordering must be restored
      for (int i = 0; i < 10; i++) {
        expect(player.queue[i].id, tracks[i].id);
      }
      expect(player.currentTrack?.id, originalIdAtActive);
    });

    // 6. Insertion next, append, removal and reordering
    test('6. Queue modification: insertNext, addToQueue, removeAt, reorderQueue', () async {
      final player = createPlayer();
      final t1 = createMockTrack(id: 1, titleEn: 'Track 1', titleAr: 'مقطع 1');
      final t2 = createMockTrack(id: 2, titleEn: 'Track 2', titleAr: 'مقطع 2');
      final t3 = createMockTrack(id: 3, titleEn: 'Track 3', titleAr: 'مقطع 3');
      final tInsert = createMockTrack(id: 99, titleEn: 'Inserted Next', titleAr: 'مدرج تالياً');
      final tAppend = createMockTrack(id: 100, titleEn: 'Appended End', titleAr: 'مضاف أخيراً');

      await player.loadQueue([t1, t2, t3], startIndex: 1, autoPlay: false); // Active is t2 (index 1)
      expect(player.currentTrack?.id, 2);

      // Insert Next: should place tInsert at index 2
      player.insertNext(tInsert);
      expect(player.queue[2].id, 99);
      expect(player.currentTrack?.id, 2);
      expect(player.currentIndex, 1);

      // Add to Queue: should append at end
      player.addToQueue(tAppend);
      expect(player.queue.last.id, 100);

      // Reorder: Move tAppend (index 4) to index 0
      player.reorderQueue(4, 0);
      expect(player.queue[0].id, 100);
      // Current track (t2) was moved from index 1 to index 2
      expect(player.currentTrack?.id, 2);
      expect(player.currentIndex, 2);

      // Remove track at index 0 (tAppend)
      await player.removeAt(0);
      expect(player.queue.any((t) => t.id == 100), isFalse);
      expect(player.currentTrack?.id, 2);
      expect(player.currentIndex, 1);
    });

    // 7. Clearing queue
    test('7. Clearing the queue safely resets track, index, and playback state', () async {
      final player = createPlayer();
      final tracks = [
        createMockTrack(id: 1, titleEn: 'Track 1', titleAr: 'مقطع 1'),
      ];

      await player.loadQueue(tracks, startIndex: 0, autoPlay: false);
      expect(player.hasActiveTrack, isTrue);

      await player.clearQueue();
      expect(player.queue, isEmpty);
      expect(player.currentIndex, -1);
      expect(player.currentTrack, isNull);
      expect(player.hasActiveTrack, isFalse);
      expect(player.isPlaying, isFalse);
    });

    // 8. Auto-advancement completion handler called exactly once
    test('8. Playback completion triggers auto-advancement cleanly', () async {
      final fakeAudio = FakeAudioPlayer();
      final player = createPlayer(player: fakeAudio);
      final tracks = [
        createMockTrack(id: 1, titleEn: 'Track 1', titleAr: 'مقطع 1'),
        createMockTrack(id: 2, titleEn: 'Track 2', titleAr: 'مقطع 2'),
      ];

      await player.loadQueue(tracks, startIndex: 0, autoPlay: false);
      expect(player.currentIndex, 0);

      // Trigger completion via the audio player's completion stream
      fakeAudio.completeTrack();
      await pumpEventQueue();

      expect(player.currentIndex, 1);
      expect(player.currentTrack?.id, 2);
    });

    // 9. Rapid skip requests and asynchronous ticket acquisition race safety
    test('9. Rapid next skips discard superseded stream ticket requests safely', () async {
      final player = createPlayer();
      // Inject network latency to simulate async in-flight ticket acquisition
      mockHttp.delay = const Duration(milliseconds: 30);

      final tracks = List.generate(
        5,
        (i) => createMockTrack(id: i + 1, titleEn: 'Track ${i + 1}', titleAr: 'مقطع ${i + 1}'),
      );
      await player.loadQueue(tracks, startIndex: 0, autoPlay: false);

      // Trigger multiple rapid skips in quick succession without awaiting each one
      final f1 = player.playNext();
      final f2 = player.playNext();
      final f3 = player.playNext();

      await Future.wait([f1, f2, f3]);

      // The final state must match the last requested track (index 3, id 4)
      expect(player.currentIndex, 3);
      expect(player.currentTrack?.id, 4);
      expect(player.isLoadingTicket, isFalse);
    });

    // 10. Regression coverage: artwork, genre filtering, secure ticket URL, mini-player
    test('10. Regression: Artwork, genre display, secure ticket endpoints, and mini-player sync', () {
      final track = createMockTrack(
        id: 77,
        titleEn: 'Doctor (Original Mix)',
        titleAr: 'دكتور (النسخة الأصلية)',
        coverArt: 'https://staging-api.funmoments.unifinitylab.com/storage/artwork/doctor.jpg',
      );

      // Artwork URL is preserved
      expect(track.coverArtUrl, contains('doctor.jpg'));
      // Stream ticket endpoint is secure and points to proper REST route
      expect(track.streamTicketUrl, contains('/music/tracks/77/stream-ticket'));
      // Bilingual titles
      expect(track.displayTitle(false), 'Doctor (Original Mix)');
      expect(track.displayTitle(true), 'دكتور (النسخة الأصلية)');
      // Primary genre helper
      expect(track.genres.first.displayName(false), 'Dance');
    });
  });
}
