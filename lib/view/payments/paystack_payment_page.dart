import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/book_confirmation_service.dart';
import 'package:funmoments/service/booking_services/book_service.dart';
import 'package:funmoments/service/booking_services/personalization_service.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/jobs_service/job_request_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:http/http.dart' as http;

import '../../service/rtl_service.dart';
import '../utils/common_helper.dart';

class PaystackPaymentPage extends StatelessWidget {
  PaystackPaymentPage(
      {Key? key,
      required this.isFromOrderExtraAccept,
      required this.isFromWalletDeposite,
      required this.isFromHireJob})
      : super(key: key);

  String? url;
  final isFromOrderExtraAccept;
  final isFromWalletDeposite;
  final isFromHireJob;

  @override
  Widget build(BuildContext context) {
    Future.delayed(const Duration(microseconds: 600), () {
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();
    });
    ConstantColors cc = ConstantColors();

    return Scaffold(
      appBar: CommonHelper().appbarCommon('Paystack', context, () {
        Provider.of<PlaceOrderService>(context, listen: false)
            .doNext(context, 'failed', paymentFailed: true);
      }),
      body: WillPopScope(
        onWillPop: () async {
          await Provider.of<PlaceOrderService>(context, listen: false)
              .doNext(context, 'failed', paymentFailed: true);
          return false;
        },
        child: FutureBuilder(
            future: waitForIt(context, isFromOrderExtraAccept,
                isFromWalletDeposite, isFromHireJob),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (url == null) {
                return const Center(
                  child: Text('Loding failed.'),
                );
              }
              // if (snapshot.hasError) {
              //   print(snapshot.error);
              //   return const Center(
              //     child: Text('Loding failed.'),
              //   );
              // }
              return WebView(
                // onWebViewCreated: ((controller) {
                //   _controller = controller;
                // }),
                onWebResourceError: (error) =>
                    Provider.of<PlaceOrderService>(context, listen: false)
                        .doNext(context, 'failed', paymentFailed: true),
                initialUrl: url,
                javascriptMode: JavascriptMode.unrestricted,
                onPageFinished: (value) async {
                  // final title = await _controller.currentUrl();
                  // print(title);
                  print('on finished.........................$value');
                  final uri = Uri.parse(value);
                  final response = await http.get(uri);
                  // if (response.body.contains('PAYMENT ID')) {

                  if (response.body.contains('Payment Successful')) {
                    if (isFromOrderExtraAccept == true) {
                      Provider.of<OrderDetailsService>(context, listen: false)
                          .acceptOrderExtra(context);
                    } else if (isFromWalletDeposite) {
                      Provider.of<WalletService>(context, listen: false)
                          .makeDepositeToWalletSuccess(context);
                    } else if (isFromHireJob) {
                      Provider.of<JobRequestService>(context, listen: false)
                          .goToJobSuccessPage(context);
                    } else {
                      Provider.of<PlaceOrderService>(context, listen: false)
                          .makePaymentSuccess(context);
                    }

                    return;
                  }
                  if (response.body.contains('Declined')) {}
                },
                navigationDelegate: (navRequest) async {
                  print('nav req to .......................${navRequest.url}');
                  if (navRequest.url.contains('success')) {
                    if (isFromOrderExtraAccept == true) {
                      await Provider.of<OrderDetailsService>(context,
                              listen: false)
                          .acceptOrderExtra(context);
                    } else if (isFromWalletDeposite) {
                      Provider.of<WalletService>(context, listen: false)
                          .makeDepositeToWalletSuccess(context);
                    } else if (isFromHireJob) {
                      Provider.of<JobRequestService>(context, listen: false)
                          .goToJobSuccessPage(context);
                    } else {
                      await Provider.of<PlaceOrderService>(context,
                              listen: false)
                          .makePaymentSuccess(context);
                    }
                    return NavigationDecision.prevent;
                  }
                  if (navRequest.url.contains('failed')) {
                    Provider.of<PlaceOrderService>(context, listen: false)
                        .doNext(context, 'failed', paymentFailed: true);
                  }
                  return NavigationDecision.navigate;
                },

                // javascriptChannels: <JavascriptChannel>[
                //   // Set Javascript Channel to WebView
                //   JavascriptChannel(
                //       name: 'same',
                //       onMessageReceived: (javMessage) {
                //         print(javMessage.message);
                //         print('...........................................');
                //       }),
                // ].toSet(),
              );
            }),
      ),
    );
  }

  Future<void> waitForIt(BuildContext context, isFromOrderExtraAccept,
      isFromWalletDeposite, isFromHireJob) async {
    final uri = Uri.parse('https://api.paystack.co/transaction/initialize');

    String paystackSecretKey =
        Provider.of<PaymentGatewayListService>(context, listen: false)
                .serverkey ??
            '';

    var amount;

    String name;
    String phone;
    String email;
    String orderId;
    name = Provider.of<ProfileService>(context, listen: false)
            .profileDetails
            .userDetails
            .name ??
        'test';
    phone = Provider.of<ProfileService>(context, listen: false)
            .profileDetails
            .userDetails
            .phone ??
        '111111111';
    email = Provider.of<ProfileService>(context, listen: false)
            .profileDetails
            .userDetails
            .email ??
        'test@test.com';

    if (isFromOrderExtraAccept == true) {
      amount = Provider.of<OrderDetailsService>(context, listen: false)
          .selectedExtraPrice;

      amount = double.parse(amount).toStringAsFixed(0);
      amount = int.parse(amount);

      orderId = Provider.of<OrderDetailsService>(context, listen: false)
          .selectedExtraId
          .toString();
    } else if (isFromWalletDeposite) {
      amount = Provider.of<WalletService>(context, listen: false).amountToAdd;
      amount = double.parse(amount).toStringAsFixed(0);
      amount = int.parse(amount);

      orderId = DateTime.now().toString();
    } else if (isFromHireJob) {
      amount = Provider.of<JobRequestService>(context, listen: false)
          .selectedJobPrice;
      amount = double.parse(amount).toStringAsFixed(0);
      amount = int.parse(amount);

      orderId = DateTime.now().toString();
    } else {
      var bcProvider =
          Provider.of<BookConfirmationService>(context, listen: false);
      var pProvider =
          Provider.of<PersonalizationService>(context, listen: false);
      var bookProvider = Provider.of<BookService>(context, listen: false);

      if (pProvider.isOnline == 0) {
        amount = bcProvider.totalPriceAfterAllcalculation.toStringAsFixed(0);
        amount = int.parse(amount);
      } else {
        amount = bcProvider.totalPriceOnlineServiceAfterAllCalculation
            .toStringAsFixed(0);
        amount = int.parse(amount);
      }

      orderId = Provider.of<PlaceOrderService>(context, listen: false).orderId;

      email = bookProvider.email ?? '';
    }

    print(orderId);

    final header = {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Authorization": "Bearer $paystackSecretKey",
      // Above is API server key for the Midtrans account, encoded to base64
    };
    final currencyCode =
        Provider.of<RtlService>(context, listen: false).currencyCode;

    // final orderId = Random().nextInt(23000).toInt();
    final response = await http.post(uri,
        headers: header,
        body: jsonEncode({
          "amount": amount * 100,
          "currency": currencyCode,
          "email": email,
          "reference_id": orderId.toString(),
          "callback_url": "http://success.com",
          "metadata": {"cancel_action": "http://failed.com"}
        }));
    print(response.body);
    if (response.statusCode == 200) {
      url = jsonDecode(response.body)['data']['authorization_url'];
      print(url);
      return;
    }

    // print(response.statusCode);
    // if (response.statusCode == 201) {
    // this.url =
    //     'https://sandbox.payfast.co.za/eng/process?merchant_id=${selectedGateaway.merchantId}&merchant_key=${selectedGateaway.merchantKey}&amount=$amount&item_name=GrenmartGroceries';
    // //   return;
    // // }

    // return true;
  }

  Future<bool> verifyPayment(String url) async {
    final uri = Uri.parse(url);
    final response = await http.get(uri);
    print(response.body.contains('Payment Completed'));
    return response.body.contains('Payment Completed');
  }
}
