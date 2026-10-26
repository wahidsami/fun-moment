import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';

void main() {
  group('MusicArtworkWidget Rendering Tests', () {
    testWidgets('1. Missing/null URL renders placeholder icon without crashing', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: MusicArtworkWidget(
              imageUrl: null,
              width: 80,
              height: 80,
            ),
          ),
        ),
      );

      expect(find.byType(MusicArtworkWidget), findsOneWidget);
      expect(find.byIcon(Icons.music_note_rounded), findsOneWidget);
      expect(find.byType(Image), findsNothing);
    });

    testWidgets('2. Empty/whitespace URL renders placeholder icon', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: MusicArtworkWidget(
              imageUrl: '   ',
              width: 80,
              height: 80,
            ),
          ),
        ),
      );

      expect(find.byIcon(Icons.music_note_rounded), findsOneWidget);
      expect(find.byType(Image), findsNothing);
    });

    testWidgets('3. Invalid URL scheme renders placeholder icon', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: MusicArtworkWidget(
              imageUrl: 'not-a-valid-url',
              width: 80,
              height: 80,
            ),
          ),
        ),
      );

      expect(find.byIcon(Icons.music_note_rounded), findsOneWidget);
      expect(find.byType(Image), findsNothing);
    });

    testWidgets('4. Valid HTTPS image URL instantiates CachedNetworkImage with proper parameters', (tester) async {
      const testUrl = 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/cover_bd93aef1-fa44-4988-9c84-379f24380249.png';

      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: MusicArtworkWidget(
              imageUrl: testUrl,
              width: 110,
              height: 110,
              borderRadius: FMRadii.md,
              fit: BoxFit.cover,
            ),
          ),
        ),
      );

      expect(find.byType(MusicArtworkWidget), findsOneWidget);
      final cachedImageFinder = find.byType(CachedNetworkImage);
      expect(cachedImageFinder, findsOneWidget);

      final cachedImage = tester.widget<CachedNetworkImage>(cachedImageFinder);
      expect(cachedImage.imageUrl, testUrl);
      expect(cachedImage.fit, BoxFit.cover);
      expect(cachedImage.width, 110);
      expect(cachedImage.height, 110);
      expect(cachedImage.memCacheWidth, 220);
      expect(cachedImage.memCacheHeight, 220);
    });

    testWidgets('5. Custom styling (border, shadow, placeholder colors) is respected', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: MusicArtworkWidget(
              imageUrl: null,
              width: 260,
              height: 260,
              borderRadius: FMRadii.lg,
              placeholderIcon: Icons.music_note_rounded,
              placeholderIconSize: 64,
              placeholderBackgroundColor: FMColors.surfaceElevated,
              placeholderIconColor: FMColors.cyan,
              border: Border.all(color: FMColors.border),
              boxShadow: const [
                BoxShadow(
                  color: Colors.black87,
                  blurRadius: 24,
                  offset: Offset(0, 10),
                ),
              ],
            ),
          ),
        ),
      );

      expect(find.byIcon(Icons.music_note_rounded), findsOneWidget);
      final iconWidget = tester.widget<Icon>(find.byIcon(Icons.music_note_rounded));
      expect(iconWidget.size, 64);
      expect(iconWidget.color, FMColors.cyan);
    });

    testWidgets('6. Track model with Doctor cover_image_url binds seamlessly into MusicArtworkWidget', (tester) async {
      final track = CatalogTrackModel.fromJson({
        'id': 3,
        'title_en': 'Doctor',
        'title_ar': 'دكتور',
        'artist_name': 'FUN MOMENT Originals',
        'cover_image_url': 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/cover_bd93aef1-fa44-4988-9c84-379f24380249.png',
        'duration_seconds': 148,
        'preview_duration_seconds': 20,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/3/stream-ticket',
      });

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: MusicArtworkWidget(
              imageUrl: track.coverArtUrl,
              width: 52,
              height: 52,
              borderRadius: FMRadii.sm,
            ),
          ),
        ),
      );

      final cachedImageFinder = find.byType(CachedNetworkImage);
      expect(cachedImageFinder, findsOneWidget);
      final cachedImage = tester.widget<CachedNetworkImage>(cachedImageFinder);
      expect(cachedImage.imageUrl, track.coverImageUrl);
      expect(cachedImage.width, 52);
      expect(cachedImage.height, 52);
    });
  });
}
