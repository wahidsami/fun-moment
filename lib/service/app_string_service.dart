import 'dart:convert';
import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/app_strings.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;

class AppStringService with ChangeNotifier {
  bool isloading = false;

  Map tStrings = {};

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  void setLanguage(String langCode) async {
    if (langCode == 'ar') {
      tStrings = Map<String, dynamic>.from(translations);
    } else {
      tStrings = Map<String, dynamic>.from(appStrings);
    }
    try {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      await prefs.setString('translated_string', jsonEncode(tStrings));
    } catch (_) {}
    notifyListeners();
  }

  Future<void> initStrings() async {
    try {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      final selected = prefs.getString('user_selected_lang') ?? prefs.getString('slug');
      if (selected == 'ar') {
        tStrings = Map<String, dynamic>.from(translations);
      } else {
        final cached = prefs.getString('translated_string');
        if (cached != null) {
          try {
            tStrings = jsonDecode(cached);
          } catch (_) {
            tStrings = Map<String, dynamic>.from(appStrings);
          }
        } else {
          tStrings = Map<String, dynamic>.from(appStrings);
        }
      }
      notifyListeners();
    } catch (_) {}
  }

  fetchTranslatedStrings(BuildContext context, {bool doNotLoad = false}) async {
    //if already loaded. no need to load again

    var connection = await checkConnection();
    if (connection) {
      //internet connection is on
      SharedPreferences prefs = await SharedPreferences.getInstance();
      prefs.getString('token');
      if (doNotLoad) {
        final strings = prefs.getString('translated_string');
        tStrings = jsonDecode(strings ?? 'null') ?? Map<String, dynamic>.from(appStrings);
        return;
      }

      final userSelected = prefs.getString('user_selected_lang');
      if (userSelected == 'ar') {
        tStrings = Map<String, dynamic>.from(translations);
        prefs.setString('translated_string', jsonEncode(tStrings));
        notifyListeners();
        return;
      }

      setLoadingTrue();

      var data = jsonEncode({
        "strings": jsonEncode(appStrings),
      });

      var header = {
        "Content-Type": "application/json",
      };

      log(jsonEncode(appStrings).toString());
      var response = await http.post(Uri.parse('$baseApi/translate-string'),
          headers: header, body: data);

      try {
        if (response.statusCode == 201) {
          debugPrint(response.body.toString());
          print('tstring :${prefs.getString('slug')}');

          final activeSlug = prefs.getString('user_selected_lang') ?? prefs.getString('slug');
          tStrings = activeSlug == 'ar'
              ? Map<String, dynamic>.from(translations)
              : jsonDecode(response.body)['strings'];
          print('tstring :$tStrings');
          prefs.setString('translated_string', jsonEncode(tStrings));
          notifyListeners();
        } else {
          print('error fetching translations ' + response.body);
        }
      } catch (e) {
        debugPrint(e.toString());
      }
    }
  }

  String getString(String staticString) {
    try {
      if (tStrings.containsKey(staticString) && tStrings[staticString] != null) {
        return tStrings[staticString].toString();
      }
      return staticString;
    } catch (e) {
      return staticString;
    }
  }
}
