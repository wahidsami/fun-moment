import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/music/my_playlists_page.dart';
import 'package:funmoments/view/music/playlist_details_page.dart';
import 'package:funmoments/view/music/components/add_to_playlist_sheet.dart';

CatalogTrackModel makeTestTrack({
  int id = 1,
  String titleEn = 'Desert Mirage',
  String titleAr = 'سراب الصحراء',
  String artistName = 'DJ Mirage',
  int durationSeconds = 180,
  int? playlistPosition,
  TrackAccessStatusModel? accessStatus,
}) {
  return CatalogTrackModel(
    id: id,
    titleEn: titleEn,
    titleAr: titleAr,
    artistName: artistName,
    durationSeconds: durationSeconds,
    previewDurationSeconds: 30,
    streamTicketUrl: 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/catalog/stream-ticket/$id',
    playlistPosition: playlistPosition,
    accessStatus: accessStatus,
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('TrackAccessStatusModel & MusicPlaylistModel Unit Tests', () {
    test('TrackAccessStatusModel correctly parses all 6 status variants', () {
      final freeJson = {
        'status': 'free',
        'is_playable': true,
        'can_stream_full': true,
        'label_en': 'Free Stream',
        'label_ar': 'بث مجاني',
        'expires_at': null,
      };
      final freeStatus = TrackAccessStatusModel.fromJson(freeJson);
      expect(freeStatus.status, 'free');
      expect(freeStatus.isPlayable, true);
      expect(freeStatus.canStreamFull, true);
      expect(freeStatus.labelEn, 'Free Stream');
      expect(freeStatus.labelAr, 'بث مجاني');
      expect(freeStatus.isFree, true);
      expect(freeStatus.isPurchased, false);
      expect(freeStatus.isRented, false);

      final purchasedJson = {
        'status': 'purchased',
        'is_playable': true,
        'can_stream_full': true,
        'label_en': 'Purchased',
        'label_ar': 'تم الشراء',
      };
      final purchasedStatus = TrackAccessStatusModel.fromJson(purchasedJson);
      expect(purchasedStatus.status, 'purchased');
      expect(purchasedStatus.isPurchased, true);
      expect(purchasedStatus.isPlayable, true);
      expect(purchasedStatus.canStreamFull, true);

      final rentedJson = {
        'status': 'rented',
        'is_playable': true,
        'can_stream_full': true,
        'label_en': 'Rented',
        'label_ar': 'مستأجر',
        'expires_at': '2026-11-01T12:00:00Z',
      };
      final rentedStatus = TrackAccessStatusModel.fromJson(rentedJson);
      expect(rentedStatus.status, 'rented');
      expect(rentedStatus.isRented, true);
      expect(rentedStatus.expiresAt, '2026-11-01T12:00:00Z');

      final expiredJson = {
        'status': 'rental_expired',
        'is_playable': true,
        'can_stream_full': false,
        'label_en': 'Rental Expired',
        'label_ar': 'انتهى الاستئجار',
      };
      final expiredStatus = TrackAccessStatusModel.fromJson(expiredJson);
      expect(expiredStatus.status, 'rental_expired');
      expect(expiredStatus.isRentalExpired, true);
      expect(expiredStatus.canStreamFull, false);

      final reqJson = {
        'status': 'purchase_required',
        'is_playable': true,
        'can_stream_full': false,
        'label_en': 'Purchase Required',
        'label_ar': 'يتطلب الشراء',
      };
      final reqStatus = TrackAccessStatusModel.fromJson(reqJson);
      expect(reqStatus.status, 'purchase_required');
      expect(reqStatus.isPurchaseRequired, true);
      expect(reqStatus.canStreamFull, false);

      final unavailJson = {
        'status': 'unavailable',
        'is_playable': false,
        'can_stream_full': false,
        'label_en': 'Unavailable',
        'label_ar': 'غير متاح',
      };
      final unavailStatus = TrackAccessStatusModel.fromJson(unavailJson);
      expect(unavailStatus.status, 'unavailable');
      expect(unavailStatus.isUnavailable, true);
      expect(unavailStatus.isPlayable, false);
      expect(unavailStatus.canStreamFull, false);
    });

    test('MusicPlaylistModel parses playlist with ordered tracks', () {
      final playlistJson = {
        'id': 7,
        'name': 'Riyadh Late Night Beats',
        'description': 'Curated high-energy dance and original mixes',
        'cover_image_url': 'https://example.com/playlist7.jpg',
        'is_public': false,
        'tracks_count': 2,
        'total_duration_seconds': 420,
        'created_at': '2026-10-27T00:00:00Z',
        'updated_at': '2026-10-27T01:00:00Z',
        'tracks': [
          {
            'id': 101,
            'title_en': 'Desert Storm Synth',
            'title_ar': 'عاصفة الصحراء الإلكترونية',
            'artist_name': 'DJ Dune',
            'duration_seconds': 200,
            'preview_duration_seconds': 30,
            'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/catalog/stream-ticket/101',
            'playlist_position': 1,
            'access_status': {
              'status': 'free',
              'is_playable': true,
              'can_stream_full': true,
              'label_en': 'Free Stream',
              'label_ar': 'بث مجاني',
            },
          },
          {
            'id': 102,
            'title_en': 'Neon Oasis (VIP Remix)',
            'title_ar': 'واحة النيون',
            'artist_name': 'Original by DJ Noor (Remix by Falcon)',
            'duration_seconds': 220,
            'preview_duration_seconds': 30,
            'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/catalog/stream-ticket/102',
            'playlist_position': 2,
            'access_status': {
              'status': 'purchase_required',
              'is_playable': true,
              'can_stream_full': false,
              'label_en': 'Purchase Required',
              'label_ar': 'يتطلب الشراء',
            },
          },
        ],
      };

      final playlist = MusicPlaylistModel.fromJson(playlistJson);
      expect(playlist.id, 7);
      expect(playlist.name, 'Riyadh Late Night Beats');
      expect(playlist.tracksCount, 2);
      expect(playlist.tracks.length, 2);
      expect(playlist.tracks[0].playlistPosition, 1);
      expect(playlist.tracks[0].accessStatus?.isFree, true);
      expect(playlist.tracks[1].playlistPosition, 2);
      expect(playlist.tracks[1].accessStatus?.isPurchaseRequired, true);
    });

    test('CatalogTrackModel copyWith preserves and updates accessStatus and playlistPosition', () {
      final track = makeTestTrack(id: 1, durationSeconds: 150);
      expect(track.playlistPosition, null);
      expect(track.accessStatus, null);

      final updated = track.copyWith(
        playlistPosition: 5,
        accessStatus: TrackAccessStatusModel(
          status: 'purchased',
          isPlayable: true,
          canStreamFull: true,
          labelEn: 'Purchased',
          labelAr: 'تم الشراء',
        ),
      );
      expect(updated.playlistPosition, 5);
      expect(updated.accessStatus?.isPurchased, true);
      expect(updated.accessStatus?.canStreamFull, true);
    });
  });

  group('Widget Tests: MyPlaylistsPage', () {
    testWidgets('Renders empty state when user has no playlists', (tester) async {
      await tester.binding.setSurfaceSize(const Size(400, 800));

      final buyerService = BuyerMusicService();
      buyerService.playlists = [];
      buyerService.isPlaylistsLoading = false;
      buyerService.playlistsError = null;

      final playerService = MusicPlayerService();
      final rtlService = RtlService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<MusicPlayerService>.value(value: playerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: const MaterialApp(
            home: MyPlaylistsPage(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('My Playlists'), findsOneWidget);
      expect(find.text('No Playlists Yet'), findsOneWidget);
      expect(find.text('Create Playlist'), findsWidgets);
    });

    testWidgets('Renders Arabic RTL empty state correctly', (tester) async {
      await tester.binding.setSurfaceSize(const Size(400, 800));

      final buyerService = BuyerMusicService();
      buyerService.playlists = [];
      buyerService.isPlaylistsLoading = false;
      buyerService.playlistsError = null;

      final playerService = MusicPlayerService();
      final rtlService = RtlService();
      rtlService.langSlug = 'ar';
      rtlService.direction = 'rtl';

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<MusicPlayerService>.value(value: playerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: const MaterialApp(
            home: MyPlaylistsPage(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('قوائم التشغيل'), findsOneWidget);
      expect(find.text('لا توجد قوائم تشغيل حتى الآن'), findsOneWidget);
      expect(find.text('إنشاء قائمة تشغيل'), findsWidgets);
    });

    testWidgets('Renders playlist list when playlists exist', (tester) async {
      await tester.binding.setSurfaceSize(const Size(400, 800));

      final buyerService = BuyerMusicService();
      buyerService.playlists = [
        MusicPlaylistModel(
          id: 1,
          name: 'Road Trip Saudi',
          description: 'Tracks for desert highway',
          tracksCount: 5,
          totalDurationSeconds: 900,
        ),
        MusicPlaylistModel(
          id: 2,
          name: 'Gym Workout Synth',
          description: 'High energy 130+ BPM',
          tracksCount: 3,
          totalDurationSeconds: 600,
        ),
      ];
      buyerService.isPlaylistsLoading = false;

      final playerService = MusicPlayerService();
      final rtlService = RtlService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<MusicPlayerService>.value(value: playerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: const MaterialApp(
            home: MyPlaylistsPage(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Road Trip Saudi'), findsOneWidget);
      expect(find.text('Gym Workout Synth'), findsOneWidget);
      expect(find.text('5 tracks'), findsOneWidget);
      expect(find.text('15:00'), findsOneWidget);
      expect(find.text('3 tracks'), findsOneWidget);
      expect(find.text('10:00'), findsOneWidget);
    });
  });

  group('Widget Tests: PlaylistDetailsPage', () {
    testWidgets('Displays playlist header, tracks, and entitlement badges', (tester) async {
      await tester.binding.setSurfaceSize(const Size(400, 900));

      final testPlaylist = MusicPlaylistModel(
        id: 10,
        name: 'VIP Club Nights',
        description: 'Exclusively curated tracks',
        tracksCount: 2,
        totalDurationSeconds: 360,
        tracks: [
          makeTestTrack(
            id: 201,
            titleEn: 'Desert Heat',
            titleAr: 'حرارة الصحراء',
            artistName: 'DJ Sahara',
            durationSeconds: 180,
            playlistPosition: 1,
            accessStatus: TrackAccessStatusModel(
              status: 'free',
              isPlayable: true,
              canStreamFull: true,
              labelEn: 'Free Stream',
              labelAr: 'بث مجاني',
            ),
          ),
          makeTestTrack(
            id: 202,
            titleEn: 'Midnight Oud VIP',
            titleAr: 'عود منتصف الليل',
            artistName: 'Tariq Oud',
            durationSeconds: 180,
            playlistPosition: 2,
            accessStatus: TrackAccessStatusModel(
              status: 'purchase_required',
              isPlayable: true,
              canStreamFull: false,
              labelEn: 'Purchase Required',
              labelAr: 'يتطلب الشراء',
            ),
          ),
        ],
      );

      final buyerService = BuyerMusicService();
      buyerService.currentPlaylist = testPlaylist;

      final playerService = MusicPlayerService();
      final rtlService = RtlService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<MusicPlayerService>.value(value: playerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: const MaterialApp(
            home: PlaylistDetailsPage(playlistId: 10),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('VIP Club Nights'), findsWidgets);
      expect(find.text('Desert Heat'), findsOneWidget);
      expect(find.text('Midnight Oud VIP'), findsOneWidget);
      expect(find.text('Free Stream'), findsOneWidget);
      expect(find.text('Purchase Required'), findsOneWidget);
      expect(find.text('Play All'), findsOneWidget);
    });
  });

  group('Widget Tests: AddToPlaylistSheet', () {
    testWidgets('Renders existing playlists and detects already-added tracks', (tester) async {
      await tester.binding.setSurfaceSize(const Size(400, 700));

      final track = makeTestTrack(
        id: 50,
        titleEn: 'Echoes of Diriyah',
        titleAr: 'أصداء الدرعية',
        artistName: 'Sami Sound',
        durationSeconds: 210,
      );

      final buyerService = BuyerMusicService();
      buyerService.playlists = [
        MusicPlaylistModel(
          id: 1,
          name: 'Traditional & Fusion',
          tracksCount: 3,
        ),
        MusicPlaylistModel(
          id: 2,
          name: 'Workout Beats',
          tracksCount: 1,
        ),
      ];

      final rtlService = RtlService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: AddToPlaylistSheet(track: track),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Add to Playlist'), findsOneWidget);
      expect(find.text('Echoes of Diriyah'), findsOneWidget);
      expect(find.text('Traditional & Fusion'), findsOneWidget);
      expect(find.text('Workout Beats'), findsOneWidget);
      expect(find.text('New Playlist'), findsOneWidget);
    });
  });

  group('Local Playlist Persistence & Dual-Mode Operations', () {
    test('MusicPlaylistModel toJson and fromJson roundtrip correctly', () {
      final track = makeTestTrack(id: 42, titleEn: 'Oasis Sunset', durationSeconds: 210);
      final original = MusicPlaylistModel(
        id: 999,
        name: 'My Chill Mix',
        description: 'Desert relaxation vibes',
        coverImageUrl: 'https://example.com/cover.jpg',
        isPublic: false,
        tracksCount: 1,
        totalDurationSeconds: 210,
        createdAt: '2026-10-27T10:00:00Z',
        updatedAt: '2026-10-27T10:30:00Z',
        tracks: [track],
      );

      final jsonMap = original.toJson();
      expect(jsonMap['id'], 999);
      expect(jsonMap['name'], 'My Chill Mix');
      expect(jsonMap['tracks'], isA<List>());
      expect((jsonMap['tracks'] as List).length, 1);

      final reconstructed = MusicPlaylistModel.fromJson(jsonMap);
      expect(reconstructed.id, 999);
      expect(reconstructed.name, 'My Chill Mix');
      expect(reconstructed.description, 'Desert relaxation vibes');
      expect(reconstructed.tracksCount, 1);
      expect(reconstructed.tracks.length, 1);
      expect(reconstructed.tracks[0].id, 42);
      expect(reconstructed.tracks[0].titleEn, 'Oasis Sunset');
    });

    test('createPlaylist creates and persists playlist locally when token is null or remote fails', () async {
      SharedPreferences.setMockInitialValues({});
      final buyerService = BuyerMusicService();
      buyerService.playlists = [];

      final created = await buyerService.createPlaylist('Offline Vibes', description: 'Saved locally');
      expect(created, isNotNull);
      expect(created!.name, 'Offline Vibes');
      expect(created.description, 'Saved locally');
      expect(buyerService.playlists.length, 1);
      expect(buyerService.playlists[0].name, 'Offline Vibes');

      // Verify that a new service instance can restore it via fetchPlaylists
      final newService = BuyerMusicService();
      final loaded = await newService.fetchPlaylists();
      expect(loaded.length, 1);
      expect(loaded[0].name, 'Offline Vibes');
    });

    test('addTrackToPlaylist adds track, updates duration, and prevents duplicates locally', () async {
      SharedPreferences.setMockInitialValues({});
      final buyerService = BuyerMusicService();
      buyerService.playlists = [];

      final playlist = await buyerService.createPlaylist('Road Trip Saudi');
      expect(playlist, isNotNull);

      final testTrack = makeTestTrack(id: 501, titleEn: 'Highway Beat', durationSeconds: 200);
      buyerService.catalogTracks = [testTrack];

      // Add track
      final res1 = await buyerService.addTrackToPlaylist(playlist!.id, 501);
      expect(res1['success'], true);
      expect(buyerService.playlists[0].tracksCount, 1);
      expect(buyerService.playlists[0].totalDurationSeconds, 200);
      expect(buyerService.playlists[0].tracks.length, 1);

      // Duplicate add should be rejected
      final res2 = await buyerService.addTrackToPlaylist(playlist.id, 501);
      expect(res2['success'], false);
      expect(buyerService.playlists[0].tracksCount, 1);
    });

    test('removeTrackFromPlaylist removes track and updates counts', () async {
      SharedPreferences.setMockInitialValues({});
      final buyerService = BuyerMusicService();
      buyerService.playlists = [];

      final playlist = await buyerService.createPlaylist('Favorites Collection');
      final testTrack = makeTestTrack(id: 601, durationSeconds: 150);
      buyerService.catalogTracks = [testTrack];
      await buyerService.addTrackToPlaylist(playlist!.id, 601);
      expect(buyerService.playlists[0].tracksCount, 1);

      final removed = await buyerService.removeTrackFromPlaylist(playlist.id, 601);
      expect(removed, true);
      expect(buyerService.playlists[0].tracksCount, 0);
      expect(buyerService.playlists[0].tracks.isEmpty, true);
    });

    test('fetchPlaylistsForTrack accurately computes containsTrack in local mode', () async {
      SharedPreferences.setMockInitialValues({});
      final buyerService = BuyerMusicService();
      buyerService.playlists = [];

      final playlist1 = await buyerService.createPlaylist('Rock');
      final playlist2 = await buyerService.createPlaylist('Jazz');

      final track1 = makeTestTrack(id: 701);
      buyerService.catalogTracks = [track1];
      await buyerService.addTrackToPlaylist(playlist1!.id, 701);

      final results = await buyerService.fetchPlaylistsForTrack(701);
      expect(results.length, 2);

      final rock = results.firstWhere((p) => p.name == 'Rock');
      final jazz = results.firstWhere((p) => p.name == 'Jazz');
      expect(rock.containsTrack, true);
      expect(jazz.containsTrack, false);
    });
  });
}
