import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/model/recent_service_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/db/db_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;

class RecentServicesService with ChangeNotifier {
  var recentServiceMap = [];
  bool alreadySaved = false;
  bool hasService = true;

  bool isLoading = false;
  bool _isFetching = false;

  fetchRecentService({bool isRefresh = false}) async {
    if (_isFetching) return;
    if (recentServiceMap.isNotEmpty &&
        recentServiceMap[0] != 'error' &&
        !isRefresh) return;

    if (isRefresh || (recentServiceMap.isNotEmpty && recentServiceMap[0] == 'error')) {
      recentServiceMap = [];
    }
    _isFetching = true;
    isLoading = true;
    // Yield execution to allow caller (e.g. initState) and build lifecycle to finish before notifying listeners
    await Future<void>.delayed(Duration.zero);
    if (!_isFetching) return;
    notifyListeners();

    try {
      http.Response? response;
      for (int attempt = 0; attempt < 3; attempt++) {
        try {
          response = await http
              .get(Uri.parse('$baseApi/latest-services'))
              .timeout(const Duration(seconds: 4));
          if (response.statusCode == 200 || response.statusCode == 201) {
            break;
          }
        } catch (attemptErr) {
          debugPrint('fetchRecentService attempt $attempt failed: $attemptErr');
          if (attempt == 2) rethrow;
          await Future.delayed(Duration(milliseconds: 300 * (attempt + 1)));
        }
      }

      if (response != null && (response.statusCode == 201 || response.statusCode == 200)) {
        var data = RecentServiceModel.fromJson(jsonDecode(response.body));

        if (data.latestServices.isEmpty) {
          hasService = false;
          recentServiceMap.clear();
          return;
        } else {
          hasService = true;
          recentServiceMap.clear();
        }

        for (int i = 0; i < data.latestServices.length; i++) {
          String? serviceImage;
          if (data.serviceImage.length > i) {
            serviceImage = data.serviceImage[i]?.imgUrl;
          } else {
            serviceImage = null;
          }

          int totalRating = 0;
          for (int j = 0;
              j < data.latestServices[i].reviewsForMobile.length;
              j++) {
            totalRating = totalRating +
                (data.latestServices[i].reviewsForMobile[j].rating?.toInt() ?? 0);
          }

          double averageRate = 0;

          if (data.latestServices[i].reviewsForMobile.isNotEmpty) {
            averageRate = (totalRating /
                data.latestServices[i].reviewsForMobile.length);
          }

          setServiceList(
              data.latestServices[i].id,
              data.latestServices[i].title,
              data.latestServices[i].sellerForMobile.name,
              data.latestServices[i].price,
              averageRate,
              serviceImage,
              i,
              data.latestServices[i].sellerId);
        }
      } else {
        recentServiceMap.add('error');
      }
    } catch (e) {
      debugPrint('fetchRecentService non-fatal: $e');
      if (recentServiceMap.isEmpty) {
        recentServiceMap.add('error');
      }
    } finally {
      isLoading = false;
      _isFetching = false;
      notifyListeners();
    }
  }

  setServiceList(
      serviceId, title, sellerName, price, rating, image, index, sellerId) {
    recentServiceMap.add({
      'serviceId': serviceId,
      'title': title,
      'sellerName': sellerName,
      'price': price,
      'rating': rating,
      'image': image,
      'isSaved': false,
      'sellerId': sellerId
    });

    checkIfAlreadySaved(serviceId, title, sellerName, index);
  }

  checkIfAlreadySaved(serviceId, title, sellerName, index) async {
    var newListMap = recentServiceMap;
    alreadySaved = await DbService().checkIfSaved(serviceId, title, sellerName);
    newListMap[index]['isSaved'] = alreadySaved;
    recentServiceMap = newListMap;
    notifyListeners();
  }

  saveOrUnsave(int serviceId, String? title, image, int price, String? sellerName,
      double rating, int index, BuildContext context, sellerId) async {
    var newListMap = recentServiceMap;
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
    recentServiceMap = newListMap;
    notifyListeners();
  }

  recentServiceSaveUnsaveFromOtherPage(
    int serviceId,
    String title,
    String sellerName,
  ) async {
    int? index;
    for (int i = 0; i < recentServiceMap.length; i++) {
      if (recentServiceMap[i]['serviceId'] == serviceId &&
          recentServiceMap[i]['title'] == title &&
          recentServiceMap[i]['sellerName'] == sellerName) {
        index = i;
        break;
      }
    }

    if (index != null) {
      //if that product exist in other page then change the fav button accordingly
      var newListMap = recentServiceMap;
      alreadySaved =
          await DbService().checkIfSaved(serviceId, title, sellerName);
      newListMap[index]['isSaved'] = alreadySaved;
      recentServiceMap = newListMap;
      notifyListeners();
    }
  }
}
