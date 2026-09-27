import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'app_string_service.dart';

class RtlService with ChangeNotifier {
  static const String userSelectedLangKey = 'user_selected_lang';

  /// RTL support
  String direction = 'ltr';
  String? langId;
  String langSlug = 'en_US';

  bool get isRtl => direction == 'rtl';
  bool get isArabic => langSlug.startsWith('ar');

  String currency = 'SAR';
  String currencyDirection = 'right';
  String currencyCode = 'SAR';

  bool alreadyCurrencyLoaded = false;
  bool alreadyRtlLoaded = false;

  Future<void> loadSavedLanguage(BuildContext? context) async {
    final srf = await SharedPreferences.getInstance();
    final savedLang = srf.getString(userSelectedLangKey);
    if (savedLang != null && savedLang.isNotEmpty) {
      langSlug = savedLang;
      direction = savedLang == 'ar' ? 'rtl' : 'ltr';
      if (currency == 'SAR' || currency == 'SR' || currency == 'ر.س') {
        currency = isArabic ? 'ر.س' : 'SAR';
      }
      if (context != null) {
        try {
          Provider.of<AppStringService>(context, listen: false).setLanguage(savedLang);
        } catch (_) {}
      }
      notifyListeners();
    }
  }

  Future<void> changeLanguage(
    String langCode, {
    BuildContext? context,
    AppStringService? stringService,
  }) async {
    final srf = await SharedPreferences.getInstance();
    langSlug = langCode;
    direction = langCode == 'ar' ? 'rtl' : 'ltr';

    if (currency == 'SAR' || currency == 'SR' || currency == 'ر.س') {
      currency = (langCode == 'ar') ? 'ر.س' : 'SAR';
    }

    await srf.setString(userSelectedLangKey, langCode);
    await srf.setString('slug', langCode);

    if (stringService != null) {
      stringService.setLanguage(langCode);
    } else if (context != null) {
      try {
        Provider.of<AppStringService>(context, listen: false).setLanguage(langCode);
      } catch (_) {}
    }

    notifyListeners();
  }

  fetchCurrency() async {
    if (alreadyCurrencyLoaded == false) {
      try {
        var response = await http.get(Uri.parse('$baseApi/currency'));
        if (response.statusCode == 200 || response.statusCode == 201) {
          final data = jsonDecode(response.body);
          final curr = data['currency'];
          if (curr != null) {
            String sym = curr['symbol']?.toString().trim() ?? '';
            String code = curr['code']?.toString().trim() ?? '';

            // Reject invalid, placeholder '@', or generic '$' fallbacks when SAR is intended
            if (sym.isEmpty || sym == '\$' || sym == '@' || code == 'SAR' || sym == 'SR') {
              sym = isArabic ? 'ر.س' : 'SAR';
              code = 'SAR';
            }

            currency = sym;
            currencyDirection = curr['position'] ?? (sym == 'SAR' || sym == 'ر.س' ? 'right' : 'left');
            currencyCode = code.isNotEmpty ? code : "SAR";
            alreadyCurrencyLoaded = true;
            notifyListeners();
          }
        }
      } catch (e) {
        debugPrint('fetchCurrency error: $e');
      }
    }
  }

  fetchDirection(BuildContext context) async {
    final srf = await SharedPreferences.getInstance();
    final userSelected = srf.getString(userSelectedLangKey);

    // If the user has explicitly chosen a language, preserve it and do not overwrite with backend default
    if (userSelected != null && userSelected.isNotEmpty) {
      langSlug = userSelected;
      direction = userSelected == 'ar' ? 'rtl' : 'ltr';
      Provider.of<AppStringService>(context, listen: false).setLanguage(userSelected);
      alreadyRtlLoaded = true;
      notifyListeners();
      return;
    }

    var response = await http.get(Uri.parse('$baseApi/language'));
    print(response.body);
    if (response.statusCode == 201) {
      direction = jsonDecode(response.body)['language']['direction'];
      langId = jsonDecode(response.body)['language']['id'].toString();
      langSlug = jsonDecode(response.body)['language']['slug'].toString();
      var now = DateTime.now();

      if (!srf.containsKey('langId')) {
        print('Translating string for the first time');
        srf.setString('langId', langId!);
        srf.setString('slug', langSlug);
        print('slugid$langSlug');
        srf.setString('update_date', now.toIso8601String());
        await Provider.of<AppStringService>(context, listen: false)
            .fetchTranslatedStrings(context);
      } else if (srf.getString('langId') != langId) {
        srf.setString('update_date', now.toIso8601String());
        print('Updating translated Strings');
        srf.setString('langId', langId!);
        srf.setString('slug', langSlug);
        await Provider.of<AppStringService>(context, listen: false)
            .fetchTranslatedStrings(context);
      } else if (now
              .difference(DateTime.parse(
                  srf.getString('update_date') ?? now.toIso8601String()))
              .inMinutes >
          7200) {
        srf.setString('update_date', now.toIso8601String());
        await Provider.of<AppStringService>(context, listen: false)
            .fetchTranslatedStrings(context);
      } else {
        print('Loading translations from local');
        await Provider.of<AppStringService>(context, listen: false)
            .fetchTranslatedStrings(context, doNotLoad: false);
      }

      alreadyRtlLoaded = true;
      notifyListeners();
    } else {
      print('failed loading language direction');
      print(response.body);
    }
  }
}
