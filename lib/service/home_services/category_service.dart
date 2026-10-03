import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';

class CategoryService with ChangeNotifier {
  var categories;
  var categoriesDropdownList = [];
  bool _isFetching = false;

  bool get isFetching => _isFetching;

  void _safeNotifyListeners() {
    try {
      final binding = WidgetsBinding.instance;
      if (binding.schedulerPhase == SchedulerPhase.persistentCallbacks) {
        binding.addPostFrameCallback((_) {
          notifyListeners();
        });
        return;
      }
    } catch (_) {}
    notifyListeners();
  }

  void resetState() {
    categories = null;
    categoriesDropdownList = [];
    _isFetching = false;
    _safeNotifyListeners();
  }

  Future<void> fetchCategory({bool isRefresh = false, http.Client? client}) async {
    if (_isFetching) return;
    if (categories != null && categories != 'error' && !isRefresh) {
      return; // Already successfully loaded in memory
    }

    _isFetching = true;
    _safeNotifyListeners();

    // Fast-path: on cold launch, immediately restore from local cache if memory state is empty
    if (categories == null || categories == 'error') {
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString('cached_categories_json');
        if (cached != null && cached.isNotEmpty) {
          final decoded = jsonDecode(cached);
          final model = CategoryModel.fromJson(decoded);
          if (model.category.isNotEmpty) {
            categories = model;
            categoriesDropdownList = model.category;
            debugPrint(
                '[CategoryService] Fast-path: restored ${model.category.length} categories from SharedPreferences cache');
            _safeNotifyListeners();
          }
        }
      } catch (e) {
        debugPrint('[CategoryService] Cache load non-fatal: $e');
      }
    }

    final httpClient = client ?? http.Client();
    final bool disposeClient = client == null;

    try {
      // Bounded 2-attempt fetch (max ~16s total) to overcome socket congestion without 37s stalls
      for (int attempt = 1; attempt <= 2; attempt++) {
        final success = await _fetchFromApi(httpClient, attempt: attempt);
        if (success) break;
        if (attempt < 2) {
          await Future.delayed(const Duration(milliseconds: 500));
        }
      }

      // If all network attempts failed and there was no cached data, transition to error state
      if (categories == null) {
        debugPrint(
            '[CategoryService] Network failed with no cache present; transitioning to error state');
        categories = 'error';
        _safeNotifyListeners();
      } else {
        debugPrint(
            '[CategoryService] Terminal state reached with active categories (cached or remote)');
      }
    } finally {
      if (disposeClient) {
        httpClient.close();
      }
      _isFetching = false;
      _safeNotifyListeners();
    }
  }

  Future<bool> _fetchFromApi(http.Client client, {int attempt = 1}) async {
    final sw = Stopwatch()..start();
    final uri = Uri.parse('$baseApi/category');
    debugPrint('[CategoryService] GET $uri (attempt $attempt) started');

    try {
      final response = await client
          .get(uri)
          .timeout(const Duration(seconds: 10));
      sw.stop();
      debugPrint(
          '[CategoryService] GET $uri (attempt $attempt) returned ${response.statusCode} in ${sw.elapsedMilliseconds}ms');

      if (response.statusCode == 200 || response.statusCode == 201) {
        final decoded = jsonDecode(response.body);
        final model = CategoryModel.fromJson(decoded);
        categories = model;
        categoriesDropdownList = model.category;
        _safeNotifyListeners();

        // Persist fresh data to local cache
        try {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('cached_categories_json', response.body);
          debugPrint(
              '[CategoryService] Successfully cached fresh categories JSON');
        } catch (_) {}

        return true;
      } else {
        debugPrint(
            '[CategoryService] GET $uri (attempt $attempt) non-200 status: ${response.statusCode}');
        return false;
      }
    } catch (e) {
      sw.stop();
      debugPrint(
          '[CategoryService] GET $uri (attempt $attempt) error after ${sw.elapsedMilliseconds}ms: $e');
      return false;
    }
  }
}
