// ignore_for_file: prefer_typing_uninitialized_variables

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/jobs_service/job_request_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:http/http.dart' as http;

class BillplzPayment extends StatelessWidget {
  BillplzPayment(
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
  late WebViewController _controller;
  @override
  Widget build(BuildContext context) {
    Future.delayed(const Duration(microseconds: 600), () {
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();
    });

    return Scaffold(
      appBar: CommonHelper().appbarCommon('BillPlz', context, () {
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
            future: waitForIt(context),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(
                  child: CircularProgressIndicator(),
                );
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
                // onWebViewCreated: ((controller) {
                //   _controller = controller;
                // }),
                onWebResourceError: (error) =>
                    Provider.of<PlaceOrderService>(context, listen: false)
                        .doNext(context, 'failed', paymentFailed: true),
                initialUrl: url,
                javascriptMode: JavascriptMode.unrestricted,
                onPageFinished: (value) async {
                  verifyPayment(value, context, isFromOrderExtraAccept,
                      isFromWalletDeposite, isFromHireJob);
                },
              );
            }),
      ),
    );
  }

  JavascriptChannel _extractDataJSChannel(BuildContext context) {
    return JavascriptChannel(
      name: 'Flutter',
      onMessageReceived: (JavascriptMessage message) {
        String pageBody = message.message;
        print('page body: $pageBody');
      },
    );
  }

  waitForIt(BuildContext context) async {
    // String orderId =
    //     Provider.of<PlaceOrderService>(context, listen: false).orderId;

    final url = Uri.parse('https://www.billplz-sandbox.com/api/v3/bills');
    final username =
        Provider.of<PaymentGatewayListService>(context, listen: false)
                .publicKey ??
            '';

    final collectionName =
        Provider.of<PaymentGatewayListService>(context, listen: false)
                .billPlzCollectionName ??
            '';
    final basicAuth =
        'Basic ${base64Encode(utf8.encode('$username:$username'))}';
    final header = {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Authorization": basicAuth,
      // Above is API server key for the Midtrans account, encoded to base64
    };
    final response = await http.post(url,
        headers: header,
        body: jsonEncode({
          "collection_id": collectionName,
          "description": "Fun Moments payment",
          "email": email,
          "name": name,
          "amount": "${double.parse(amount) * 100}",
          "reference_1_label": "Bank Code",
          "reference_1": "BP-FKR01",
          "callback_url": "https://funmoments.sa"
        }));
    print(response.statusCode);
    if (response.statusCode == 200) {
      this.url = jsonDecode(response.body)["url"];
      return;
    }

    return true;
  }
}

Future verifyPayment(String url, BuildContext context, isFromOrderExtraAccept,
    isFromWalletDeposite, isFromHireJob) async {
  final uri = Uri.parse(url);
  final response = await http.get(uri);
  if (response.body.contains('paid')) {
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
  if (response.body.contains('your payment was not')) {
    OthersHelper().showSnackBar(context, 'Payment failed', Colors.red);
    Navigator.pop(context);
    return;
  }
}
