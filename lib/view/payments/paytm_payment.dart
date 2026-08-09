// ignore_for_file: prefer_typing_uninitialized_variables

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/jobs_service/job_request_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../utils/common_helper.dart';

class PaytmPayment extends StatefulWidget {
  const PaytmPayment(
      {Key? key,
      required this.isFromOrderExtraAccept,
      required this.isFromWalletDeposite,
      required this.isFromHireJob})
      : super(key: key);

  final isFromOrderExtraAccept;
  final isFromWalletDeposite;
  final isFromHireJob;

  @override
  State<PaytmPayment> createState() => _PaytmPaymentState();
}

class _PaytmPaymentState extends State<PaytmPayment> {
  WebViewController? _controller;
  var successUrl;
  var failedUrl;

  @override
  void initState() {
    super.initState();
    successUrl =
        Provider.of<PlaceOrderService>(context, listen: false).successUrl;
    failedUrl =
        Provider.of<PlaceOrderService>(context, listen: false).cancelUrl;
  }

  String? html;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CommonHelper().appbarCommon('Paytm', context, () {
        Provider.of<PlaceOrderService>(context, listen: false)
            .doNext(context, 'failed', paymentFailed: true);
      }),
      body: WillPopScope(
        onWillPop: () async {
          await Provider.of<PlaceOrderService>(context, listen: false)
              .doNext(context, 'failed', paymentFailed: true);
          return false;
        },
        child: WebView(
          onWebViewCreated: (controller) {
            _controller = controller;

            var paytmHtmlString =
                Provider.of<PlaceOrderService>(context, listen: false)
                    .paytmHtmlForm as String;

            controller.loadHtmlString(paytmHtmlString);
          },
          initialUrl: "url",
          javascriptMode: JavascriptMode.unrestricted,
          navigationDelegate: (NavigationRequest request) async {
            print('current url is ${request.url}');
            print('success url is $successUrl');
            print('failed url is $failedUrl');
            if (request.url.contains(successUrl)) {
              //if payment is success, then the page is refreshing twice.
              //which is causing the screen pop twice.
              //So, this alreadySuccess = true trick will prevent that

              print('payment success');
              if (widget.isFromOrderExtraAccept == true) {
                Provider.of<OrderDetailsService>(context, listen: false)
                    .acceptOrderExtra(context);
              } else if (widget.isFromWalletDeposite) {
                Provider.of<WalletService>(context, listen: false)
                    .makeDepositeToWalletSuccess(context);
              } else if (widget.isFromHireJob) {
                Provider.of<JobRequestService>(context, listen: false)
                    .goToJobSuccessPage(context);
              } else {
                await Provider.of<PlaceOrderService>(context, listen: false)
                    .makePaymentSuccess(context);
              }

              return NavigationDecision.prevent;
            }
            if (request.url.contains('order-cancel-static')) {
              Provider.of<PlaceOrderService>(context, listen: false)
                  .doNext(context, 'failed', paymentFailed: true);

              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      ),
    );
  }
}
