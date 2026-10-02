import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/model/top_service_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/db/db_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;

class TopRatedServicesSerivce with ChangeNotifier {
  var topServiceMap = [];
  bool alreadySaved = false;

  bool isLoading = false;
  bool _isFetching = false;

  fetchTopService({bool isRefresh = false}) async {
    if (_isFetching) return;
    if (topServiceMap.isNotEmpty &&
        topServiceMap[0] != 'error' &&
        !isRefresh) return;

    if (isRefresh || (topServiceMap.isNotEmpty && topServiceMap[0] == 'error')) {
      topServiceMap = [];
    }
    _isFetching = true;
    isLoading = true;
    // Yield execution to allow caller (e.g. initState) and build lifecycle to finish before notifying listeners
    await Future<void>.delayed(Duration.zero);
    if (!_isFetching) return;
    notifyListeners();

    try {
      http.Response? response;
      for (int attempt = 0; attempt < 2; attempt++) {
        try {
          response = await http
              .get(Uri.parse('$baseApi/top-services'))
              .timeout(const Duration(seconds: 6));
          if (response.statusCode == 200 || response.statusCode == 201) {
            break;
          }
        } catch (attemptErr) {
          debugPrint('fetchTopService attempt $attempt failed: $attemptErr');
          if (attempt == 1) rethrow;
          await Future.delayed(const Duration(milliseconds: 400));
        }
      }

      if (response != null && (response.statusCode == 201 || response.statusCode == 200)) {
        var data = TopServiceModel.fromJson(jsonDecode(response.body));
        topServiceMap.clear();

        for (int i = 0; i < data.topServices.length; i++) {
          String? serviceImage;

          if (data.serviceImage.length > i) {
            serviceImage = data.serviceImage[i]?.imgUrl;
          } else {
            serviceImage = null;
          }

          int totalRating = 0;
          for (int j = 0;
              j < data.topServices[i].reviewsForMobile.length;
              j++) {
            totalRating = totalRating +
                (data.topServices[i].reviewsForMobile[j].rating?.toInt() ?? 0);
          }
          double averageRate = 0;

          if (data.topServices[i].reviewsForMobile.isNotEmpty) {
            averageRate =
                (totalRating / data.topServices[i].reviewsForMobile.length);
          }
          setServiceList(
              data.topServices[i].id,
              data.topServices[i].title,
              data.topServices[i].sellerForMobile.name,
              data.topServices[i].price,
              averageRate,
              serviceImage,
              i,
              data.topServices[i].sellerId);
        }
      } else {
        topServiceMap.add('error');
      }
    } catch (e) {
      debugPrint('fetchTopService non-fatal: $e');
      if (topServiceMap.isEmpty) {
        topServiceMap.add('error');
      }
    } finally {
      isLoading = false;
      _isFetching = false;
      notifyListeners();
    }
  }

  setServiceList(
      serviceId, title, sellerName, price, rating, image, index, sellerId) {
    topServiceMap.add({
      'serviceId': serviceId,
      'title': title,
      'sellerName': sellerName,
      'price': price,
      'rating': rating,
      'image': image,
      'isSaved': false,
      'sellerId': sellerId,
    });

    checkIfAlreadySaved(serviceId, title, sellerName, index);
  }

  checkIfAlreadySaved(serviceId, title, sellerName, index) async {
    var newListMap = topServiceMap;
    alreadySaved = await DbService().checkIfSaved(serviceId, title, sellerName);
    newListMap[index]['isSaved'] = alreadySaved;
    topServiceMap = newListMap;
    notifyListeners();
  }

  saveOrUnsave(int serviceId, String? title, image, var price, String? sellerName,
      double rating, int index, BuildContext context, sellerId) async {
    var newListMap = topServiceMap;
    alreadySaved = await DbService().saveOrUnsave(
        serviceId,
        title ?? '',
        image ?? placeHolderUrl,
        price,
        sellerName ?? '',
        rating,
        context,
        sellerId);
    newListMap[index]['isSaved'] = alreadySaved;
    topServiceMap = newListMap;
    notifyListeners();
  }

  topServiceSaveUnsaveFromOtherPage(
    int serviceId,
    String title,
    String sellerName,
  ) async {
    int? index;
    for (int i = 0; i < topServiceMap.length; i++) {
      if (topServiceMap[i]['serviceId'] == serviceId &&
          topServiceMap[i]['title'] == title &&
          topServiceMap[i]['sellerName'] == sellerName) {
        index = i;
        break;
      }
    }

    if (index != null) {
      //if that product exist in other page then change the saved button accordingly
      var newListMap = topServiceMap;
      alreadySaved =
          await DbService().checkIfSaved(serviceId, title, sellerName);
      newListMap[index]['isSaved'] = alreadySaved;
      topServiceMap = newListMap;
      notifyListeners();
    }
  }
}
