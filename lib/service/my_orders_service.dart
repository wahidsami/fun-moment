import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:funmoments/model/my_orders_list_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MyOrdersService with ChangeNotifier {
  var myServices;
  var nextPageUrl;

  bool isLoading = false;
  bool isLoadingNextPage = false;

  var orderStatusOptions = [
    "pending",
    "active",
    "complete",
    "delivered",
    "cancelled",
    "All"
  ];
  var paymentStatusOptions = ['pending', 'complete', "All"];
  var selectedPaymentSort = "All";
  var selectedOrderSort = "All";

  int totalPages = 1;
  int currentPage = 1;

  void resetState() {
    myServices = null;
    nextPageUrl = null;
    isLoading = false;
    isLoadingNextPage = false;
    selectedPaymentSort = "All";
    selectedOrderSort = "All";
    totalPages = 1;
    currentPage = 1;
    notifyListeners();
  }

  String get paymentStatusCode {
    if (selectedPaymentSort == "All") {
      return '';
    }
    return paymentStatusOptions.indexOf(selectedPaymentSort).toString();
  }

  String get orderStatusCode {
    if (selectedOrderSort == "All") {
      return '';
    }
    return orderStatusOptions.indexOf(selectedOrderSort).toString();
  }

  setPaymentSort(value) {
    if (value == selectedPaymentSort) {
      return;
    }
    selectedPaymentSort = value;
    fetchMyOrders();
    notifyListeners();
  }

  setOrderSort(value) {
    if (value == selectedOrderSort) {
      return;
    }
    selectedOrderSort = value;
    fetchMyOrders();
    notifyListeners();
  }

  setLoadingTrue() {
    isLoading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isLoading = false;
    notifyListeners();
  }

  setIsLoadingNextPage(value) {
    if (value == isLoadingNextPage) {
      return;
    }
    isLoadingNextPage = value;
    notifyListeners();
  }

  int currentUserType = 1;
  bool get isSeller => currentUserType == 0;

  fetchMyOrders({bool? isSellerOverride}) async {
    //get user id
    SharedPreferences prefs = await SharedPreferences.getInstance();
    int? userId = prefs.getInt('userId');
    var token = prefs.getString('token');

    final rawUserType = prefs.get('userType');
    int? storedUserType;
    if (rawUserType is int) {
      storedUserType = rawUserType;
    } else if (rawUserType is num) {
      storedUserType = rawUserType.toInt();
    } else if (rawUserType is String) {
      storedUserType = int.tryParse(rawUserType);
    }

    final bool isStoredSeller = (storedUserType == 0);

    // DEFENSIVE HARD-BLOCK:
    // If stored userType is 0 (Provider), NEVER allow routing to customer endpoint,
    // even if an uninitialized caller passes isSellerOverride: false.
    if (isStoredSeller) {
      currentUserType = 0;
    } else if (isSellerOverride != null) {
      currentUserType = isSellerOverride ? 0 : 1;
    } else if (storedUserType != null) {
      currentUserType = storedUserType;
    }

    var header = {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "Authorization": "Bearer $token",
    };

    var connection = await checkConnection();
    if (connection) {
      setLoadingTrue();

      bool isSellerRole = (currentUserType == 0);
      String prefix = isSellerRole ? 'seller' : 'user';

      // Secondary defense-in-depth: if device has seller stored, NEVER use 'user'
      if (isStoredSeller && prefix == 'user') {
        debugPrint('[PROVIDER ORDERS HARD-BLOCK] Prevented /user/my-orders for provider account. Forcing seller endpoint.');
        prefix = 'seller';
        currentUserType = 0;
      }

      final endpoint =
          '$baseApi/$prefix/my-orders?payment_status=$paymentStatusCode&status=$orderStatusCode';

      // Required [MY ORDERS ROUTING TRACE]
      print('[MY ORDERS ROUTING TRACE] stored SharedPreferences raw userType: $rawUserType');
      print('[MY ORDERS ROUTING TRACE] parsed storedUserType: $storedUserType');
      print('[MY ORDERS ROUTING TRACE] isSellerOverride: $isSellerOverride');
      print('[MY ORDERS ROUTING TRACE] currentUserType: $currentUserType');
      print('[MY ORDERS ROUTING TRACE] resolved prefix: $prefix');
      print('[MY ORDERS ROUTING TRACE] endpoint: $endpoint');
      print('[MY ORDERS ROUTING TRACE] StackTrace:\n${StackTrace.current}');

      debugPrint('[MY ORDERS ROUTING TRACE] stored SharedPreferences raw userType: $rawUserType');
      debugPrint('[MY ORDERS ROUTING TRACE] parsed storedUserType: $storedUserType');
      debugPrint('[MY ORDERS ROUTING TRACE] isSellerOverride: $isSellerOverride');
      debugPrint('[MY ORDERS ROUTING TRACE] currentUserType: $currentUserType');
      debugPrint('[MY ORDERS ROUTING TRACE] resolved prefix: $prefix');
      debugPrint('[MY ORDERS ROUTING TRACE] endpoint: $endpoint');

      debugPrint('[MY ORDERS REQUEST]');
      debugPrint('caller=MyOrdersService.fetchMyOrders');
      debugPrint('user_type=$currentUserType');
      debugPrint('endpoint=$endpoint');
      if (endpoint.contains('/user/my-orders')) {
        debugPrint('[MY ORDERS REQUEST] StackTrace for /user/my-orders:');
        debugPrint(StackTrace.current.toString());
      }

      debugPrint('[PROVIDER ORDERS] starting request');
      debugPrint('[PROVIDER ORDERS] role=user_type=$currentUserType');
      debugPrint('[PROVIDER ORDERS] endpoint=$endpoint');

      try {
        var response = await http.post(
          Uri.parse(endpoint),
          headers: header,
        );

        debugPrint('[PROVIDER ORDERS] status: ${response.statusCode}');

        if (response.statusCode == 201 || response.statusCode == 200) {
          final decoded = jsonDecode(response.body);
          final rawData = decoded is Map &&
                  decoded['my_orders'] is Map &&
                  decoded['my_orders']['data'] is List
              ? decoded['my_orders']['data'] as List
              : [];

          debugPrint('[PROVIDER ORDERS] response count: ${rawData.length}');

          final Map<String, dynamic> decodedMap =
              Map<String, dynamic>.from(decoded is Map ? decoded : {});
          var data = MyordersListModel.fromJson(decodedMap);
          debugPrint(
              '[PROVIDER ORDERS] parsed orders count: ${data.myOrders.length}');

          myServices = data.myOrders;
          nextPageUrl = data.nextPage;
          debugPrint(
              '[PROVIDER ORDERS] state orders count: ${myServices.length}');

          isLoading = false;
          notifyListeners();
          setLoadingFalse();
          return myServices;
        } else {
          debugPrint(
              '[PROVIDER ORDERS] request failed with status: ${response.statusCode}');
          myServices = 'error';
          isLoading = false;
          notifyListeners();
          setLoadingFalse();
          return myServices;
        }
      } catch (e, stack) {
        debugPrint('[PROVIDER ORDERS] Exception during fetchMyOrders: $e');
        debugPrint(stack.toString());
        myServices = 'error';
        isLoading = false;
        notifyListeners();
        setLoadingFalse();
        return myServices;
      }
    }
  }

  fetchNextOrders() async {
    //get user id
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var token = prefs.getString('token');

    var header = {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "Authorization": "Bearer $token",
    };

    var connection = await checkConnection();
    if (nextPageUrl == null) {
      return false;
    }
    if (connection) {
      setIsLoadingNextPage(true);
      try {
        var response =
            await http.post(Uri.parse('$nextPageUrl'), headers: header);

        if (response.statusCode == 201 || response.statusCode == 200) {
          final decoded = jsonDecode(response.body);
          if (decoded is Map &&
              decoded['my_orders'] is Map &&
              decoded['my_orders']['data'] is List) {
            final Map<String, dynamic> decodedMap =
                Map<String, dynamic>.from(decoded);
            var data = MyordersListModel.fromJson(decodedMap);
            for (var element in data.myOrders) {
              if (myServices is List) {
                myServices.add(element);
              }
            }
            nextPageUrl = data.nextPage;
            setIsLoadingNextPage(false);
            notifyListeners();
            return myServices;
          }
        }
      } catch (e) {
        debugPrint('[PROVIDER ORDERS] Exception during fetchNextOrders: $e');
      }
      setIsLoadingNextPage(false);
      return false;
    }
  }
}
