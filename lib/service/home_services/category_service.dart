import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';

class CategoryService with ChangeNotifier {
  var categories;
  var categoriesDropdownList = [];
  bool _isFetching = false;

  bool get isFetching => _isFetching;

  Future<void> fetchCategory({bool isRefresh = false}) async {
    if (_isFetching) return;
    if (categories != null && categories != 'error' && !isRefresh) {
      return; // Already successfully loaded
    }

    _isFetching = true;
    notifyListeners();

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
            notifyListeners();
          }
        }
      } catch (e) {
        debugPrint('CategoryService cache load non-fatal: $e');
      }
    }

    try {
      // 3-attempt resilient fetch to overcome port 80 cold-start SYN drop
      for (int attempt = 1; attempt <= 3; attempt++) {
        final success = await _fetchFromApi();
        if (success) break;
        if (attempt < 3) {
          await Future.delayed(Duration(milliseconds: 500 * attempt));
        }
      }
    } finally {
      _isFetching = false;
      notifyListeners();
    }
  }

  Future<bool> _fetchFromApi() async {
    try {
      final response = await http
          .get(Uri.parse('$baseApi/category'))
          .timeout(const Duration(seconds: 12));

      if (response.statusCode == 200 || response.statusCode == 201) {
        final decoded = jsonDecode(response.body);
        final model = CategoryModel.fromJson(decoded);
        categories = model;
        categoriesDropdownList = model.category;
        notifyListeners();

        // Persist to local cache for instant cold start
        try {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('cached_categories_json', response.body);
        } catch (_) {}

        return true;
      } else {
        if (categories == null) {
          categories = 'error';
          notifyListeners();
        }
        return false;
      }
    } catch (e) {
      debugPrint('CategoryService._fetchFromApi error: $e');
      if (categories == null) {
        categories = 'error';
        notifyListeners();
      }
      return false;
    }
  }
}
