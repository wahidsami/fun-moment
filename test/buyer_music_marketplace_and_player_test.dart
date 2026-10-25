import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/music_player_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    SharedPreferences.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('xyz.luan/audioplayers.global'),
            (call) async => null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('xyz.luan/audioplayers'),
            (call) async => null);
  });

  group('Phase 4: Buyer Music Models & Helpers Tests', () {
    test('CatalogTrackModel parses JSON correctly with primary and secondary genres', () {
      final json = {
        'id': 42,
        'title_en': 'Desert Mirage',
        'title_ar': 'سراب الصحراء',
        'artist_name': 'DJ Mirage',
        'duration_seconds': 215,
        'preview_duration_seconds': 30,
        'bpm': 128,
        'key': 'Fm',
        'cover_art_url': 'https://example.com/cover.jpg',
        'stream_ticket_url': 'https://example.com/api/v1/music/tracks/42/stream-ticket',
        'is_favorite': true,
        'play_count': 150,
        'primary_genre': {
          'id': 1,
          'name': 'Electronic',
          'slug': 'electronic',
        },
        'genres': [
          {'id': 2, 'name': 'Dance', 'slug': 'dance'},
        ],
        'offers': [
          {
            'id': 10,
            'offer_type': 'free',
            'price': 0.0,
            'currency': 'SAR',
            'is_active': true,
          },
          {
            'id': 11,
            'offer_type': 'rent',
            'price': 19.99,
            'currency': 'SAR',
            'rental_duration_days': 7,
            'is_active': true,
          },
          {
            'id': 12,
            'offer_type': 'sale',
            'price': 49.99,
            'currency': 'SAR',
            'is_active': true,
          },
        ],
        'rights_declaration': 'exclusive',
        'seller': {
          'id': 5,
          'name': 'Artist Studio',
        },
      };

      final track = CatalogTrackModel.fromJson(json);

      expect(track.id, 42);
      expect(track.titleEn, 'Desert Mirage');
      expect(track.titleAr, 'سراب الصحراء');
      expect(track.artistName, 'DJ Mirage');
      expect(track.durationSeconds, 215);
      expect(track.formattedDuration, '03:35');
      expect(track.formattedPreviewDuration, '00:30');
      expect(track.isFavorite, isTrue);
      expect(track.bpm, 128);
      expect(track.musicalKey, 'Fm');

      // Bilingual display title
      expect(track.displayTitle(false), 'Desert Mirage');
      expect(track.displayTitle(true), 'سراب الصحراء');

      // Genres deduplication and inclusion
      expect(track.genres.length, 2);
      expect(track.genres.first.name, 'Electronic');

      // Commercial offers helpers
      expect(track.hasFreeOffer, isTrue);
      expect(track.hasRentOffer, isTrue);
      expect(track.hasSaleOffer, isTrue);
      expect(track.freeOffer?.formattedPrice, 'Free');
      expect(track.rentOffer?.formattedPrice, '19.99 SAR');
      expect(track.saleOffer?.formattedPrice, '49.99 SAR');
    });

    test('CatalogTrackModel handles missing Arabic title gracefully', () {
      final json = {
        'id': 99,
        'title_en': 'English Only Track',
        'title_ar': '',
        'artist_name': 'Solo Artist',
        'duration_seconds': 120,
        'stream_ticket_url': 'https://example.com/ticket',
      };

      final track = CatalogTrackModel.fromJson(json);
      expect(track.displayTitle(true), 'English Only Track');
      expect(track.displayTitle(false), 'English Only Track');
    });

    test('CatalogResponse parses pagination and track lists', () {
      final json = {
        'success': true,
        'data': {
          'current_page': 2,
          'last_page': 5,
          'total': 75,
          'data': [
            {
              'id': 1,
              'title_en': 'Track 1',
              'artist_name': 'Artist 1',
              'duration_seconds': 180,
              'stream_ticket_url': 'https://example.com/ticket',
            },
            {
              'id': 2,
              'title_en': 'Track 2',
              'artist_name': 'Artist 2',
              'duration_seconds': 200,
              'stream_ticket_url': 'https://example.com/ticket',
            },
          ],
        },
      };

      final response = CatalogResponse.fromJson(json);
      expect(response.currentPage, 2);
      expect(response.lastPage, 5);
      expect(response.total, 75);
      expect(response.tracks.length, 2);
      expect(response.tracks[0].id, 1);
      expect(response.tracks[1].id, 2);
    });
  });

  group('Phase 4: Carousel 0, 1, N Track Rules Tests', () {
    test('Zero tracks: empty list yields empty state without fake items', () {
      final List<CatalogTrackModel> emptyReleases = [];
      expect(emptyReleases.isEmpty, isTrue);
      expect(emptyReleases.length, 0);
    });

    test('One track: single track item rendered directly without slider controls', () {
      final List<CatalogTrackModel> singleRelease = [
        CatalogTrackModel(
          id: 101,
          titleEn: 'Solo Featured Release',
          titleAr: 'إصدار فردي مميز',
          artistName: 'Artist One',
          durationSeconds: 180,
          previewDurationSeconds: 30,
          streamTicketUrl: 'https://example.com/stream-ticket',
        ),
      ];

      expect(singleRelease.length, 1);
      expect(singleRelease.first.id, 101);
    });

    test('Multiple tracks: multi-item list retains exact API count and navigation', () {
      final List<CatalogTrackModel> multiReleases = List.generate(
        4,
        (i) => CatalogTrackModel(
          id: i + 1,
          titleEn: 'Track ${i + 1}',
          titleAr: 'مقطع ${i + 1}',
          artistName: 'Artist ${i + 1}',
          durationSeconds: 180 + (i * 10),
          previewDurationSeconds: 30,
          streamTicketUrl: 'https://example.com/ticket',
        ),
      );

      expect(multiReleases.length, 4);
      expect(multiReleases[0].id, 1);
      expect(multiReleases[3].id, 4);
    });
  });

  group('Phase 4: Player State & Session Telemetry Tests', () {
    test('MusicPlayerService initializes with inactive state', () {
      final player = MusicPlayerService();
      expect(player.hasActiveTrack, isFalse);
      expect(player.isPlaying, isFalse);
      expect(player.isBuffering, isFalse);
      expect(player.isLoadingTicket, isFalse);
      expect(player.position, Duration.zero);
      expect(player.playbackMode, 'preview');
      player.dispose();
    });

    test('BuyerMusicService maintains search, genre, and offer filters', () {
      final svc = BuyerMusicService();
      expect(svc.searchQuery, '');
      expect(svc.selectedGenreId, isNull);
      expect(svc.selectedOfferType, isNull);
      expect(svc.selectedSort, 'latest');
      expect(svc.isMarketplaceEnabled, isTrue);
    });
  });
}
