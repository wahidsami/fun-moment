import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/music/components/artwork_ambient_lighting_widget.dart';
import 'package:funmoments/view/music/components/dj_visualizer_widget.dart';
import 'package:funmoments/view/music/now_playing_visualizer_page.dart';

CatalogTrackModel createTestTrack({
  int id = 101,
  String titleEn = 'Neon Riyadh Beats',
  String titleAr = 'إيقاعات الرياض النيونية',
  String artistName = 'DJ Desert Pulse',
  int durationSeconds = 180,
  int bpm = 128,
  bool isFirstParty = true,
  String? coverImageUrl,
}) {
  return CatalogTrackModel(
    id: id,
    titleEn: titleEn,
    titleAr: titleAr,
    artistName: artistName,
    durationSeconds: durationSeconds,
    previewDurationSeconds: 30,
    bpm: bpm,
    musicalKey: 'G Minor',
    coverImageUrl: coverImageUrl ?? 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/test.jpg',
    streamTicketUrl: 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/catalog/stream-ticket/$id',
    isFirstParty: isFirstParty,
    badge: 'FUN MOMENT Original',
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('DJVisualizerMode Enum & Properties', () {
    test('All modes have localized titles and icons', () {
      expect(DJVisualizerMode.neonSpectrum.nameEn, 'Neon Spectrum');
      expect(DJVisualizerMode.neonSpectrum.nameAr, 'طيف النيون');
      expect(DJVisualizerMode.neonSpectrum.icon, Icons.equalizer_rounded);

      expect(DJVisualizerMode.beatRing.nameEn, 'Beat Ring');
      expect(DJVisualizerMode.beatRing.nameAr, 'حلقات الإيقاع');
      expect(DJVisualizerMode.beatRing.icon, Icons.radar_rounded);

      expect(DJVisualizerMode.clubLights.nameEn, 'Club Lights');
      expect(DJVisualizerMode.clubLights.nameAr, 'أضواء الحفلة');
      expect(DJVisualizerMode.clubLights.icon, Icons.flare_rounded);

      expect(DJVisualizerMode.off.nameEn, 'Off');
      expect(DJVisualizerMode.off.nameAr, 'إيقاف');
    });
  });

  group('DJVisualizerWidget Rendering & Compact Mode', () {
    testWidgets('Renders in Neon Spectrum mode', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: DJVisualizerWidget(
              mode: DJVisualizerMode.neonSpectrum,
              isPlaying: true,
              bpm: 128,
              position: Duration(seconds: 15),
              duration: Duration(seconds: 180),
            ),
          ),
        ),
      );

      expect(find.byType(DJVisualizerWidget), findsOneWidget);
      expect(find.byType(CustomPaint), findsWidgets);
    });

    testWidgets('Renders in Beat Ring mode', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: DJVisualizerWidget(
              mode: DJVisualizerMode.beatRing,
              isPlaying: true,
              bpm: 128,
            ),
          ),
        ),
      );

      expect(find.byType(DJVisualizerWidget), findsOneWidget);
    });

    testWidgets('Renders in Club Lights mode', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: DJVisualizerWidget(
              mode: DJVisualizerMode.clubLights,
              isPlaying: true,
              bpm: 128,
            ),
          ),
        ),
      );

      expect(find.byType(DJVisualizerWidget), findsOneWidget);
    });

    testWidgets('Renders child when mode is Off', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: DJVisualizerWidget(
              mode: DJVisualizerMode.off,
              isPlaying: false,
              child: Text('Off Child Widget'),
            ),
          ),
        ),
      );

      expect(find.text('Off Child Widget'), findsOneWidget);
    });

    testWidgets('Renders in compact mode without error', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: SizedBox(
              width: 300,
              height: 48,
              child: DJVisualizerWidget(
                mode: DJVisualizerMode.neonSpectrum,
                isPlaying: true,
                isCompact: true,
                bpm: 120,
              ),
            ),
          ),
        ),
      );

      expect(find.byType(DJVisualizerWidget), findsOneWidget);
      expect(find.byType(CustomPaint), findsWidgets);
    });

    testWidgets('Renders subdued resting state when paused', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: DJVisualizerWidget(
              mode: DJVisualizerMode.neonSpectrum,
              isPlaying: false,
              bpm: 128,
            ),
          ),
        ),
      );

      expect(find.byType(DJVisualizerWidget), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.byType(CustomPaint), findsWidgets);
    });
  });

  group('ArtworkAmbientLightingWidget', () {
    testWidgets('Renders fallback placeholder gracefully when coverImageUrl is null', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: ArtworkAmbientLightingWidget(
              imageUrl: null,
              size: 200,
              isPlaying: true,
            ),
          ),
        ),
      );

      expect(find.byType(ArtworkAmbientLightingWidget), findsOneWidget);
      expect(find.byIcon(Icons.music_note_rounded), findsOneWidget);
    });

    testWidgets('Renders with non-null image URL and overlays', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: ArtworkAmbientLightingWidget(
              imageUrl: 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/test.jpg',
              size: 200,
              isPlaying: true,
              overlayChild: Center(child: Text('Center Overlay')),
            ),
          ),
        ),
      );

      expect(find.byType(ArtworkAmbientLightingWidget), findsOneWidget);
      expect(find.text('Center Overlay'), findsOneWidget);
    });

    testWidgets('Animates smoothly between playing and paused states', (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: ArtworkAmbientLightingWidget(
              imageUrl: 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/test.jpg',
              size: 200,
              isPlaying: false,
            ),
          ),
        ),
      );

      expect(find.byType(ArtworkAmbientLightingWidget), findsOneWidget);

      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: ArtworkAmbientLightingWidget(
              imageUrl: 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/test.jpg',
              size: 200,
              isPlaying: true,
            ),
          ),
        ),
      );

      await tester.pump(const Duration(milliseconds: 100));
      expect(find.byType(ArtworkAmbientLightingWidget), findsOneWidget);
    });
  });

  group('NowPlayingVisualizerPage Layout & RTL Alignment', () {
    testWidgets('English layout renders Now Playing metadata and playback controls', (WidgetTester tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final track = createTestTrack();
      final playerService = MusicPlayerService();
      final buyerService = BuyerMusicService();
      final rtlService = RtlService();
      rtlService.langSlug = 'en';

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<MusicPlayerService>.value(value: playerService),
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: MaterialApp(
            home: NowPlayingVisualizerPage(track: track),
          ),
        ),
      );

      // Verify track title and artist
      expect(find.text('Neon Riyadh Beats'), findsOneWidget);
      expect(find.text('DJ Desert Pulse'), findsOneWidget);
      expect(find.text('128 BPM'), findsOneWidget);

      // Verify mode chip and pill
      expect(find.text('Neon Spectrum'), findsWidgets);

      // Verify ambient artwork widget centerpiece
      expect(find.byType(ArtworkAmbientLightingWidget), findsOneWidget);

      // Verify controls exist
      expect(find.byIcon(Icons.skip_previous_rounded), findsOneWidget);
      expect(find.byIcon(Icons.play_arrow_rounded), findsOneWidget);
      expect(find.byIcon(Icons.skip_next_rounded), findsOneWidget);
      expect(find.byIcon(Icons.shuffle_rounded), findsOneWidget);
      expect(find.byIcon(Icons.repeat_rounded), findsOneWidget);

      // Verify physical horizontal positions in English (LTR):
      // Previous must be to the visual left of Play/Pause
      // Play/Pause must be to the visual left of Next
      final prevCenter = tester.getCenter(find.byIcon(Icons.skip_previous_rounded));
      final playCenter = tester.getCenter(find.byIcon(Icons.play_arrow_rounded));
      final nextCenter = tester.getCenter(find.byIcon(Icons.skip_next_rounded));

      expect(prevCenter.dx < playCenter.dx, isTrue,
          reason: 'In English, Previous button must be physically to the left of Play/Pause');
      expect(playCenter.dx < nextCenter.dx, isTrue,
          reason: 'In English, Next button must be physically to the right of Play/Pause');
    });

    testWidgets('Arabic RTL layout preserves strict visual positions: Previous on left, Next on right', (WidgetTester tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final track = createTestTrack();
      final playerService = MusicPlayerService();
      final buyerService = BuyerMusicService();
      final rtlService = RtlService();
      rtlService.langSlug = 'ar';

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<MusicPlayerService>.value(value: playerService),
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: MaterialApp(
            locale: const Locale('ar'),
            home: Directionality(
              textDirection: TextDirection.rtl,
              child: NowPlayingVisualizerPage(track: track),
            ),
          ),
        ),
      );

      // Verify Arabic title and artist
      expect(find.text('إيقاعات الرياض النيونية'), findsOneWidget);
      expect(find.text('DJ Desert Pulse'), findsOneWidget);

      // Verify mode chip in Arabic (appears in top chip and bottom pill)
      expect(find.text('طيف النيون'), findsWidgets);

      // Verify controls exist
      expect(find.byIcon(Icons.skip_previous_rounded), findsOneWidget);
      expect(find.byIcon(Icons.play_arrow_rounded), findsOneWidget);
      expect(find.byIcon(Icons.skip_next_rounded), findsOneWidget);

      // In Arabic RTL, ensure Previous is still visually to the left of Play/Pause,
      // and Next is visually to the right of Play/Pause!
      final prevCenter = tester.getCenter(find.byIcon(Icons.skip_previous_rounded));
      final playCenter = tester.getCenter(find.byIcon(Icons.play_arrow_rounded));
      final nextCenter = tester.getCenter(find.byIcon(Icons.skip_next_rounded));

      expect(prevCenter.dx < playCenter.dx, isTrue,
          reason: 'In Arabic RTL, Previous button MUST remain physically to the left of Play/Pause');
      expect(playCenter.dx < nextCenter.dx, isTrue,
          reason: 'In Arabic RTL, Next button MUST remain physically to the right of Play/Pause');
    });

    testWidgets('Switching visualizer mode updates active mode without interrupting player state', (WidgetTester tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final track = createTestTrack();
      final playerService = MusicPlayerService();
      final buyerService = BuyerMusicService();
      final rtlService = RtlService();
      rtlService.langSlug = 'en';

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<MusicPlayerService>.value(value: playerService),
            ChangeNotifierProvider<BuyerMusicService>.value(value: buyerService),
            ChangeNotifierProvider<RtlService>.value(value: rtlService),
          ],
          child: MaterialApp(
            home: NowPlayingVisualizerPage(track: track),
          ),
        ),
      );

      expect(find.text('Neon Spectrum'), findsOneWidget);

      // Tap Beat Ring pill
      await tester.tap(find.text('Beat Ring'));
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Beat Ring'), findsWidgets);
      // Ensure player state is not reset or interrupted
      expect(playerService.repeatMode, RepeatMode.off);

      // Tap Club Lights pill
      await tester.tap(find.text('Club Lights'));
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Club Lights'), findsWidgets);
    });
  });
}

