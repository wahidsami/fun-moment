// ignore_for_file: avoid_print, prefer_typing_uninitialized_variables

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/jobs_service/job_request_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:http/http.dart' as http;

import '../../service/rtl_service.dart';
import '../utils/common_helper.dart';

class MolliePayment extends StatelessWidget {
  MolliePayment(
      {Key? key,
      required this.amount,
      required this.name,
      required this.phone,
      required this.email,
      required this.isFromOrderExtraAccept,
      required this.orderId,
      required this.isFromWalletDeposite,
      required this.isFromHireJob})
      : super(key: key);

  final amount;
  final name;
  final phone;
  final email;
  final isFromOrderExtraAccept;
  final isFromWalletDeposite;
  final isFromHireJob;
  final orderId;

  String? url;
  String? statusURl;
  @override
  Widget build(BuildContext context) {
    var successUrl =
        Provider.of<PlaceOrderService>(context, listen: false).successUrl ??
            'https://www.google.com/';

    Future.delayed(const Duration(microseconds: 600), () {
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();
    });
    return Scaffold(
      appBar: CommonHelper().appbarCommon('Midtrans', context, () {
        Provider.of<PlaceOrderService>(context, listen: false)
            .doNext(context, 'failed', paymentFailed: true);
      }),
      body: WillPopScope(
        onWillPop: () async {
          await Provider.of<PlaceOrderService>(context, listen: false)
              .doNext(context, 'failed', paymentFailed: true);
          return true;
        },
        child: FutureBuilder(
            future: waitForIt(context, successUrl),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasData) {
                return const Center(
                  child: Text('Loding failed.'),
                );
              }
              if (snapshot.hasError) {
                print(snapshot.error);
                return const Center(
                  child: Text('Loding failed.'),
                );
              }
              return WebView(
                initialUrl: url,
                javascriptMode: JavascriptMode.unrestricted,
                onPageStarted: (value) async {
                  var redirectUrl = successUrl;

                  if (value.contains(redirectUrl)) {
                    String status = await verifyPayment(context);
                    if (status == 'paid') {
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
                    }
                    if (status == 'open') {
                      await showDialog(
                          context: context,
                          builder: (ctx) {
                            return AlertDialog(
                              title: Text(
                                  lnProvider.getString('Payment cancelled!')),
                              content: Text(lnProvider
                                  .getString('Payment has been cancelled.')),
                            );
                          });
                    }
                    if (status == 'failed') {
                      await showDialog(
                          context: context,
                          builder: (ctx) {
                            return AlertDialog(
                              title:
                                  Text(lnProvider.getString('Payment failed!')),
                            );
                          });
                    }
                    if (status == 'expired') {
                      await showDialog(
                          context: context,
                          builder: (ctx) {
                            return AlertDialog(
                              title:
                                  Text(lnProvider.getString('Payment failed!')),
                              content: Text(lnProvider
                                  .getString('Payment has been expired.')),
                            );
                          });
                    }
                  }
                },
              );
            }),
      ),
    );
  }

  waitForIt(BuildContext context, successUrl) async {
    final publicKey =
        Provider.of<PaymentGatewayListService>(context, listen: false)
            .publicKey;

    // final publicKey = 'test_fVk76gNbAp6ryrtRjfAVvzjxSHxC2v';

    final url = Uri.parse('https://api.mollie.com/v2/payments');
    final header = {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Authorization": "Bearer $publicKey",
      // Above is API server key for the Midtrans account, encoded to base64
    };
    final currencyCode =
        Provider.of<RtlService>(context, listen: false).currencyCode;
    final response = await http.post(url,
        headers: header,
        body: jsonEncode({
          "amount": {"value": amount, "currency": currencyCode},
          "description": "Fun Moments payment",
          "redirectUrl": successUrl,
          "webhookUrl": successUrl, "metadata": 'mollieFunMoments$orderId',
          // "method": "creditcard",
        }));
    if (response.statusCode == 201) {
      this.url = jsonDecode(response.body)['_links']['checkout']['href'];
      print('url link is ${this.url}');
      statusURl = jsonDecode(response.body)['_links']['self']['href'];
      print(statusURl);
      return;
    } else {
      print(response.body);
    }

    return true;
  }

  verifyPayment(BuildContext context) async {
    final publicKey =
        Provider.of<PaymentGatewayListService>(context, listen: false)
            .publicKey;

    // final publicKey = 'test_fVk76gNbAp6ryrtRjfAVvzjxSHxC2v';

    final url = Uri.parse(statusURl as String);
    final header = {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Authorization": "Bearer $publicKey",
      // Above is API server key for the Midtrans account, encoded to base64
    };
    final response = await http.get(url, headers: header);
    print(jsonDecode(response.body)['status']);
    return jsonDecode(response.body)['status'];
  }
}
