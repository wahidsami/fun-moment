import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/model/slider_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

import 'package:shared_preferences/shared_preferences.dart';

class SliderService with ChangeNotifier {
  List<Map> sliderDetailsList = [];
  List sliderImageList = [];

  bool isLoading = false;
  bool isLoaded = false;
  bool hasError = false;

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
    sliderDetailsList = [];
    sliderImageList = [];
    isLoading = false;
    isLoaded = false;
    hasError = false;
    _safeNotifyListeners();
  }

  Future<void> loadSlider({bool isRefresh = false, http.Client? client, String? language}) async {
    // Once loaded, do not refetch unless explicitly requested via isRefresh
    if (isLoaded && !isRefresh) return;
    if (isLoading) return;

    isLoading = true;
    hasError = false;
    _safeNotifyListeners();

    String lang = language ?? 'en';
    try {
      final srf = await SharedPreferences.getInstance();
      final savedLang = srf.getString('user_selected_lang') ?? srf.getString('slug');
      if (savedLang != null && savedLang.isNotEmpty) {
        lang = savedLang;
      }
    } catch (_) {}
    final isArabic = lang.startsWith('ar');

    final sw = Stopwatch()..start();
    final uri = Uri.parse('$baseApi/slider?lang=${isArabic ? 'ar' : 'en'}');
    debugPrint('[SliderService] GET $uri started (isRefresh=$isRefresh, lang=$lang)');

    final httpClient = client ?? http.Client();
    final bool disposeClient = client == null;

    try {
      final response = await httpClient
          .get(uri, headers: {
            'Accept': 'application/json',
            'X-Language': isArabic ? 'ar' : 'en',
          })
          .timeout(const Duration(seconds: 10));
      sw.stop();
      debugPrint(
          '[SliderService] GET $uri returned ${response.statusCode} in ${sw.elapsedMilliseconds}ms');

      if (response.statusCode == 201 || response.statusCode == 200) {
        var data = SliderModel.fromJson(jsonDecode(response.body));

        final newDetails = <Map>[];
        final newImages = [];

        final imageMap = <int, String>{};
        for (final img in data.imageUrl) {
          if (img.imageId != null && img.imgUrl != null && img.imgUrl!.trim().isNotEmpty) {
            imageMap[img.imageId!] = img.imgUrl!.trim();
          }
        }

        for (int i = 0; i < data.sliderDetails.length; i++) {
          final detail = data.sliderDetails[i];

          String? resolvedImg = (detail.imgUrl != null && detail.imgUrl!.trim().isNotEmpty)
              ? detail.imgUrl!.trim()
              : null;

          if (resolvedImg == null && detail.backgroundImage != null) {
            final bgId = int.tryParse(detail.backgroundImage!);
            if (bgId != null && imageMap.containsKey(bgId)) {
              resolvedImg = imageMap[bgId];
            }
          }

          if (resolvedImg == null && i < data.imageUrl.length) {
            final fb = data.imageUrl[i].imgUrl?.trim();
            if (fb != null && fb.isNotEmpty) {
              resolvedImg = fb;
            }
          }

          newDetails.add({
            'id': detail.id,
            'title': detail.title ?? '',
            'subtitle': detail.subTitle ?? '',
            'title_en': detail.titleEn,
            'title_ar': detail.titleAr,
            'sub_title_en': detail.subTitleEn,
            'sub_title_ar': detail.subTitleAr,
            'btn_text': detail.btnText,
            'btn_text_en': detail.btnTextEn,
            'btn_text_ar': detail.btnTextAr,
            'service_id': detail.serviceId,
            'action_type': detail.actionType ?? 'none',
            'action_target': detail.actionTarget,
            'order': detail.order ?? 0,
            'img_url': resolvedImg ?? '',
          });

          newImages.add(resolvedImg ?? '');
        }

        sliderDetailsList = newDetails;
        sliderImageList = newImages;
        isLoaded = true;
        hasError = false;
        debugPrint(
            '[SliderService] Loaded ${sliderDetailsList.length} remote slide(s)');
      } else {
        debugPrint(
            '[SliderService] GET $uri failed with status ${response.statusCode}');
        sliderDetailsList = [];
        sliderImageList = [];
        hasError = true;
        isLoaded = true;
      }
    } catch (e) {
      sw.stop();
      debugPrint(
          '[SliderService] GET $uri failed after ${sw.elapsedMilliseconds}ms: $e');
      sliderDetailsList = [];
      sliderImageList = [];
      hasError = true;
      isLoaded = true;
    } finally {
      if (disposeClient) {
        httpClient.close();
      }
      isLoading = false;
      debugPrint(
          '[SliderService] Terminal state reached: isLoaded=$isLoaded, isLoading=$isLoading, hasError=$hasError, slides=${sliderDetailsList.length}');
      _safeNotifyListeners();
    }
  }
}
