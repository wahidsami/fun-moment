import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

void main() {
  group('Music Artwork Model & Deserialization Tests', () {
    test('1. Doctor track parses cover_image_url absolute HTTPS URL correctly', () {
      final json = {
        'id': 3,
        'title_en': 'Doctor',
        'title_ar': 'دكتور',
        'artist_name': 'FUN MOMENT Originals',
        'cover_image_url': 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/cover_bd93aef1-fa44-4988-9c84-379f24380249.png',
        'duration_seconds': 148,
        'preview_duration_seconds': 20,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/3/stream-ticket',
        'is_first_party': true,
        'badge': 'FUN MOMENT Original',
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.id, 3);
      expect(track.coverImageUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/cover_bd93aef1-fa44-4988-9c84-379f24380249.png');
      expect(track.coverArtUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/cover_bd93aef1-fa44-4988-9c84-379f24380249.png');
      expect(track.coverArtUrl, equals(track.coverImageUrl));
    });

    test('2. Backward compatibility: parses legacy cover_art_url field if present', () {
      final json = {
        'id': 4,
        'title_en': 'Desert Mirage',
        'title_ar': 'سراب الصحراء',
        'artist_name': 'FUN MOMENT Originals',
        'cover_art_url': 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/legacy_art.png',
        'duration_seconds': 200,
        'preview_duration_seconds': 30,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/4/stream-ticket',
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.id, 4);
      expect(track.coverImageUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/legacy_art.png');
      expect(track.coverArtUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/legacy_art.png');
    });

    test('3. Track with null/empty cover falls back to album cover_image_url', () {
      final json = {
        'id': 5,
        'title_en': 'Album Track 1',
        'title_ar': 'مسار من الألبوم',
        'artist_name': 'FUN MOMENT Originals',
        'cover_image_url': null,
        'duration_seconds': 180,
        'preview_duration_seconds': 20,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/5/stream-ticket',
        'album': {
          'id': 10,
          'title': 'Showcase Vol 1',
          'title_en': 'Showcase Vol 1',
          'title_ar': 'المعرض ١',
          'cover_image_url': 'https://staging-api.funmoments.unifinitylab.com/storage/album_covers/album_10.jpg',
        },
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.coverImageUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/album_covers/album_10.jpg');
      expect(track.coverArtUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/album_covers/album_10.jpg');
    });

    test('4. Resolves relative cover paths to origin-based absolute URLs', () {
      final origin = baseApi.replaceAll(RegExp(r'/api/v1/?$'), '');

      final relativePath1 = 'music_covers/cover_test1.png';
      final resolved1 = CatalogTrackModel.resolveCoverUrl(relativePath1);
      expect(resolved1, '$origin/storage/music_covers/cover_test1.png');

      final relativePath2 = '/storage/music_covers/cover_test2.png';
      final resolved2 = CatalogTrackModel.resolveCoverUrl(relativePath2);
      expect(resolved2, '$origin/storage/music_covers/cover_test2.png');
    });

    test('5. Missing, null, or empty string cover resolves to null', () {
      expect(CatalogTrackModel.resolveCoverUrl(null), isNull);
      expect(CatalogTrackModel.resolveCoverUrl(''), isNull);
      expect(CatalogTrackModel.resolveCoverUrl('   '), isNull);

      final track = CatalogTrackModel.fromJson({
        'id': 6,
        'title_en': 'No Cover Track',
        'title_ar': '',
        'artist_name': 'Artist',
        'duration_seconds': 120,
        'preview_duration_seconds': 30,
        'stream_ticket_url': '',
      });

      expect(track.coverImageUrl, isNull);
      expect(track.coverArtUrl, isNull);
    });

    test('6. CatalogAlbumModel resolves relative cover_image_url properly', () {
      final origin = baseApi.replaceAll(RegExp(r'/api/v1/?$'), '');

      final album = CatalogAlbumModel.fromJson({
        'id': 7,
        'title': 'Test Album',
        'title_en': 'Test Album',
        'title_ar': 'ألبوم تجريبي',
        'cover_image_url': 'music_covers/album_cover.png',
      });

      expect(album.coverImageUrl, '$origin/storage/music_covers/album_cover.png');
    });

    test('7. copyWith correctly copies and overrides artwork URLs', () {
      final track = CatalogTrackModel.fromJson({
        'id': 8,
        'title_en': 'Copy Track',
        'title_ar': '',
        'artist_name': 'Artist',
        'cover_image_url': 'https://staging-api.funmoments.unifinitylab.com/storage/art1.png',
        'duration_seconds': 150,
        'preview_duration_seconds': 30,
        'stream_ticket_url': '',
      });

      // Preserves original cover
      final copiedSame = track.copyWith(isFavorite: true);
      expect(copiedSame.coverImageUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/art1.png');
      expect(copiedSame.isFavorite, isTrue);

      // Overrides coverImageUrl
      final copiedNew = track.copyWith(
        coverImageUrl: 'https://staging-api.funmoments.unifinitylab.com/storage/art2.png',
      );
      expect(copiedNew.coverImageUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/art2.png');
      expect(copiedNew.coverArtUrl, 'https://staging-api.funmoments.unifinitylab.com/storage/art2.png');
    });

    test('8. Staging API genre JSON with name_en/name_ar parses correctly and displays localized names', () {
      final json = {
        'id': 1,
        'name_en': 'House',
        'name_ar': 'هاوس',
        'slug': 'house',
        'sort_order': 1,
        'icon': 'disc',
      };

      final genre = CatalogGenreModel.fromJson(json);

      expect(genre.id, 1);
      expect(genre.nameEn, 'House');
      expect(genre.nameAr, 'هاوس');
      expect(genre.slug, 'house');
      expect(genre.icon, 'disc');
      expect(genre.sortOrder, 1);

      // Verify display names
      expect(genre.displayName(false), 'House');
      expect(genre.displayName(true), 'هاوس');
      expect(genre.name, 'House'); // backward compatibility getter
    });

    test('9. Track JSON with primary_genre and secondary_genres parses genres correctly', () {
      final json = {
        'id': 3,
        'title_en': 'Doctor',
        'title_ar': 'دكتور',
        'artist_name': 'FUN MOMENT Originals',
        'cover_image_url': 'https://staging-api.funmoments.unifinitylab.com/storage/music_covers/cover_bd93aef1-fa44-4988-9c84-379f24380249.png',
        'duration_seconds': 167,
        'preview_duration_seconds': 20,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/3/stream-ticket',
        'primary_genre': {
          'id': 1,
          'name_en': 'House',
          'name_ar': 'هاوس',
          'slug': 'house',
        },
        'secondary_genres': [
          {
            'id': 2,
            'name_en': 'Techno',
            'name_ar': 'تكنو',
            'slug': 'techno',
          },
        ],
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.primaryGenre, isNotNull);
      expect(track.primaryGenre!.displayName(false), 'House');
      expect(track.primaryGenre!.displayName(true), 'هاوس');
      expect(track.genres.length, 2);
      expect(track.genres[0].displayName(false), 'House');
      expect(track.genres[0].displayName(true), 'هاوس');
      expect(track.genres[1].displayName(false), 'Techno');
      expect(track.genres[1].displayName(true), 'تكنو');
    });

    test('10. Genre fallback: missing translation falls back cleanly', () {
      final englishOnly = CatalogGenreModel.fromJson({
        'id': 10,
        'name_en': 'Ambient',
        'name_ar': '',
        'slug': 'ambient',
      });
      expect(englishOnly.displayName(true), 'Ambient');
      expect(englishOnly.displayName(false), 'Ambient');

      final arabicOnly = CatalogGenreModel.fromJson({
        'id': 11,
        'name_en': '',
        'name_ar': 'شعبي',
        'slug': 'shaabi',
      });
      expect(arabicOnly.displayName(false), 'شعبي');
      expect(arabicOnly.displayName(true), 'شعبي');

      final slugOnly = CatalogGenreModel.fromJson({
        'id': 12,
        'slug': 'electronic',
      });
      expect(slugOnly.displayName(false), 'electronic');
      expect(slugOnly.displayName(true), 'electronic');
    });
  });
}
