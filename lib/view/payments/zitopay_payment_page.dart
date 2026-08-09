// ignore_for_file: avoid_print

import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform, TargetPlatform;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/jobs_service/job_request_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../utils/common_helper.dart';

class ZitopayPaymentPage extends StatefulWidget {
  const ZitopayPaymentPage(
      {Key? key,
      required this.userName,
      required this.amount,
      required this.isFromOrderExtraAccept,
      required this.isFromWalletDeposite,
      required this.isFromHireJob})
      : super(key: key);

  final userName;
  final amount;
  final isFromOrderExtraAccept;
  final isFromWalletDeposite;
  final isFromHireJob;

  @override
  _ZitopayPaymentPageState createState() => _ZitopayPaymentPageState();
}

class _ZitopayPaymentPageState extends State<ZitopayPaymentPage> {
  @override
  void initState() {
    super.initState();
    // Enable virtual display.
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) WebView.platform = AndroidWebView();
  }

  bool alreadySuccessful = false;

  late WebViewController controller;

  @override
  Widget build(BuildContext context) {
    Future.delayed(const Duration(microseconds: 600), () {
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();
    });

    return SafeArea(
      child: WillPopScope(
        onWillPop: () async {
          if (await controller.canGoBack()) {
            controller.goBack();
            return false;
          } else {
            Provider.of<PlaceOrderService>(context, listen: false)
                .doNext(context, 'failed', paymentFailed: true);
            return false;
          }
        },
        child: Scaffold(
          appBar: CommonHelper().appbarCommon('ZitoPay', context, () {
            Provider.of<PlaceOrderService>(context, listen: false)
                .doNext(context, 'failed', paymentFailed: true);
          }),
          body: WebView(
            javascriptMode: JavascriptMode.unrestricted,
            initialUrl:
                "https://zitopay.africa/sci/?currency=XAF&amount=${widget.amount}&receiver=${widget.userName}&success_url=https%3A%2F%2Fwww.google.com%2F&cancel_url=https%3A%2F%2Fpub.dev",
            onWebViewCreated: (controller) {
              this.controller = controller;
            },
            navigationDelegate: (NavigationRequest request) async {
              if (request.url.contains('https://www.google.com/')) {
                //if payment is success, then the page is refreshing twice.
                //which is causing the screen pop twice.
                //So, this alreadySuccess = true trick will prevent that
                if (alreadySuccessful != true) {
                  print('payment success');
                  if (widget.isFromOrderExtraAccept == true) {
                    await Provider.of<OrderDetailsService>(context,
                            listen: false)
                        .acceptOrderExtra(context);
                  } else if (widget.isFromWalletDeposite) {
                    await Provider.of<WalletService>(context, listen: false)
                        .makeDepositeToWalletSuccess(context);
                  } else if (widget.isFromHireJob) {
                    Provider.of<JobRequestService>(context, listen: false)
                        .goToJobSuccessPage(context);
                  } else {
                    await Provider.of<PlaceOrderService>(context, listen: false)
                        .makePaymentSuccess(context);
                  }
                }

                setState(() {
                  alreadySuccessful = true;
                });

                return NavigationDecision.prevent;
              }
              if (request.url.contains('https://pub.dev/')) {
                print('payment failed');
                Provider.of<PlaceOrderService>(context, listen: false)
                    .doNext(context, 'failed', paymentFailed: true);

                return NavigationDecision.prevent;
              }
              return NavigationDecision.navigate;
            },
          ),
        ),
      ),
    );
  }
}
