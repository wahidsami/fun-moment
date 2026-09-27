import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:funmoments/model/service_details_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ServiceDetailsService with ChangeNotifier {
  var serviceAllDetails;

  var sellerId;

  bool isloading = false;

  // List reviewList = [];

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  fetchServiceDetails(serviceId) async {
    setLoadingTrue();
    try {
      var connection = await checkConnection();
      if (!connection) {
        serviceAllDetails = 'error';
        OthersHelper().showToast('Please check your internet connection', Colors.black);
        return;
      }

      var header = {
        "Accept": "application/json",
      };

      print('$baseApi/service-details/$serviceId');

      var response = await http.get(
          Uri.parse('$baseApi/service-details/$serviceId'),
          headers: header);

      print(response.body);
      print(response.statusCode);
      if (response.statusCode == 200 || response.statusCode == 201) {
        var data = ServiceDetailsModel.fromJson(jsonDecode(response.body));

        serviceAllDetails = data;
        sellerId = jsonDecode(response.body)['service_details']
            ['seller_for_mobile']['id'];
      } else {
        serviceAllDetails = 'error';
        OthersHelper().showToast('Something went wrong', Colors.black);
      }
    } catch (e, st) {
      debugPrint('Error in fetchServiceDetails: $e\n$st');
      serviceAllDetails = 'error';
      OthersHelper().showToast('Failed to load service details', Colors.black);
    } finally {
      setLoadingFalse();
      notifyListeners();
    }
  }
}
