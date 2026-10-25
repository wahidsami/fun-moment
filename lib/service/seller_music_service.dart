import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/seller_music_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class SellerMusicService extends ChangeNotifier {
  bool isLoading = false;
  bool isSubmitting = false;
  String? errorMessage;

  List<SellerMusicTrack> tracks = [];
  List<SellerMusicGenre> genres = [];
  SellerMusicConfig? config;

  int currentPage = 1;
  int totalPages = 1;
  int totalTracks = 0;

  Future<String?> _getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('token');
  }

  Future<SellerMusicConfig?> fetchConfig() async {
    final token = await _getToken();
    try {
      final url = Uri.parse('$baseApi/seller/music/config');
      final res = await http.get(url, headers: {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          config = SellerMusicConfig.fromJson(body['data']);
          notifyListeners();
          return config;
        }
      }
    } catch (e) {
      debugPrint('SellerMusicService.fetchConfig error: $e');
    }
    return null;
  }

  Future<List<SellerMusicGenre>> fetchGenres() async {
    final token = await _getToken();
    try {
      final url = Uri.parse('$baseApi/seller/music/genres');
      final res = await http.get(url, headers: {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is List) {
          genres = (body['data'] as List)
              .map((g) => SellerMusicGenre.fromJson(g as Map<String, dynamic>))
              .toList();
          notifyListeners();
          return genres;
        }
      }
    } catch (e) {
      debugPrint('SellerMusicService.fetchGenres error: $e');
    }
    return [];
  }

  Future<bool> fetchTracks({
    String? status,
    String? mediaStatus,
    String? search,
    int page = 1,
    bool refresh = false,
  }) async {
    final token = await _getToken();
    if (token == null) {
      errorMessage = 'Authentication token missing';
      notifyListeners();
      return false;
    }

    if (refresh) {
      currentPage = 1;
      tracks.clear();
    }

    isLoading = true;
    errorMessage = null;
    notifyListeners();

    try {
      final queryParams = <String, String>{
        'page': page.toString(),
        'per_page': '15',
        if (status != null && status.isNotEmpty && status != 'all') 'status': status,
        if (mediaStatus != null && mediaStatus.isNotEmpty && mediaStatus != 'all') 'media_status': mediaStatus,
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      };

      final uri = Uri.parse('$baseApi/seller/music/tracks').replace(queryParameters: queryParams);
      final res = await http.get(uri, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      isLoading = false;

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          final data = body['data'];
          final list = (data['data'] as List<dynamic>?)
                  ?.map((t) => SellerMusicTrack.fromJson(t as Map<String, dynamic>))
                  .toList() ??
              [];

          currentPage = data['current_page'] is int ? data['current_page'] : 1;
          totalPages = data['last_page'] is int ? data['last_page'] : 1;
          totalTracks = data['total'] is int ? data['total'] : list.length;

          if (page == 1) {
            tracks = list;
          } else {
            tracks.addAll(list);
          }
          notifyListeners();
          return true;
        }
      } else {
        final body = jsonDecode(res.body);
        errorMessage = body['message_en'] ?? body['message'] ?? 'Failed to load tracks (${res.statusCode})';
      }
    } catch (e) {
      isLoading = false;
      errorMessage = 'Network error: $e';
      debugPrint('SellerMusicService.fetchTracks error: $e');
    }

    notifyListeners();
    return false;
  }

  Future<SellerMusicTrack?> fetchTrackDetail(int trackId) async {
    final token = await _getToken();
    if (token == null) return null;

    try {
      final uri = Uri.parse('$baseApi/seller/music/tracks/$trackId');
      final res = await http.get(uri, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          return SellerMusicTrack.fromJson(body['data']);
        }
      }
    } catch (e) {
      debugPrint('SellerMusicService.fetchTrackDetail error: $e');
    }
    return null;
  }

  Future<Map<String, dynamic>> submitTrack({
    required String title,
    String? artistName,
    String? description,
    required int genreId,
    List<String> secondaryGenres = const [],
    required File audioFile,
    File? coverImage,
    required String rightsDeclaration,
    String? rightsNotes,
    required bool agreeRightsDeclaration,
    required List<SellerMusicOffer> offers,
  }) async {
    final token = await _getToken();
    if (token == null) {
      return {'success': false, 'message': 'Authentication required'};
    }

    isSubmitting = true;
    notifyListeners();

    try {
      final uri = Uri.parse('$baseApi/seller/music/tracks');
      final request = http.MultipartRequest('POST', uri);

      request.headers['Accept'] = 'application/json';
      request.headers['Authorization'] = 'Bearer $token';

      request.fields['title'] = title;
      if (artistName != null && artistName.isNotEmpty) {
        request.fields['artist_name'] = artistName;
      }
      if (description != null && description.isNotEmpty) {
        request.fields['description'] = description;
      }
      request.fields['genre_id'] = genreId.toString();

      for (int i = 0; i < secondaryGenres.length; i++) {
        request.fields['secondary_genres[$i]'] = secondaryGenres[i];
      }

      request.fields['rights_declaration'] = rightsDeclaration;
      if (rightsNotes != null && rightsNotes.isNotEmpty) {
        request.fields['rights_notes'] = rightsNotes;
      }
      request.fields['agree_rights_declaration'] = agreeRightsDeclaration ? '1' : '0';

      // Offers serialization
      for (int i = 0; i < offers.length; i++) {
        request.fields['offers[$i][offer_type]'] = offers[i].offerType;
        request.fields['offers[$i][price]'] = offers[i].price.toString();
        request.fields['offers[$i][currency]'] = offers[i].currency;
        if (offers[i].rentalDurationDays != null) {
          request.fields['offers[$i][rental_duration_days]'] = offers[i].rentalDurationDays.toString();
        }
        request.fields['offers[$i][is_active]'] = offers[i].isActive ? '1' : '0';
      }

      // Audio file
      request.files.add(await http.MultipartFile.fromPath('audio_file', audioFile.path));

      // Cover image file if provided
      if (coverImage != null) {
        request.files.add(await http.MultipartFile.fromPath('cover_image', coverImage.path));
      }

      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      isSubmitting = false;
      notifyListeners();

      final body = jsonDecode(response.body);
      if (response.statusCode == 200 || response.statusCode == 201) {
        return {
          'success': true,
          'message': body['message_en'] ?? 'Track submitted successfully',
          'track': body['data'],
        };
      } else {
        String msg = body['message_en'] ?? body['message'] ?? 'Submission failed (${response.statusCode})';
        if (body['errors'] is Map) {
          final errMap = body['errors'] as Map<String, dynamic>;
          final firstKey = errMap.keys.first;
          if (errMap[firstKey] is List && (errMap[firstKey] as List).isNotEmpty) {
            msg = (errMap[firstKey] as List).first.toString();
          }
        }
        return {'success': false, 'message': msg};
      }
    } catch (e) {
      isSubmitting = false;
      notifyListeners();
      return {'success': false, 'message': 'Upload failed: $e'};
    }
  }

  Future<bool> retryProcessing(int trackId) async {
    final token = await _getToken();
    if (token == null) return false;

    try {
      final uri = Uri.parse('$baseApi/seller/music/tracks/$trackId/retry');
      final res = await http.post(uri, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true) {
          // Update track in local list
          final index = tracks.indexWhere((t) => t.id == trackId);
          if (index != -1) {
            fetchTracks(page: currentPage, refresh: false);
          }
          return true;
        }
      }
    } catch (e) {
      debugPrint('SellerMusicService.retryProcessing error: $e');
    }
    return false;
  }

  Future<bool> deleteTrack(int trackId) async {
    final token = await _getToken();
    if (token == null) return false;

    try {
      final uri = Uri.parse('$baseApi/seller/music/tracks/$trackId');
      final res = await http.delete(uri, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200) {
        tracks.removeWhere((t) => t.id == trackId);
        totalTracks = tracks.length;
        notifyListeners();
        return true;
      }
    } catch (e) {
      debugPrint('SellerMusicService.deleteTrack error: $e');
    }
    return false;
  }
}
