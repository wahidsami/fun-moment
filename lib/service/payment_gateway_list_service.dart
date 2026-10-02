// ignore_for_file: prefer_typing_uninitialized_variables, avoid_print

import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/book_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'common_service.dart';

class PaymentGatewayListService with ChangeNotifier {
  List paymentList = [];
  var selectedMethodName;

  bool? isTestMode;
  var publicKey;
  var serverkey;

  var billPlzCollectionName;
  var paytabProfileId;

  var squareLocationId;

  var zitopayUserName;

  bool isloading = false;
  bool isLoaded = false;
  bool hasError = false;
  String? errorMessage;

  setSelectedMethodName(newName) {
    selectedMethodName = newName;
    notifyListeners();
  }

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  Future fetchGatewayList(BuildContext context, {bool forceRefresh = false, bool isFromDepositeToWallet = false}) async {
    // If payment list already loaded and not force-refreshing, don't reload
    if (!forceRefresh && isLoaded && paymentList.isNotEmpty) {
      return;
    }

    var connection = await checkConnection();
    if (!connection) {
      hasError = true;
      errorMessage = 'No internet connection. Please check your network and try again.';
      isloading = false;
      notifyListeners();
      return false;
    }

    setLoadingTrue();
    hasError = false;
    errorMessage = null;

    try {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      var token = prefs.getString('token');

      var header = {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Authorization": "Bearer $token",
      };

      var response = await http
          .post(Uri.parse('$baseApi/user/payment-gateway-list'), headers: header)
          .timeout(const Duration(seconds: 15));

      debugPrint('Payment gateway response [${response.statusCode}]: ${response.body}');
      setLoadingFalse();
      isLoaded = true;

      if (response.statusCode == 200 || response.statusCode == 201) {
        var decoded = jsonDecode(response.body);
        var rawList = decoded['gateway_list'];
        if (rawList is List && rawList.isNotEmpty) {
          paymentList = List.from(rawList);
          // Only show wallet payment if eligible: user is signed in and not depositing to wallet
          if (!isFromDepositeToWallet && (token != null && token.isNotEmpty)) {
            paymentList.add({
              "name": "wallet",
              "logo_link": "https://i.postimg.cc/y8pMmqF4/wallet.png"
            });
          }
          try {
            setSelectedMethodName(paymentList.first?['name']);
            setKey(paymentList.first?['name'], 0);
            Provider.of<BookService>(context, listen: false)
                .setSelectedPayment(paymentList.first?['name']);
          } catch (e) {
            debugPrint('Error selecting initial payment method: $e');
          }
        } else {
          paymentList = [];
        }
        hasError = false;
        notifyListeners();
        return true;
      } else {
        hasError = true;
        errorMessage = 'Failed to load payment gateways (${response.statusCode})';
        paymentList = [];
        notifyListeners();
        return false;
      }
    } on TimeoutException {
      setLoadingFalse();
      isLoaded = true;
      hasError = true;
      errorMessage = 'Network timeout while loading payment gateways. Please try again.';
      paymentList = [];
      notifyListeners();
      return false;
    } catch (e) {
      setLoadingFalse();
      isLoaded = true;
      hasError = true;
      errorMessage = 'Unable to load payment gateways. Please check connection.';
      paymentList = [];
      debugPrint('fetchGatewayList error: $e');
      notifyListeners();
      return false;
    }
  }

  //set clientId or secretId
  //==================>
  setKey(String methodName, int index) {
    print('selected method $methodName');
    switch (methodName) {
      case 'paypal':
        publicKey = paymentList[index]['client_id'];
        serverkey = paymentList[index]['secret_id'];
        isTestMode = paymentList[index]['test_mode'];
        print('client id is $publicKey');
        print('secret id is $serverkey');
        notifyListeners();
        break;

      case 'cashfree':
        publicKey = paymentList[index]['app_id'];
        serverkey = paymentList[index]['secret_key'];
        isTestMode = paymentList[index]['test_mode'];
        print('client id is $publicKey');
        print('secret id is $serverkey');
        notifyListeners();
        break;

      case 'flutterwave':
        publicKey = paymentList[index]['public_key'];
        serverkey = paymentList[index]['secret_key'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'instamojo':
        publicKey = paymentList[index]['client_id'];
        serverkey = paymentList[index]['client_secret'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'marcadopago':
        publicKey = paymentList[index]['client_id'];
        serverkey = paymentList[index]['client_secret'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'midtrans':
        publicKey = paymentList[index]['merchant_id'];
        serverkey = paymentList[index]['server_key'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'mollie':
        publicKey = paymentList[index]['public_key'];
        serverkey = '';
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'payfast':
        publicKey = paymentList[index]['merchant_id'];
        serverkey = paymentList[index]['merchant_key'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'paystack':
        publicKey = paymentList[index]['public_key'];
        serverkey = paymentList[index]['secret_key'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'paytm':
        publicKey = paymentList[index]['merchant_key'];
        serverkey = paymentList[index]['merchant_mid'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'razorpay':
        publicKey = paymentList[index]['api_key'];
        serverkey = paymentList[index]['api_secret'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'stripe':
        publicKey = paymentList[index]['public_key'];
        serverkey = paymentList[index]['secret_key'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'cinetpay':
        publicKey = paymentList[index]['site_id'];
        serverkey = paymentList[index]['app_key'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;
      case 'paytabs':
        paytabProfileId = paymentList[index]['profile_id'];
        serverkey = paymentList[index]['server_key'];
        isTestMode = paymentList[index]['test_mode'];

        print('GOOOG $paymentList');
        notifyListeners();
        break;

      case 'squareup':
        squareLocationId = paymentList[index]['location_id'];
        serverkey = paymentList[index]['access_token'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'billplz':
        publicKey = paymentList[index]['key'];
        serverkey = paymentList[index]['xsignature'];
        billPlzCollectionName = paymentList[index]['collection_name'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'zitopay':
        zitopayUserName = paymentList[index]['username'];
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'manual_payment':
        publicKey = '';
        serverkey = '';
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      case 'cash_on_delivery':
        publicKey = '';
        serverkey = '';
        isTestMode = paymentList[index]['test_mode'];
        notifyListeners();
        break;

      //switch end
    }
  }
}
