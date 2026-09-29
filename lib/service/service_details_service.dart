import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:funmoments/model/service_details_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ServiceDetailsService with ChangeNotifier {
  dynamic serviceAllDetails;
  dynamic sellerId;
  int? currentServiceId;
  bool isloading = false;
  bool hasError = false;
  String? errorMessage;

  setLoadingTrue() {
    isloading = true;
    hasError = false;
    errorMessage = null;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  Future<void> retry() async {
    if (currentServiceId != null) {
      await fetchServiceDetails(currentServiceId, forceRefresh: true);
    }
  }

  Future<void> fetchServiceDetails(dynamic serviceId, {bool forceRefresh = false}) async {
    if (serviceId == null) return;
    final parsedId = (serviceId is int) ? serviceId : int.tryParse(serviceId.toString());
    if (parsedId == null) return;

    // Avoid repeated identical fetches if already loaded and not forcing refresh
    if (!forceRefresh &&
        currentServiceId == parsedId &&
        serviceAllDetails != null &&
        serviceAllDetails != 'error' &&
        !isloading) {
      debugPrint('[ServiceDetails] already loaded serviceId $parsedId, skipping duplicate fetch');
      return;
    }

    currentServiceId = parsedId;
    setLoadingTrue();

    try {
      var connection = await checkConnection();
      if (!connection) {
        hasError = true;
        serviceAllDetails = 'error';
        errorMessage = 'Please check your internet connection';
        OthersHelper().showToast(errorMessage!, Colors.black);
        return;
      }

      var header = {
        "Accept": "application/json",
      };

      final url = '$baseApi/service-details/$parsedId';
      debugPrint('[ServiceDetails] Requesting: $url');

      var response = await http
          .get(Uri.parse(url), headers: header)
          .timeout(const Duration(seconds: 8));

      debugPrint('[ServiceDetails] Status code: ${response.statusCode}');

      if (response.statusCode == 200 || response.statusCode == 201) {
        var data = ServiceDetailsModel.fromJson(jsonDecode(response.body));

        serviceAllDetails = data;
        sellerId = jsonDecode(response.body)['service_details']
            ?['seller_for_mobile']?['id'];
        hasError = false;
        errorMessage = null;
      } else if (response.statusCode == 404) {
        hasError = true;
        serviceAllDetails = 'error';
        errorMessage = 'Service not found';
        debugPrint('[ServiceDetails] Service $parsedId not found (HTTP 404)');
      } else {
        hasError = true;
        serviceAllDetails = 'error';
        errorMessage = 'Something went wrong';
        debugPrint('[ServiceDetails] HTTP ${response.statusCode}: ${response.body}');
        OthersHelper().showToast('Something went wrong', Colors.black);
      }
    } on TimeoutException catch (e) {
      debugPrint('[ServiceDetails] TimeoutException after 8s: $e');
      hasError = true;
      serviceAllDetails = 'error';
      errorMessage = 'Request timed out. Please try again.';
      OthersHelper().showToast('Request timed out', Colors.black);
    } on SocketException catch (e) {
      debugPrint('[ServiceDetails] SocketException: $e');
      hasError = true;
      serviceAllDetails = 'error';
      errorMessage = 'Network connection failed';
      OthersHelper().showToast('Network error', Colors.black);
    } catch (e, st) {
      debugPrint('[ServiceDetails] Unexpected error in fetchServiceDetails: $e\n$st');
      hasError = true;
      serviceAllDetails = 'error';
      errorMessage = 'Failed to load service details';
      OthersHelper().showToast('Failed to load service details', Colors.black);
    } finally {
      setLoadingFalse();
    }
  }
}
