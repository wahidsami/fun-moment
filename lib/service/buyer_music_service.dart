import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/buyer_music_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class BuyerMusicService extends ChangeNotifier {
  bool isLoading = false;
  bool isCatalogLoading = false;
  bool isReleasesLoading = false;
  bool isAlbumsLoading = false;
  bool isFavoritesLoading = false;
  String? errorMessage;
  String? albumsError;
  bool isMarketplaceEnabled = true;

  List<CatalogTrackModel> latestReleases = [];
  List<CatalogAlbumModel> albums = [];
  List<CatalogTrackModel> catalogTracks = [];
  List<CatalogTrackModel> favoriteTracks = [];
  List<CatalogGenreModel> genres = [];

  int catalogCurrentPage = 1;
  int catalogLastPage = 1;
  int catalogTotal = 0;

  // Selected filters
  String searchQuery = '';
  int? selectedGenreId;
  String? selectedOfferType; // 'free', 'rent', 'sale' or null for all
  String selectedSort = 'latest'; // 'latest', 'popular', 'title'

  // Guest favorite storage keys
  static const String _guestFavoritesKey = 'guest_music_favorites';

  Future<String?> _getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('token');
  }

  Future<Map<String, String>> _getHeaders({bool requireAuth = false}) async {
    final token = await _getToken();
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  /// Fetch genres for filtering
  Future<void> fetchGenres() async {
    try {
      final headers = await _getHeaders();
      final url = Uri.parse('$baseApi/music/genres');
      final res = await http.get(url, headers: headers);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is List) {
          genres = (body['data'] as List)
              .whereType<Map<String, dynamic>>()
              .map((g) => CatalogGenreModel.fromJson(g))
              .toList();
          notifyListeners();
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchGenres error: $e');
    }
  }

  /// Fetch latest releases carousel items
  Future<void> fetchLatestReleases({int limit = 8}) async {
    isReleasesLoading = true;
    notifyListeners();

    try {
      final headers = await _getHeaders();
      final url = Uri.parse('$baseApi/music/catalog/latest-releases?limit=$limit');
      final res = await http.get(url, headers: headers);

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is List) {
          latestReleases = (body['data'] as List)
              .whereType<Map<String, dynamic>>()
              .map((t) => CatalogTrackModel.fromJson(t))
              .toList();
          isMarketplaceEnabled = true;
        }
      } else if (res.statusCode == 403) {
        final body = jsonDecode(res.body);
        if (body['error_code'] == 'MARKETPLACE_DISABLED') {
          isMarketplaceEnabled = false;
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchLatestReleases error: $e');
    } finally {
      isReleasesLoading = false;
      notifyListeners();
    }
  }

  /// Fetch published catalog albums
  Future<void> fetchAlbums({int limit = 10, bool isRefresh = false}) async {
    if (isRefresh) {
      albums.clear();
      albumsError = null;
    }
    isAlbumsLoading = true;
    notifyListeners();

    try {
      final headers = await _getHeaders();
      final url = Uri.parse('$baseApi/music/catalog/albums?limit=$limit');
      final res = await http.get(url, headers: headers);

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] != null) {
          final dynamic dataField = body['data'];
          final List rawList = dataField is Map<String, dynamic> && dataField['data'] is List
              ? dataField['data'] as List
              : (dataField is List ? dataField : []);

          albums = rawList
              .whereType<Map<String, dynamic>>()
              .map((a) => CatalogAlbumModel.fromJson(a))
              .toList();
          albumsError = null;
        }
      } else {
        albumsError = 'Failed to load albums (${res.statusCode})';
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchAlbums error: $e');
      albumsError = e.toString();
    } finally {
      isAlbumsLoading = false;
      notifyListeners();
    }
  }

  /// Fetch full album details including tracklist
  Future<CatalogAlbumModel?> getAlbumDetails(int albumId) async {
    try {
      final headers = await _getHeaders();
      final url = Uri.parse('$baseApi/music/catalog/albums/$albumId');
      final res = await http.get(url, headers: headers);

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          return CatalogAlbumModel.fromJson(body['data'] as Map<String, dynamic>);
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.getAlbumDetails error: $e');
    }
    return null;
  }

  /// Browse and search catalog tracks
  Future<void> fetchCatalog({
    bool isRefresh = false,
    String? query,
    int? genreId,
    String? offerType,
    String? sort,
  }) async {
    if (isRefresh) {
      catalogCurrentPage = 1;
      catalogTracks.clear();
    } else if (catalogCurrentPage > 1 && catalogCurrentPage > catalogLastPage) {
      return;
    }

    if (query != null) searchQuery = query;
    if (genreId != null) selectedGenreId = genreId == 0 ? null : genreId;
    if (offerType != null) selectedOfferType = offerType == 'all' ? null : offerType;
    if (sort != null) selectedSort = sort;

    isCatalogLoading = true;
    errorMessage = null;
    notifyListeners();

    try {
      final queryParams = <String, String>{
        'page': catalogCurrentPage.toString(),
        'per_page': '15',
        'sort': selectedSort,
      };
      if (searchQuery.trim().isNotEmpty) {
        queryParams['q'] = searchQuery.trim();
      }
      if (selectedGenreId != null && selectedGenreId! > 0) {
        queryParams['genre_id'] = selectedGenreId.toString();
      }
      if (selectedOfferType != null && selectedOfferType!.isNotEmpty) {
        queryParams['offer_type'] = selectedOfferType!;
      }

      final uri = Uri.parse('$baseApi/music/catalog').replace(queryParameters: queryParams);
      final headers = await _getHeaders();
      final res = await http.get(uri, headers: headers);

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true) {
          final catalogRes = CatalogResponse.fromJson(body);
          if (catalogCurrentPage == 1) {
            catalogTracks = catalogRes.tracks;
          } else {
            catalogTracks.addAll(catalogRes.tracks);
          }
          catalogLastPage = catalogRes.lastPage;
          catalogTotal = catalogRes.total;
          isMarketplaceEnabled = true;
        }
      } else if (res.statusCode == 403) {
        final body = jsonDecode(res.body);
        if (body['error_code'] == 'MARKETPLACE_DISABLED') {
          isMarketplaceEnabled = false;
          errorMessage = body['message'] ?? 'Music marketplace is currently disabled.';
        }
      } else {
        errorMessage = 'Failed to load music catalog.';
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchCatalog error: $e');
      errorMessage = 'Network connection error.';
    } finally {
      isCatalogLoading = false;
      notifyListeners();
    }
  }

  /// Load next page for infinite scrolling
  Future<void> loadNextPage() async {
    if (isCatalogLoading || catalogCurrentPage >= catalogLastPage) return;
    catalogCurrentPage++;
    await fetchCatalog();
  }

  /// Get single track details
  Future<CatalogTrackModel?> fetchTrackDetail(int trackId) async {
    try {
      final headers = await _getHeaders();
      final url = Uri.parse('$baseApi/music/catalog/tracks/$trackId');
      final res = await http.get(url, headers: headers);

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          return CatalogTrackModel.fromJson(body['data']);
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchTrackDetail error: $e');
    }
    return null;
  }

  /// Toggle track favorite (server-side for authenticated, local fallback for guest)
  Future<bool> toggleFavorite(CatalogTrackModel track) async {
    final token = await _getToken();
    final bool newStatus = !track.isFavorite;

    // Optimistically update in local lists
    _updateFavoriteStatus(track.id, newStatus);

    if (token == null || token.isEmpty) {
      // Guest mode: save locally in SharedPreferences
      await _toggleGuestFavorite(track.id, newStatus);
      return newStatus;
    }

    try {
      final headers = await _getHeaders(requireAuth: true);
      final url = Uri.parse('$baseApi/music/favorites/toggle');
      final res = await http.post(
        url,
        headers: headers,
        body: jsonEncode({
          'track_id': track.id,
          'action': newStatus ? 'favorite' : 'unfavorite',
        }),
      );

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true) {
          final serverIsFav = body['data']?['is_favorite'] == true;
          _updateFavoriteStatus(track.id, serverIsFav);
          return serverIsFav;
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.toggleFavorite error: $e');
      // Revert on error
      _updateFavoriteStatus(track.id, !newStatus);
    }
    return track.isFavorite;
  }

  void _updateFavoriteStatus(int trackId, bool isFav) {
    for (var t in catalogTracks) {
      if (t.id == trackId) t.isFavorite = isFav;
    }
    for (var t in latestReleases) {
      if (t.id == trackId) t.isFavorite = isFav;
    }
    if (!isFav) {
      favoriteTracks.removeWhere((t) => t.id == trackId);
    }
    notifyListeners();
  }

  Future<void> _toggleGuestFavorite(int trackId, bool isFav) async {
    final prefs = await SharedPreferences.getInstance();
    final list = prefs.getStringList(_guestFavoritesKey) ?? [];
    final idStr = trackId.toString();
    if (isFav && !list.contains(idStr)) {
      list.add(idStr);
    } else if (!isFav) {
      list.remove(idStr);
    }
    await prefs.setStringList(_guestFavoritesKey, list);
  }

  /// Fetch authenticated user's favorite tracks
  Future<void> fetchFavorites({int page = 1}) async {
    final token = await _getToken();
    if (token == null || token.isEmpty) {
      // Guest: favorites can only be viewed when logged in or empty
      favoriteTracks = [];
      notifyListeners();
      return;
    }

    isFavoritesLoading = true;
    notifyListeners();

    try {
      final headers = await _getHeaders(requireAuth: true);
      final url = Uri.parse('$baseApi/music/favorites?page=$page&per_page=20');
      final res = await http.get(url, headers: headers);

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          final list = body['data']['data'] as List? ?? [];
          favoriteTracks = list
              .whereType<Map<String, dynamic>>()
              .map((t) => CatalogTrackModel.fromJson(t))
              .toList();
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchFavorites error: $e');
    } finally {
      isFavoritesLoading = false;
      notifyListeners();
    }
  }

  /// Telemetry ingestion with server-recognized session token
  Future<bool> sendPlaybackTelemetry({
    required int trackId,
    required String playbackSessionId,
    required String eventType,
    required String playbackMode,
    required int positionSeconds,
    required int durationSeconds,
    String? sessionToken,
    String? guestSessionId,
    String? errorMessage,
  }) async {
    try {
      final headers = await _getHeaders();
      if (sessionToken != null && sessionToken.isNotEmpty) {
        headers['X-Stream-Ticket'] = sessionToken;
      }
      final url = Uri.parse('$baseApi/music/analytics/playback-event');
      final payload = {
        'track_id': trackId,
        'playback_session_id': playbackSessionId,
        'event_type': eventType,
        'playback_mode': playbackMode,
        'position_seconds': positionSeconds,
        'duration_seconds': durationSeconds,
        'idempotency_key': '$playbackSessionId-$eventType-$positionSeconds',
        if (sessionToken != null) 'session_token': sessionToken,
        if (guestSessionId != null) 'guest_session_id': guestSessionId,
        if (errorMessage != null) 'error_message': errorMessage,
      };

      final res = await http.post(
        url,
        headers: headers,
        body: jsonEncode(payload),
      );

      return res.statusCode == 200;
    } catch (e) {
      debugPrint('BuyerMusicService.sendPlaybackTelemetry error: $e');
      return false;
    }
  }

  /// Phase 5: Fetch authoritative server quote for offer checkout
  Future<Map<String, dynamic>?> getQuote(int offerId) async {
    try {
      final headers = await _getHeaders();
      final url = Uri.parse('$baseApi/music/orders/quote?offer_id=$offerId');
      final res = await http.get(url, headers: headers);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true) {
          return body as Map<String, dynamic>;
        }
      }
      return null;
    } catch (e) {
      debugPrint('BuyerMusicService.getQuote error: $e');
      return null;
    }
  }

  /// Phase 5: Fetch buyer wallet balance
  Future<double> fetchUserWalletBalance() async {
    try {
      final headers = await _getHeaders(requireAuth: true);
      final url = Uri.parse('$baseApi/user/wallet/balance');
      final res = await http.get(url, headers: headers);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] != null) {
          final bal = body['data']['balance'];
          if (bal != null) {
            return (bal is num) ? bal.toDouble() : double.tryParse(bal.toString()) ?? 0.0;
          }
        }
      }
      return 0.0;
    } catch (e) {
      debugPrint('BuyerMusicService.fetchUserWalletBalance error: $e');
      return 0.0;
    }
  }

  /// Phase 5: Submit commercial order with wallet or paytabs gateway
  Future<Map<String, dynamic>> createOrder({
    required int offerId,
    required String paymentGateway,
    String? returnUrl,
    String? idempotencyKey,
  }) async {
    try {
      final headers = await _getHeaders(requireAuth: true);
      final url = Uri.parse('$baseApi/music/orders');
      final payload = {
        'offer_id': offerId,
        'payment_gateway': paymentGateway,
        if (returnUrl != null) 'return_url': returnUrl,
        if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
      };

      final res = await http.post(
        url,
        headers: headers,
        body: jsonEncode(payload),
      );

      final body = jsonDecode(res.body);
      return body is Map<String, dynamic>
          ? body
          : {'success': false, 'message_en': 'Invalid response format'};
    } catch (e) {
      debugPrint('BuyerMusicService.createOrder error: $e');
      return {
        'success': false,
        'error_code': 'NETWORK_ERROR',
        'message_en': 'Network connection error. Please try again.',
        'message_ar': 'خطأ في الاتصال بالشبكة. يرجى المحاولة مرة أخرى.',
      };
    }
  }
}
