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

  fetchTranslatedStrings(BuildContext context, {bool doNotLoad = false}) async {
    //if already loaded. no need to load again

    var connection = await checkConnection();
    if (connection) {
      //internet connection is on
      SharedPreferences prefs = await SharedPreferences.getInstance();
      prefs.getString('token');
      if (doNotLoad) {
        final strings = prefs.getString('translated_string');
        tStrings = jsonDecode(strings ?? 'null');
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
          //TODO : FIXXXX HEREEEEEEEEEEEEEEEEEEEEEEEEEE
          print('tstring :${prefs.getString('slug')}');

          tStrings = prefs.getString('slug') == 'ar'
              ? translations
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
  //TODO :HERE WE CAN GET THE Language and give the translation directly from the application

  getString(String staticString) {
    try {
      if (tStrings.containsKey(staticString)) {
        return tStrings[staticString];
      } else {
        return staticString;
      }
    } catch (e) {
      debugPrint(e.toString());
      return staticString;
    }
  }
}
