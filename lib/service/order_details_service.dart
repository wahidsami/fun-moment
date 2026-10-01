// ignore_for_file: avoid_print

import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/order_details_model.dart';
import 'package:funmoments/model/order_extra_model.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/orders_service.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/view/booking/components/order_extra_accept_success_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'common_service.dart';

class OrderDetailsService with ChangeNotifier {
  var orderDetails;

  var orderStatus;

  List orderExtra = [];

  bool isLoading = true;

  setLoadingStatus(bool status) {
    isLoading = status;
    notifyListeners();
  }

  void resetState() {
    orderDetails = null;
    orderStatus = null;
    orderExtra = [];
    isLoading = true;
    notifyListeners();
  }

  Future<bool> fetchOrderDetails(orderId, BuildContext context,
      {bool isFromOrderComplete = false}) async {
    //get user id
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var token = prefs.getString('token');

    var header = {
      //if header type is application/json then the data should be in jsonEncode method
      "Accept": "application/json",
      "Content-Type": "application/json",
      "Authorization": "Bearer $token",
    };

    var connection = await checkConnection();
    if (!connection) {
      orderDetails = 'error';
      setLoadingStatus(false);
      return false;
    }

    if (!isFromOrderComplete) {
      setLoadingStatus(true);
    }

    try {
      bool isSeller = false;
      try {
        isSeller = Provider.of<ProfileService>(context, listen: false).isSeller;
      } catch (_) {}
      if (prefs.containsKey('userType')) {
        final rawUserType = prefs.get('userType');
        if (rawUserType is int) {
          isSeller = rawUserType == 0;
        } else if (rawUserType is num) {
          isSeller = rawUserType.toInt() == 0;
        } else if (rawUserType is String) {
          isSeller = int.tryParse(rawUserType) == 0;
        }
      }

      final endpoint = isSeller
          ? '$baseApi/seller/my-orders/$orderId'
          : '$baseApi/user/my-orders/$orderId';

      debugPrint('[MY ORDERS REQUEST]');
      debugPrint('caller=OrderDetailsService.fetchOrderDetails');
      debugPrint('user_type=${isSeller ? 0 : 1}');
      debugPrint('endpoint=$endpoint');
      if (endpoint.contains('/user/my-orders')) {
        debugPrint('[MY ORDERS REQUEST] StackTrace for /user/my-orders:');
        debugPrint(StackTrace.current.toString());
      }

      var response = await http
          .post(Uri.parse(endpoint), headers: header)
          .timeout(const Duration(seconds: 20));

      print('order details response ${response.body}');

      if (response.statusCode == 201) {
        final decoded = jsonDecode(response.body);
        if (decoded != null &&
            decoded is Map<String, dynamic> &&
            decoded.containsKey('orderInfo') &&
            decoded['orderInfo'] != null) {
          var data = OrderDetailsModel.fromJson(decoded);

          orderDetails = data.orderInfo;

          var status = data.orderInfo.status;

          orderStatus = getOrderStatus(status ?? -1);

          try {
            await fetchOrderExtraList(orderId);
          } catch (e) {
            debugPrint('fetchOrderExtraList error: $e');
          }

          try {
            Provider.of<OrdersService>(context, listen: false)
                .fetchDeclineHistory(context, orderId: orderId);
          } catch (e) {
            debugPrint('fetchDeclineHistory error: $e');
          }

          setLoadingStatus(false);
          return true;
        } else {
          // Unexpected or empty response shape
          orderDetails = 'error';
          setLoadingStatus(false);
          return false;
        }
      } else {
        // Non-201 response (4xx, 5xx)
        orderDetails = 'error';
        setLoadingStatus(false);
        return false;
      }
    } catch (e) {
      debugPrint('fetchOrderDetails exception: $e');
      orderDetails = 'error';
      setLoadingStatus(false);
      return false;
    }
  }

  //fetch order extra list
  Future<bool> fetchOrderExtraList(orderId) async {
    //get user id
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var token = prefs.getString('token');

    var header = {
      //if header type is application/json then the data should be in jsonEncode method
      "Accept": "application/json",
      "Content-Type": "application/json",
      "Authorization": "Bearer $token",
    };

    var connection = await checkConnection();
    if (!connection) return false;

    //if connection is ok
    var response = await http.get(
        Uri.parse('$baseApi/user/order/extra-service/list/$orderId'),
        headers: header);

    final decodedData = jsonDecode(response.body);

    setLoadingStatus(false);

    if (response.statusCode == 201 &&
        decodedData.containsKey('extra_service_list')) {
      var data = OrderExtraModel.fromJson(decodedData);

      orderExtra = data.extraServiceList;

      notifyListeners();

      return true;
    } else {
      print('error fetching order extra ${response.body}');
      return false;
    }
  }

