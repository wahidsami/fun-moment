import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/model/service_models/seller_all_service_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/db/db_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;

class SellerAllServicesService with ChangeNotifier {
  var serviceMap = [];
  bool alreadySaved = false;
  bool hasError = false;
  bool isLoading = false;

  late int totalPages;

  int currentPage = 1;
  var alreadyAddedtoFav = false;
  List averageRateList = [];

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
    hasError = false;
    isLoading = false;
    notifyListeners();
  }

  fetchSellerAllService(context, sellerId, {bool isrefresh = false}) async {
    if (isrefresh) {
      serviceMap = [];
      currentPage = 1;
      hasError = false;
      isLoading = true;
      notifyListeners();
      setCurrentPage(1);
    } else {
      isLoading = true;
      notifyListeners();
    }
    if (!isrefresh && currentPage > totalPages) {
      isLoading = false;
      notifyListeners();
      return false;
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

      String apiLink =
          '$baseApi/services-by-seller-id?seller_id=$sellerId?page=$currentPage';
      var response = await http
          .get(Uri.parse(apiLink))
          .timeout(const Duration(seconds: 12));

      if (response.statusCode == 200 || response.statusCode == 201) {
        var data = SellerAllServiceModel.fromJson(jsonDecode(response.body));

        setTotalPage(data.services.lastPage);

        for (int i = 0; i < data.services.data.length; i++) {
          int totalRating = 0;
          for (int j = 0;
              j < data.services.data[i].reviewsForMobile.length;
              j++) {
            totalRating = totalRating +
                (data.services.data[i].reviewsForMobile[j].rating?.toInt() ?? 0);
          }
          double averageRate = 0;

          if (data.services.data[i].reviewsForMobile.isNotEmpty) {
            averageRate =
                (totalRating / data.services.data[i].reviewsForMobile.length);
          }
          averageRateList.add(averageRate);
        }

        if (isrefresh) {
          setServiceList(data.services.data, averageRateList, false);
        } else {
          setServiceList(data.services.data, averageRateList, true);
        }

        currentPage++;
        hasError = false;
        isLoading = false;
        setCurrentPage(currentPage);
        notifyListeners();
        return true;
      } else if (response.statusCode == 404 ||
          response.body.contains('Service Not Found') ||
          response.body.contains('service not found')) {
        serviceMap = [];
        hasError = false;
        isLoading = false;
        notifyListeners();
        return true;
      } else {
        if (serviceMap.isEmpty) {
          hasError = true;
        }
        isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      debugPrint('fetchSellerAllService error: $e');
      if (serviceMap.isEmpty) {
        hasError = true;
      }
      isLoading = false;
      notifyListeners();
      return false;
    }
  }

  setServiceList(data, averageRateList, bool addnewData) {
    if (addnewData == false) {
      //make the list empty first so that existing data doesn't stay
      serviceMap = [];
      notifyListeners();
    }

    for (int i = 0; i < data.length; i++) {
      serviceMap.add({
        'serviceId': data[i].id,
        'title': data[i].title,
        'sellerName': data[i].sellerName ?? '',
        'price': data[i].price,
        'rating': averageRateList[i],
        'image': data[i].imageUrl,
        'isSaved': false,
        'sellerId': data[i].sellerId,
      });
      checkIfAlreadySaved(data[i].id, data[i].title, data[i].sellerName ?? '',
          serviceMap.length - 1);
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
  }
}
