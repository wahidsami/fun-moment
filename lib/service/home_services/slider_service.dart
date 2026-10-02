import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/model/slider_model.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class SliderService with ChangeNotifier {
  List<Map> sliderDetailsList = [];
  List sliderImageList = [];
  loadSlider() async {
    if (sliderDetailsList.isEmpty) {
      try {
        var response = await http
            .get(Uri.parse('$baseApi/slider'))
            .timeout(const Duration(seconds: 5));

        if (response.statusCode == 201 || response.statusCode == 200) {
          var data = SliderModel.fromJson(jsonDecode(response.body));

          for (int i = 0; i < data.sliderDetails.length; i++) {
            sliderDetailsList.add({
              'title': data.sliderDetails[i].title,
              'subtitle': data.sliderDetails[i].subTitle
            });

            sliderImageList.add(data.imageUrl[i].imgUrl);
          }
          notifyListeners();
        }
      } catch (e) {
        debugPrint('SliderService.loadSlider error: $e');
      }
    } else {
      //already loaded from server. no need to load again
    }
  }
}
