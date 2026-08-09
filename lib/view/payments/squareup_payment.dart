// ignore_for_file: prefer_typing_uninitialized_variables

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:http/http.dart' as http;

import '../../service/rtl_service.dart';
import '../utils/common_helper.dart';

class SquareUpPayment extends StatelessWidget {
  SquareUpPayment(
      {Key? key,
      required this.amount,
      required this.name,
      required this.phone,
      required this.email,
      required this.isFromOrderExtraAccept,
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

  String? url;
  @override
  Widget build(BuildContext context) {
    Future.delayed(const Duration(microseconds: 600), () {
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();
    });
    return Scaffold(
      appBar: CommonHelper().appbarCommon('SquareUp', context, () {
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
            future: waitForIt(context),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(
                  child: CircularProgressIndicator(),
                );
              }

              if (snapshot.hasError) {
                print(snapshot.error);
                return const Center(
                  child: Text('Loding failed.'),
                );
              }
              if (snapshot.hasData) {
                return WebView(
                  initialUrl: url,
                  javascriptMode: JavascriptMode.unrestricted,
                  navigationDelegate: (NavigationRequest request) async {
                    print('navigation delegate link ' + request.url);
                    if (request.url.contains('https://funmoments.sa')) {
                      // String status = await verifyPayment(request.url);
                      // if (status == 'paid') {
                      //   await Provider.of<PlaceOrderService>(context, listen: false)
                      //       .makePaymentSuccess(context);
                      // }
                      // if (status == 'open') {
                      //   await showDialog(
                      //       context: context,
                      //       builder: (ctx) {
                      //         return const AlertDialog(
                      //           title: Text('Payment cancelled!'),
                      //           content: Text('Payment has been cancelled.'),
                      //         );
                      //       });
                      //   Navigator.pop(context);
                      // }
                      // if (status == 'failed') {
                      //   await showDialog(
                      //       context: context,
                      //       builder: (ctx) {
                      //         return const AlertDialog(
                      //           title: Text('Payment failed!'),
                      //         );
                      //       });
                      //   Navigator.pop(context);
                      // }
                      // if (status == 'expired') {
                      //   await showDialog(
                      //       context: context,
                      //       builder: (ctx) {
                      //         return const AlertDialog(
                      //           title: Text('Payment failed!'),
                      //           content: Text('Payment has been expired.'),
                      //         );
                      //       });
                      //   Navigator.pop(context);
                      // }

                      return NavigationDecision.prevent;
                    }
                    if (request.url.contains('https://pub.dev/')) {
                      Provider.of<PlaceOrderService>(context, listen: false)
                          .doNext(context, 'failed', paymentFailed: true);

                      return NavigationDecision.prevent;
                    }
                    return NavigationDecision.navigate;
                  },
                );
              } else {
                return const Text('Something went wrong');
              }
            }),
      ),
    );
  }

  Future waitForIt(BuildContext context) async {
    final secretKey =
        Provider.of<PaymentGatewayListService>(context, listen: false)
            .serverkey;

    final locationId =
        Provider.of<PaymentGatewayListService>(context, listen: false)
            .squareLocationId;
    final currencyCode =
        Provider.of<RtlService>(context, listen: false).currencyCode;

    // const secretKey =
    //     'EAAAEDsGeASjEG2t6YD1XqJxdyMXEJMS9m50rukk07akibxyMeCTjV2UHwdIsTtl';
    // const locationId = 'LP6DRN3R0SBRF';

    // String orderId =
    //     Provider.of<PlaceOrderService>(context, listen: false).orderId;

    final url = Uri.parse(
        'https://connect.squareupsandbox.com/v2/online-checkout/payment-links');
    final header = {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Authorization": 'Bearer $secretKey',
    };

    final response = await http.post(url,
        headers: header,
        body: jsonEncode({
          "description": "Fun Moments payment",
          "idempotency_key": DateTime.now().toString(),
          "quick_pay": {
            "location_id": locationId,
            "name": "Fun Moments payment",
            "price_money": {"amount": amount, "currency": currencyCode}
          },
          "payment_note": "Fun Moments payment",
          "redirect_url": "https://funmoments.sa/",
          "pre_populated_data": {"buyer_email": email}
        }));
    print(response.body);
    if (response.statusCode == 200) {
      this.url = jsonDecode(response.body)['payment_link']['url'];
      print(this.url);
      return url;
    }

    return true;
  }

  Future<bool> verifyPayment(String url) async {
    final uri = Uri.parse(url);
    final response = await http.get(uri);
    print(response.body.contains('successful'));
    return response.body.contains('successful');
  }
}