  var selectedExtraId;
  var selectedOrderIdForExtra;
  var selectedExtraPrice;
  var selectedExtraSellerId;

  setExtraDetails(
      {required orderId,
      required extraId,
      required extraPrice,
      required sellerId}) {
    selectedOrderIdForExtra = orderId;
    selectedExtraId = extraId.toString();
    selectedExtraPrice = extraPrice.toString();
    selectedExtraSellerId = sellerId;
    notifyListeners();
  }

  //============>
  Future<bool> acceptOrderExtra(BuildContext context,
      {bool manualPaymentSelected = false, imagePath}) async {
    //get user id
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var token = prefs.getString('token');

    var connection = await checkConnection();
    if (!connection) return false;
    //if connection is ok

    var selectedPayment =
        Provider.of<PaymentGatewayListService>(context, listen: false)
                .selectedMethodName ??
            'cash_on_delivery';

    FormData formData;
    var dio = Dio();
    dio.options.headers['Content-Type'] = 'multipart/form-data';
    dio.options.headers['Accept'] = 'application/json';
    dio.options.headers['Authorization'] = "Bearer $token";

    if (manualPaymentSelected == true) {
      formData = FormData.fromMap({
        'id': selectedExtraId,
        'order_id': selectedOrderIdForExtra,
        'selected_payment_gateway': selectedPayment,
        'manual_payment_image': await MultipartFile.fromFile(imagePath,
            filename: 'bankTransfer.jpg'),
      });
    } else {
      formData = FormData.fromMap({
        'id': selectedExtraId,
        'order_id': selectedOrderIdForExtra,
        'selected_payment_gateway': selectedPayment,
      });
    }

    var response = await dio.post(
      '$baseApi/user/order/extra-service/accept',
      data: formData,
      options: Options(
        validateStatus: (status) {
          return true;
        },
      ),
    );

    setLoadingStatus(false);

    if (response.statusCode == 200) {
      await fetchOrderDetails(selectedOrderIdForExtra, context);

      Navigator.pop(context);
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();

      Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (BuildContext context) =>
              const OrderExtraAcceptSuccessPage(),
        ),
      );

      //Send notification to seller
      var sellerId = Provider.of<OrderDetailsService>(context, listen: false)
          .selectedExtraSellerId;
      var username = Provider.of<ProfileService>(context, listen: false)
              .profileDetails
              .userDetails
              .name ??
          '';
      var orderId = Provider.of<OrderDetailsService>(context, listen: false)
          .orderDetails
          .id;
      PushNotificationService().sendNotificationToSeller(context,
          sellerId: sellerId,
          title: "$username " +
              lnProvider.getString("accepted your order extra request"),
          body: lnProvider.getString("Order Id") + ': $orderId');

      return true;
    } else {
      print('error accepting order extra ${response.data}');

      Navigator.pop(context);
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();

      OthersHelper().showToast('Error accepting order extra', Colors.black);
      return false;
    }
  }

  //==============>

  declineOrderExtra(BuildContext context,
      {required extraId, required orderId}) async {
    //get user id
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var token = prefs.getString('token');

    var header = {
      //if header type is application/json then the data should be in jsonEncode method
      "Accept": "application/json",
      "Content-Type": "application/json",
      "Authorization": "Bearer $token",
    };

    var connection = await checkConnection();
    if (connection) {
      //if connection is ok

      setLoadingStatus(true);

      var data = jsonEncode({'id': extraId, 'order_id': orderId});

      var response = await http.post(
          Uri.parse('$baseApi/user/order/extra-service/decline'),
          headers: header,
          body: data);
      print(data);
      if (response.statusCode == 201) {
        await fetchOrderDetails(orderId, context);
        setLoadingStatus(false);
        var sellerId = Provider.of<OrderDetailsService>(context, listen: false)
            .orderDetails
            .sellerDetails
            .id;
        var username = Provider.of<ProfileService>(context, listen: false)
                .profileDetails
                .userDetails
                .name ??
            '';
        PushNotificationService().sendNotificationToSeller(context,
            sellerId: sellerId,
            title: "$username " +
                lnProvider.getString("declined your order extra request"),
            body: lnProvider.getString("Order Id") + ': $orderId');
        Navigator.pop(context);

        notifyListeners();
      } else {
        setLoadingStatus(false);
        OthersHelper().showToast('Error declining order extra', Colors.black);
        print('error fetching order extra ${response.body}');
      }
    }
  }

  //=========>

  getOrderStatus(status) {
    if (status == 0) {
      return 'Pending';
    } else if (status == 1) {
      return 'Active';
    } else if (status == 2) {
      return "Completed";
    } else if (status == 3) {
      return "Delivered";
    } else if (status == 4) {
      return 'Cancelled';
    } else {
      return 'Unknown';
    }
  }
}
