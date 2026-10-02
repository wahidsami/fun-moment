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

  Future<void> loadSlider({bool isRefresh = false}) async {
    if (isLoaded && !isRefresh && sliderImageList.isNotEmpty) return;
    if (isLoading) return;

    isLoading = true;
    hasError = false;
    notifyListeners();

    try {
      var response = await http
          .get(Uri.parse('$baseApi/slider'))
          .timeout(const Duration(seconds: 12));

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
        } else {
          sliderDetailsList = [];
          sliderImageList = [];
        }
        isLoaded = true;
        hasError = false;
      } else {
        hasError = true;
        isLoaded = true;
      }
    } catch (e) {
      debugPrint('SliderService.loadSlider error: $e');
      hasError = true;
      isLoaded = true;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }
}
