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

        for (int i = 0; i < data.sliderDetails.length; i++) {
          final detail = data.sliderDetails[i];
          final fallbackImgUrl = i < data.imageUrl.length ? data.imageUrl[i].imgUrl : null;
          final resolvedImg = (detail.imgUrl != null && detail.imgUrl!.trim().isNotEmpty)
              ? detail.imgUrl!.trim()
              : fallbackImgUrl?.trim();

          newDetails.add({
            'id': detail.id,
            'title': detail.title,
            'subtitle': detail.subTitle,
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
            'img_url': resolvedImg,
          });

          if (resolvedImg != null &&
              resolvedImg.isNotEmpty &&
              resolvedImg != placeHolderUrl &&
              !resolvedImg.contains('i.postimg.cc/rpsKNndW/New-Project.png')) {
            newImages.add(resolvedImg);
          }
        }

        if (newImages.isNotEmpty) {
          sliderDetailsList = newDetails;
          sliderImageList = newImages;
          debugPrint(
              '[SliderService] Loaded ${newImages.length} remote slider image(s)');
        } else {
          sliderDetailsList = [];
          sliderImageList = [];
          debugPrint(
              '[SliderService] Completed with 0 remote images; fallback carousel will activate');
        }
        isLoaded = true;
        hasError = false;
      } else {
        debugPrint(
            '[SliderService] GET $uri failed with status ${response.statusCode}');
        hasError = true;
        isLoaded = true;
      }
    } catch (e) {
      sw.stop();
      debugPrint(
          '[SliderService] GET $uri failed after ${sw.elapsedMilliseconds}ms: $e');
      hasError = true;
      isLoaded = true;
    } finally {
      if (disposeClient) {
        httpClient.close();
      }
      isLoading = false;
      debugPrint(
          '[SliderService] Terminal state reached: isLoaded=$isLoaded, isLoading=$isLoading, hasError=$hasError, images=${sliderImageList.length}');
      _safeNotifyListeners();
    }
  }
}
