import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/model/slider_model.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';

void main() {
  group('Smart Media Engine - Responsive Image Variants Tests', () {
    test('1. CatalogTrackModel parses cover_image_variants correctly', () {
      final json = {
        'id': 101,
        'title_en': 'Desert Mirage',
        'title_ar': 'سراب الصحراء',
        'artist_name': 'Artist One',
        'duration_seconds': 210,
        'cover_image_url': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/mirage.jpg',
        'cover_image_variants': {
          'thumbnail': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/mirage/thumbnail.webp',
          'medium': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/mirage/medium.webp',
          'large': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/mirage/large.webp',
        },
        'stream_ticket_url': 'https://bkfunmoments.unifinitylab.com/api/v1/stream/101',
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.coverImageUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/mirage.jpg');
      expect(track.imageVariants, isNotNull);
      expect(track.thumbnailUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/mirage/thumbnail.webp');
      expect(track.mediumUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/mirage/medium.webp');
      expect(track.largeUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/mirage/large.webp');
    });

    test('2. CatalogTrackModel gracefully falls back to coverImageUrl when variants are missing', () {
      final json = {
        'id': 102,
        'title_en': 'Oasis Breeze',
        'title_ar': 'نسيم الواحة',
        'artist_name': 'Artist Two',
        'duration_seconds': 180,
        'cover_image_url': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/oasis.jpg',
        'stream_ticket_url': 'https://bkfunmoments.unifinitylab.com/api/v1/stream/102',
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.imageVariants, isNull);
      expect(track.thumbnailUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/oasis.jpg');
      expect(track.mediumUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/oasis.jpg');
      expect(track.largeUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/oasis.jpg');
    });

    test('3. CatalogAlbumModel parses variants and getters operate properly', () {
      final json = {
        'id': 201,
        'title_en': 'Golden Dunes',
        'title_ar': 'الكثبان الذهبية',
        'cover_image_url': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/album1.jpg',
        'cover_image_variants': {
          'thumbnail': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/album1/thumbnail.webp',
          'medium': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/album1/medium.webp',
          'large': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/album1/large.webp',
        },
      };

      final album = CatalogAlbumModel.fromJson(json);

      expect(album.coverImageUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/album1.jpg');
      expect(album.thumbnailUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/album1/thumbnail.webp');
      expect(album.mediumUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/album1/medium.webp');
      expect(album.largeUrl, 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/album1/large.webp');
    });

    test('4. SliderModel and CategoryModel parse image_variants correctly', () {
      final sliderJson = {
        'id': 1,
        'title': 'Grand Opening',
        'img_url': 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/slide1.jpg',
        'image_variants': {
          'thumbnail': 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/slide1/thumbnail.webp',
          'medium': 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/slide1/medium.webp',
          'large': 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/slide1/large.webp',
        },
      };

      final slide = SliderDetail.fromJson(sliderJson);
      expect(slide.thumbnailUrl, 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/slide1/thumbnail.webp');
      expect(slide.largeUrl, 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/slide1/large.webp');

      final categoryJson = {
        'id': 5,
        'name': 'Music & DJ',
        'mobile_icon': 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/cat5.jpg',
        'image_variants': {
          'thumbnail': 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/cat5/thumbnail.webp',
          'medium': 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/cat5/medium.webp',
        },
      };

      final cat = Category.fromJson(categoryJson);
      expect(cat.thumbnailUrl, 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/variants/cat5/thumbnail.webp');
      expect(cat.largeUrl, 'https://bkfunmoments.unifinitylab.com/assets/uploads/media-uploader/cat5.jpg'); // Fallback
    });

    testWidgets('5. MusicArtworkWidget chooses optimal variant based on widget dimensions', (WidgetTester tester) async {
      final variants = {
        'thumbnail': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/test/thumbnail.webp',
        'medium': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/test/medium.webp',
        'large': 'https://bkfunmoments.unifinitylab.com/storage/music_covers/variants/test/large.webp',
      };

      // Test small widget (52x52) -> thumbnail variant
      final smallWidget = MusicArtworkWidget(
        imageUrl: 'https://bkfunmoments.unifinitylab.com/storage/music_covers/test.jpg',
        imageVariants: variants,
        width: 52,
        height: 52,
      );

      await tester.pumpWidget(MaterialApp(home: Scaffold(body: smallWidget)));
      expect(find.byType(MusicArtworkWidget), findsOneWidget);

      // Test medium widget (200x200) -> medium variant
      final mediumWidget = MusicArtworkWidget(
        imageUrl: 'https://bkfunmoments.unifinitylab.com/storage/music_covers/test.jpg',
        imageVariants: variants,
        width: 200,
        height: 200,
      );

      await tester.pumpWidget(MaterialApp(home: Scaffold(body: mediumWidget)));
      expect(find.byType(MusicArtworkWidget), findsOneWidget);
    });
  });
}
