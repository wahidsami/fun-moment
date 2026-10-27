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
  List<MusicPlaylistModel> playlists = [];
  bool isPlaylistsLoading = false;
  String? playlistsError;
  MusicPlaylistModel? currentPlaylist;
  bool isCurrentPlaylistLoading = false;

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
  static const String _localPlaylistsKey = 'local_music_playlists_v1';
  static int _localPlaylistCounter = 0;

  static int generateLocalPlaylistId() {
    _localPlaylistCounter++;
    return DateTime.now().millisecondsSinceEpoch * 100 + (_localPlaylistCounter % 100);
  }

  Future<List<MusicPlaylistModel>> _loadLocalPlaylists() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_localPlaylistsKey);
      if (raw != null && raw.isNotEmpty) {
        final decoded = jsonDecode(raw);
        if (decoded is List) {
          return decoded
              .whereType<Map<String, dynamic>>()
              .map((p) => MusicPlaylistModel.fromJson(p))
              .toList();
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService._loadLocalPlaylists error: $e');
    }
    return [];
  }

  Future<void> _saveLocalPlaylists(List<MusicPlaylistModel> list) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final encoded = jsonEncode(list.map((p) => p.toJson()).toList());
      await prefs.setString(_localPlaylistsKey, encoded);
    } catch (e) {
      debugPrint('BuyerMusicService._saveLocalPlaylists error: $e');
    }
  }

  CatalogTrackModel? findTrackById(int trackId) {
    for (final t in catalogTracks) {
      if (t.id == trackId) return t;
    }
    for (final t in latestReleases) {
      if (t.id == trackId) return t;
    }
    for (final p in playlists) {
      for (final t in p.tracks) {
        if (t.id == trackId) return t;
      }
    }
    if (currentPlaylist != null) {
      for (final t in currentPlaylist!.tracks) {
        if (t.id == trackId) return t;
      }
    }
    return null;
  }

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

  /*--------------------------------------------------------------------------
   * Customer Multi-Playlist API Methods
   *--------------------------------------------------------------------------*/

  /// Fetch all playlists owned by the customer (loads locally first, syncs if authenticated)
  Future<List<MusicPlaylistModel>> fetchPlaylists() async {
    isPlaylistsLoading = true;
    playlistsError = null;
    notifyListeners();

    // 1. Instant load from local storage
    final localPlaylists = await _loadLocalPlaylists();
    if (localPlaylists.isNotEmpty) {
      playlists = List.from(localPlaylists);
      notifyListeners();
    }

    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists');
        final res = await http.get(url, headers: headers);

        if (res.statusCode == 200) {
          final body = jsonDecode(res.body);
          if (body['success'] == true && body['data'] is List) {
            final remotePlaylists = (body['data'] as List)
                .whereType<Map<String, dynamic>>()
                .map((p) => MusicPlaylistModel.fromJson(p))
                .toList();

            final remoteIds = remotePlaylists.map((p) => p.id).toSet();
            final merged = List<MusicPlaylistModel>.from(remotePlaylists);
            for (final local in localPlaylists) {
              if (!remoteIds.contains(local.id)) {
                merged.add(local);
              }
            }
            playlists = merged;
            await _saveLocalPlaylists(playlists);
          }
        } else if (res.statusCode == 401) {
          if (playlists.isEmpty) {
            playlistsError = 'UNAUTHENTICATED';
          }
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchPlaylists error: $e');
      if (playlists.isEmpty) {
        playlistsError = 'Connection error loading playlists.';
      }
    } finally {
      isPlaylistsLoading = false;
      notifyListeners();
    }
    return playlists;
  }

  /// Create a new playlist (syncs with remote when available, always persists locally)
  Future<MusicPlaylistModel?> createPlaylist(String name, {String? description}) async {
    MusicPlaylistModel? createdModel;
    final trimmedName = name.trim();
    final trimmedDesc = (description != null && description.trim().isNotEmpty) ? description.trim() : null;

    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists');
        final payload = {
          'name': trimmedName,
          if (trimmedDesc != null) 'description': trimmedDesc,
        };

        final res = await http.post(url, headers: headers, body: jsonEncode(payload));
        if (res.statusCode == 201 || res.statusCode == 200) {
          final body = jsonDecode(res.body);
          if (body['success'] == true && body['data'] is Map<String, dynamic>) {
            createdModel = MusicPlaylistModel.fromJson(body['data']);
          }
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.createPlaylist remote error: $e');
    }

    // Local fallback: create playlist model if remote unavailable or unauthenticated
    if (createdModel == null) {
      final now = DateTime.now();
      createdModel = MusicPlaylistModel(
        id: generateLocalPlaylistId(),
        name: trimmedName,
        description: trimmedDesc,
        coverImageUrl: null,
        isPublic: false,
        tracksCount: 0,
        totalDurationSeconds: 0,
        createdAt: now.toIso8601String(),
        updatedAt: now.toIso8601String(),
        tracks: const [],
      );
    }

    playlists.insert(0, createdModel);
    await _saveLocalPlaylists(playlists);
    notifyListeners();
    return createdModel;
  }

  /// Fetch playlist details and ordered tracks
  Future<MusicPlaylistModel?> fetchPlaylistDetails(int playlistId) async {
    isCurrentPlaylistLoading = true;
    notifyListeners();

    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists/$playlistId');
        final res = await http.get(url, headers: headers);

        if (res.statusCode == 200) {
          final body = jsonDecode(res.body);
          if (body['success'] == true && body['data'] is Map<String, dynamic>) {
            currentPlaylist = MusicPlaylistModel.fromJson(body['data']);
            final idx = playlists.indexWhere((p) => p.id == playlistId);
            if (idx != -1 && currentPlaylist != null) {
              playlists[idx] = currentPlaylist!;
              await _saveLocalPlaylists(playlists);
            }
            notifyListeners();
            return currentPlaylist;
          }
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchPlaylistDetails error: $e');
    } finally {
      if (currentPlaylist == null || currentPlaylist!.id != playlistId) {
        final idx = playlists.indexWhere((p) => p.id == playlistId);
        if (idx != -1) {
          currentPlaylist = playlists[idx];
        }
      }
      isCurrentPlaylistLoading = false;
      notifyListeners();
    }
    return currentPlaylist;
  }

  /// Rename or update playlist description
  Future<bool> updatePlaylist(int playlistId, {String? name, String? description}) async {
    final trimmedName = name?.trim();
    final trimmedDesc = description?.trim();

    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists/$playlistId');
        final payload = <String, dynamic>{};
        if (trimmedName != null) payload['name'] = trimmedName;
        if (trimmedDesc != null) payload['description'] = trimmedDesc;

        await http.put(url, headers: headers, body: jsonEncode(payload));
      }
    } catch (e) {
      debugPrint('BuyerMusicService.updatePlaylist error: $e');
    }

    final idx = playlists.indexWhere((p) => p.id == playlistId);
    if (idx != -1) {
      final existing = playlists[idx];
      playlists[idx] = MusicPlaylistModel(
        id: existing.id,
        name: trimmedName ?? existing.name,
        description: trimmedDesc ?? existing.description,
        coverImageUrl: existing.coverImageUrl,
        isPublic: existing.isPublic,
        tracksCount: existing.tracksCount,
        totalDurationSeconds: existing.totalDurationSeconds,
        createdAt: existing.createdAt,
        updatedAt: DateTime.now().toIso8601String(),
        tracks: existing.tracks,
      );
    }
    if (currentPlaylist != null && currentPlaylist!.id == playlistId) {
      currentPlaylist = MusicPlaylistModel(
        id: currentPlaylist!.id,
        name: trimmedName ?? currentPlaylist!.name,
        description: trimmedDesc ?? currentPlaylist!.description,
        coverImageUrl: currentPlaylist!.coverImageUrl,
        isPublic: currentPlaylist!.isPublic,
        tracksCount: currentPlaylist!.tracksCount,
        totalDurationSeconds: currentPlaylist!.totalDurationSeconds,
        createdAt: currentPlaylist!.createdAt,
        updatedAt: DateTime.now().toIso8601String(),
        tracks: currentPlaylist!.tracks,
      );
    }
    await _saveLocalPlaylists(playlists);
    notifyListeners();
    return true;
  }

  /// Delete a playlist
  Future<bool> deletePlaylist(int playlistId) async {
    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists/$playlistId');
        await http.delete(url, headers: headers);
      }
    } catch (e) {
      debugPrint('BuyerMusicService.deletePlaylist error: $e');
    }

    playlists.removeWhere((p) => p.id == playlistId);
    if (currentPlaylist != null && currentPlaylist!.id == playlistId) {
      currentPlaylist = null;
    }
    await _saveLocalPlaylists(playlists);
    notifyListeners();
    return true;
  }

  /// Add track to a playlist
  Future<Map<String, dynamic>> addTrackToPlaylist(int playlistId, int trackId) async {
    final idx = playlists.indexWhere((p) => p.id == playlistId);
    if (idx != -1) {
      final existingPlaylist = playlists[idx];
      if (existingPlaylist.tracks.any((t) => t.id == trackId)) {
        return {'success': false, 'message': 'Track is already in this playlist.'};
      }
    }

    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists/$playlistId/tracks');
        final res = await http.post(url, headers: headers, body: jsonEncode({'track_id': trackId}));
        if (res.statusCode == 200 || res.statusCode == 201) {
          if (currentPlaylist != null && currentPlaylist!.id == playlistId) {
            await fetchPlaylistDetails(playlistId);
          }
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.addTrackToPlaylist remote error: $e');
    }

    // Local sync
    if (idx != -1) {
      final existing = playlists[idx];
      final trackObj = findTrackById(trackId) ??
          CatalogTrackModel(
            id: trackId,
            titleEn: 'Track #$trackId',
            titleAr: 'مسار رقم $trackId',
            artistName: 'FUN MOMENT',
            durationSeconds: 180,
            previewDurationSeconds: 30,
            streamTicketUrl: '',
          );

      final trackWithPos = trackObj.copyWith(
        playlistPosition: existing.tracks.length + 1,
      );

      final updatedTracks = List<CatalogTrackModel>.from(existing.tracks)..add(trackWithPos);
      final coverUrl = existing.coverImageUrl ?? trackObj.coverArtUrl;

      final updatedPlaylist = MusicPlaylistModel(
        id: existing.id,
        name: existing.name,
        description: existing.description,
        coverImageUrl: coverUrl,
        isPublic: existing.isPublic,
        tracksCount: updatedTracks.length,
        totalDurationSeconds: updatedTracks.fold<int>(0, (sum, t) => sum + t.durationSeconds),
        createdAt: existing.createdAt,
        updatedAt: DateTime.now().toIso8601String(),
        tracks: updatedTracks,
      );

      playlists[idx] = updatedPlaylist;
      if (currentPlaylist != null && currentPlaylist!.id == playlistId) {
        currentPlaylist = updatedPlaylist;
      }
      await _saveLocalPlaylists(playlists);
      notifyListeners();
    }

    return {'success': true, 'message': 'Track added to playlist.'};
  }

  /// Remove track from a playlist
  Future<bool> removeTrackFromPlaylist(int playlistId, int trackId) async {
    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists/$playlistId/tracks/$trackId');
        await http.delete(url, headers: headers);
      }
    } catch (e) {
      debugPrint('BuyerMusicService.removeTrackFromPlaylist remote error: $e');
    }

    final idx = playlists.indexWhere((p) => p.id == playlistId);
    if (idx != -1) {
      final existing = playlists[idx];
      final updatedTracks = existing.tracks.where((t) => t.id != trackId).toList();

      final updatedPlaylist = MusicPlaylistModel(
        id: existing.id,
        name: existing.name,
        description: existing.description,
        coverImageUrl: updatedTracks.isNotEmpty ? existing.coverImageUrl : null,
        isPublic: existing.isPublic,
        tracksCount: updatedTracks.length,
        totalDurationSeconds: updatedTracks.fold<int>(0, (sum, t) => sum + t.durationSeconds),
        createdAt: existing.createdAt,
        updatedAt: DateTime.now().toIso8601String(),
        tracks: updatedTracks,
      );

      playlists[idx] = updatedPlaylist;
      if (currentPlaylist != null && currentPlaylist!.id == playlistId) {
        currentPlaylist = updatedPlaylist;
      }
      await _saveLocalPlaylists(playlists);
      notifyListeners();
    }
    return true;
  }

  /// Reorder tracks in a playlist
  Future<bool> reorderPlaylist(int playlistId, List<int> orderedTrackIds) async {
    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists/$playlistId/reorder');
        await http.post(url, headers: headers, body: jsonEncode({'track_ids': orderedTrackIds}));
      }
    } catch (e) {
      debugPrint('BuyerMusicService.reorderPlaylist remote error: $e');
    }

    final idx = playlists.indexWhere((p) => p.id == playlistId);
    if (idx != -1) {
      final existing = playlists[idx];
      final trackMap = {for (final t in existing.tracks) t.id: t};
      final reordered = <CatalogTrackModel>[];
      for (int i = 0; i < orderedTrackIds.length; i++) {
        final id = orderedTrackIds[i];
        if (trackMap.containsKey(id)) {
          reordered.add(trackMap[id]!.copyWith(playlistPosition: i + 1));
        }
      }

      final updatedPlaylist = MusicPlaylistModel(
        id: existing.id,
        name: existing.name,
        description: existing.description,
        coverImageUrl: existing.coverImageUrl,
        isPublic: existing.isPublic,
        tracksCount: reordered.length,
        totalDurationSeconds: reordered.fold<int>(0, (sum, t) => sum + t.durationSeconds),
        createdAt: existing.createdAt,
        updatedAt: DateTime.now().toIso8601String(),
        tracks: reordered,
      );

      playlists[idx] = updatedPlaylist;
      if (currentPlaylist != null && currentPlaylist!.id == playlistId) {
        currentPlaylist = updatedPlaylist;
      }
      await _saveLocalPlaylists(playlists);
      notifyListeners();
    }
    return true;
  }

  /// Fetch user playlists with `contains_track` flag for a specific track
  Future<List<MusicPlaylistModel>> fetchPlaylistsForTrack(int trackId) async {
    try {
      final token = await _getToken();
      if (token != null && token.isNotEmpty) {
        final headers = await _getHeaders(requireAuth: true);
        final url = Uri.parse('$baseApi/music/playlists-for-track/$trackId');
        final res = await http.get(url, headers: headers);

        if (res.statusCode == 200) {
          final body = jsonDecode(res.body);
          if (body['success'] == true && body['data'] is List) {
            return (body['data'] as List)
                .whereType<Map<String, dynamic>>()
                .map((p) => MusicPlaylistModel.fromJson(p))
                .toList();
          }
        }
      }
    } catch (e) {
      debugPrint('BuyerMusicService.fetchPlaylistsForTrack error: $e');
    }

    // Local fallback: compute containsTrack from cached playlists
    if (playlists.isEmpty) {
      await fetchPlaylists();
    }
    return playlists.map((p) {
      final hasTrack = p.tracks.any((t) => t.id == trackId);
      return MusicPlaylistModel(
        id: p.id,
        name: p.name,
        description: p.description,
        coverImageUrl: p.coverImageUrl,
        isPublic: p.isPublic,
        tracksCount: p.tracksCount,
        totalDurationSeconds: p.totalDurationSeconds,
        createdAt: p.createdAt,
        updatedAt: p.updatedAt,
        tracks: p.tracks,
        containsTrack: hasTrack,
      );
    }).toList();
  }
}
