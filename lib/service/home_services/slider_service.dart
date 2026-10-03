import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/model/slider_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class SliderService with ChangeNotifier {
  List<Map> sliderDetailsList = [];
  List sliderImageList = [];

  bool isLoading = false;
  bool isLoaded = false;
  bool hasError = false;

  void resetState() {
    sliderDetailsList = [];
    sliderImageList = [];
    isLoading = false;
    isLoaded = false;
    hasError = false;
    notifyListeners();
  }

  Future<void> loadSlider({bool isRefresh = false, http.Client? client}) async {
    // Once loaded, do not refetch unless explicitly requested via isRefresh
    if (isLoaded && !isRefresh) return;
    if (isLoading) return;

    isLoading = true;
    hasError = false;
    notifyListeners();

    final sw = Stopwatch()..start();
    final uri = Uri.parse('$baseApi/slider');
    debugPrint('[SliderService] GET $uri started (isRefresh=$isRefresh)');

    final httpClient = client ?? http.Client();
    final bool disposeClient = client == null;

    try {
      final response = await httpClient
          .get(uri)
          .timeout(const Duration(seconds: 10));
      sw.stop();
      debugPrint(
          '[SliderService] GET $uri returned ${response.statusCode} in ${sw.elapsedMilliseconds}ms');

      if (response.statusCode == 201 || response.statusCode == 200) {
        var data = SliderModel.fromJson(jsonDecode(response.body));

        final newDetails = <Map>[];
        final newImages = [];

        for (int i = 0; i < data.sliderDetails.length; i++) {
          newDetails.add({
            'title': data.sliderDetails[i].title,
            'subtitle': data.sliderDetails[i].subTitle
          });

          final imgUrl = i < data.imageUrl.length ? data.imageUrl[i].imgUrl : null;
          if (imgUrl != null &&
              imgUrl.toString().trim().isNotEmpty &&
              imgUrl.toString().trim() != placeHolderUrl &&
              !imgUrl.toString().contains('i.postimg.cc/rpsKNndW/New-Project.png')) {
            newImages.add(imgUrl.toString().trim());
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
      notifyListeners();
    }
  }
}
