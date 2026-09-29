import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class CategoryService with ChangeNotifier {
  var categories;

  var categoriesDropdownList = [];

  fetchCategory({bool isRefresh = false}) async {
    if (categories == null || isRefresh) {
      var connection = await checkConnection();
      if (connection) {
        try {
          var response = await http
              .get(Uri.parse('$baseApi/category'))
              .timeout(const Duration(seconds: 8));

          if (response.statusCode == 200 || response.statusCode == 201) {
            categories = CategoryModel.fromJson(jsonDecode(response.body));

            categoriesDropdownList = categories.category;

            notifyListeners();
          } else {
            // Something went wrong
            if (categories == null) {
              categories = 'error';
              notifyListeners();
            }
          }
        } catch (e) {
          debugPrint('CategoryService.fetchCategory error: $e');
          if (categories == null) {
            categories = 'error';
            notifyListeners();
          }
        }
      }
    } else {
      // already loaded from api
    }
  }
}
