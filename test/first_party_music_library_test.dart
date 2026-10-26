import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/buyer_music_model.dart';

void main() {
  group('First-Party Music Library & Catalog Models', () {
    test('First-party track parses correctly with badge and admin ownership', () {
      final json = {
        'id': 101,
        'title_en': 'Desert Echoes (Original Mix)',
        'title_ar': 'أصداء الصحراء',
        'artist_name': 'FUN MOMENT Originals',
        'duration_seconds': 240,
        'preview_duration_seconds': 30,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/101/stream-ticket',
        'is_first_party': true,
        'owner_type': 'admin',
        'badge': 'FUN MOMENT Original',
        'album': {
          'id': 1,
          'title': 'FUN MOMENT Staging Showcase Vol. 1',
          'title_en': 'FUN MOMENT Staging Showcase Vol. 1',
          'title_ar': 'مجموعة فن مومنت التجريبية الإصدار ١',
          'artist_name': 'FUN MOMENT Originals',
          'tracks_count': 2,
        },
        'track_number': 1,
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.id, 101);
      expect(track.isFirstParty, isTrue);
      expect(track.badge, 'FUN MOMENT Original');
      expect(track.displayBadge(false), 'FUN MOMENT Original');
      expect(track.displayBadge(true), 'أصلي من FUN MOMENT');
      expect(track.album, isNotNull);
      expect(track.album!.id, 1);
      expect(track.album!.title, 'FUN MOMENT Staging Showcase Vol. 1');
      expect(track.trackNumber, 1);
      expect(track.isRemix, isFalse);
    });

    test('Remix track parses original track attribution correctly', () {
      final json = {
        'id': 102,
        'title_en': 'Desert Echoes (Night Drive Remix)',
        'title_ar': 'أصداء الصحراء (ريمكس)',
        'artist_name': 'FUN MOMENT Originals',
        'duration_seconds': 180,
        'preview_duration_seconds': 30,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/102/stream-ticket',
        'is_first_party': true,
        'owner_type': 'admin',
        'badge': 'FUN MOMENT Original',
        'original_track_id': 101,
        'original_track': {
          'id': 101,
          'title': 'Desert Echoes (Original Mix)',
          'title_en': 'Desert Echoes (Original Mix)',
          'title_ar': 'أصداء الصحراء',
          'artist_name': 'FUN MOMENT Originals',
        },
        'track_number': 2,
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.id, 102);
      expect(track.isFirstParty, isTrue);
      expect(track.isRemix, isTrue);
      expect(track.originalTrack, isNotNull);
      expect(track.originalTrack!.id, 101);
      expect(track.originalTrack!.title, 'Desert Echoes (Original Mix)');
    });

    test('Third-party seller track preserves seller info and does not show first-party badge', () {
      final json = {
        'id': 50,
        'title_en': 'Riyadh Beats',
        'title_ar': 'إيقاعات الرياض',
        'artist_name': 'DJ Sultan',
        'duration_seconds': 210,
        'preview_duration_seconds': 20,
        'stream_ticket_url': 'https://staging-api.funmoments.unifinitylab.com/api/v1/music/tracks/50/stream-ticket',
        'is_first_party': false,
        'owner_type': 'seller',
        'seller': {
          'id': 99,
          'name': 'DJ Sultan Studio',
        },
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.id, 50);
      expect(track.isFirstParty, isFalse);
      expect(track.displayBadge(false), '');
      expect(track.displayBadge(true), '');
      expect(track.seller, isNotNull);
      expect(track.seller!.name, 'DJ Sultan Studio');
      expect(track.album, isNull);
      expect(track.isRemix, isFalse);
    });
  });
}
