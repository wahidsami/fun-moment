// ignore_for_file: prefer_typing_uninitialized_variables

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/jobs_service/job_request_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:http/http.dart' as http;

import '../../service/rtl_service.dart';
import '../utils/common_helper.dart';

class MidtransPayment extends StatelessWidget {
  MidtransPayment(
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
                initialUrl: url,
                javascriptMode: JavascriptMode.unrestricted,
                onPageFinished: (value) async {
                  if (value.contains('success')) {
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
                },
              );
            }),
      ),
    );
  }

  waitForIt(BuildContext context) async {
    final url =
        Uri.parse('https://app.sandbox.midtrans.com/snap/v1/transactions');

    final clientKey =
        Provider.of<PaymentGatewayListService>(context, listen: false)
                .publicKey ??
            '';
    final serverKey =
        Provider.of<PaymentGatewayListService>(context, listen: false)
                .serverkey ??
            '';
    final currencyCode =
        Provider.of<RtlService>(context, listen: false).currencyCode;

    final basicAuth =
        'Basic ${base64Encode(utf8.encode('$serverKey:$clientKey'))}';
    final header = {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Authorization": basicAuth,
      // Above is API server key for the Midtrans account, encoded to base64
    };
    final response = await http.post(url,
        headers: header,
        body: jsonEncode({
          "transaction_details": {
            "order_id": DateTime.now().toString(),
            "gross_amount": amount,
            'currency': currencyCode
          },
          "credit_card": {"secure": true},
          "customer_details": {
            "first_name": name,
            "email": email,
            "phone": phone,
          }
        }));
    print(response.body);
    if (response.statusCode == 201) {
      this.url = jsonDecode(response.body)['redirect_url'];
      return;
    }

    return true;
  }
}
