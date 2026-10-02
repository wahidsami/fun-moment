import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/serviceby_category_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/db/db_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ServiceByCategoryService with ChangeNotifier {
  var serviceMap = [];
  bool alreadySaved = false;
  bool hasError = false;
  bool isLoading = false;
  bool hasLoadedOnce = false;

  late int totalPages;

  int currentPage = 1;
  var alreadyAddedtoFav = false;
  List averageRateList = [];
  List imageList = [];

  setCurrentPage(newValue) {
    currentPage = newValue;
    notifyListeners();
  }

  setTotalPage(newPageNumber) {
    totalPages = newPageNumber;
    notifyListeners();
  }

  setEverythingToDefault() {
    serviceMap = [];
    currentPage = 1;
    averageRateList = [];
    imageList = [];
    hasError = false;
    isLoading = false;
    hasLoadedOnce = false;
    notifyListeners();
  }

  fetchCategoryService(context, categoryId, {bool isrefresh = false}) async {
    String apiLink =
        '$baseApi/service-list/search-by-category/$categoryId?page=$currentPage';

    if (isrefresh) {
      serviceMap = [];
      currentPage = 1;
      hasError = false;
      isLoading = true;
      notifyListeners();

      Provider.of<ServiceByCategoryService>(context, listen: false)
          .setCurrentPage(currentPage);
    } else {
      isLoading = true;
      notifyListeners();
    }

    try {
      var connection = await checkConnection();
      if (!connection) {
        if (serviceMap.isEmpty) {
          hasError = true;
        }
        isLoading = false;
        notifyListeners();
        return false;
      }

      for (int attempt = 1; attempt <= 3; attempt++) {
        final success = await _fetchSingleAttempt(apiLink, isrefresh);
        if (success) {
          return true;
        }
        if (attempt < 3) {
          await Future.delayed(Duration(milliseconds: 400 * attempt));
        }
      }

      if (serviceMap.isEmpty) {
        hasError = true;
      }
      isLoading = false;
      notifyListeners();
      return false;
    } catch (e) {
      debugPrint('fetchCategoryService error: $e');
      if (serviceMap.isEmpty) {
        hasError = true;
      }
      isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> _fetchSingleAttempt(String apiLink, bool isrefresh) async {
    try {
      var response = await http
          .get(Uri.parse(apiLink))
          .timeout(const Duration(seconds: 6));

      debugPrint('Category services response [${response.statusCode}]');

      if (response.statusCode == 201 || response.statusCode == 200) {
        var data = ServicebyCategoryModel.fromJson(jsonDecode(response.body));
        imageList = [];
        setTotalPage(data.allServices.lastPage);

        for (int i = 0; i < data.allServices.data.length; i++) {
          String? serviceImage;

          if (data.serviceImage.length > i) {
            serviceImage = data.serviceImage[i]?.imgUrl;
          } else {
            serviceImage = null;
          }

          int totalRating = 0;
          for (int j = 0;
              j < data.allServices.data[i].reviewsForMobile.length;
              j++) {
            totalRating = totalRating +
                (data.allServices.data[i].reviewsForMobile[j].rating?.toInt() ?? 0);
          }
          double averageRate = 0;

          if (data.allServices.data[i].reviewsForMobile.isNotEmpty) {
            averageRate = (totalRating /
                data.allServices.data[i].reviewsForMobile.length);
          }
          averageRateList.add(averageRate);
          imageList.add(serviceImage);
        }

        if (isrefresh) {
          setServiceList(
              data.allServices.data, averageRateList, imageList, false);
        } else {
          setServiceList(
              data.allServices.data, averageRateList, imageList, true);
        }

        imageList = [];
        currentPage++;
        setCurrentPage(currentPage);
        hasError = false;
        hasLoadedOnce = true;
        isLoading = false;
        notifyListeners();
        return true;
      } else if (response.statusCode == 404 ||
          response.body.contains('Service Not Found') ||
          response.body.contains('service not found')) {
        // Backend returns 404 when category has 0 services.
        // This is a normal empty state, not a network/server crash.
        debugPrint('[ServiceByCategory] Category has no services (404/Empty). Setting empty state.');
        serviceMap = [];
        hasError = false;
        hasLoadedOnce = true;
        isLoading = false;
        notifyListeners();
        return true;
      } else {
        return false;
      }
    } catch (e) {
      debugPrint('_fetchSingleAttempt error: $e');
      return false;
    }
  }

  setServiceList(data, averageRateList, imageList, bool addnewData) {
    if (addnewData == false) {
      //make the list empty first so that existing data doesn't stay
      serviceMap = [];
      notifyListeners();
    }

    for (int i = 0; i < data.length; i++) {
      serviceMap.add({
        'serviceId': data[i].id,
        'title': data[i].title,
        'sellerName': data[i].sellerForMobile.name,
        'price': data[i].price,
        'rating': averageRateList[i],
        'image': imageList[i],
        'isSaved': false,
        'sellerId': data[i].sellerId,
      });
      checkIfAlreadySaved(data[i].id, data[i].title,
          data[i].sellerForMobile.name, serviceMap.length - 1);
    }
  }

  checkIfAlreadySaved(serviceId, title, sellerName, index) async {
    var newListMap = serviceMap;
    alreadySaved = await DbService().checkIfSaved(serviceId, title, sellerName);
    newListMap[index]['isSaved'] = alreadySaved;
    serviceMap = newListMap;
    notifyListeners();
  }

  saveOrUnsave(int serviceId, String title, image, int price, String sellerName,
      double rating, int index, BuildContext context, sellerId) async {
    var newListMap = serviceMap;
    alreadySaved = await DbService().saveOrUnsave(serviceId, title,
        image ?? placeHolderUrl, price, sellerName, rating, context, sellerId);
    newListMap[index]['isSaved'] = alreadySaved;
    serviceMap = newListMap;
    notifyListeners();
    categoryServiceSaveUnsaveFromOtherPage(serviceId, title, sellerName);
  }

  categoryServiceSaveUnsaveFromOtherPage(
    int serviceId,
    String title,
    String sellerName,
  ) async {
    int? index;
    for (int i = 0; i < serviceMap.length; i++) {
      if (serviceMap[i]['serviceId'] == serviceId &&
          serviceMap[i]['title'] == title &&
          serviceMap[i]['sellerName'] == sellerName) {
        index = i;
        break;
      }
    }

    if (index != null) {
      //if that product exist in other page then change the saved button accordingly
      var newListMap = serviceMap;
      alreadySaved =
          await DbService().checkIfSaved(serviceId, title, sellerName);
      newListMap[index]['isSaved'] = alreadySaved;
      serviceMap = newListMap;
      notifyListeners();
    }
  }
}
