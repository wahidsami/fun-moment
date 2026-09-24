// ignore_for_file: avoid_print, prefer_typing_uninitialized_variables

import 'dart:async';
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

class PayTabsPayment extends StatefulWidget {
  const PayTabsPayment(
      {Key? key,
      required this.amount,
      required this.name,
      required this.phone,
      required this.email,
      required this.orderId,
      required this.isFromOrderExtraAccept,
      required this.isFromWalletDeposite,
      required this.isFromHireJob})
      : super(key: key);

  final amount;
  final name;
  final phone;
  final email;
  final isFromHireJob;

  final orderId;
  final isFromOrderExtraAccept;
  final isFromWalletDeposite;

  @override
  State<PayTabsPayment> createState() => _PayTabsPaymentState();
}

class _PayTabsPaymentState extends State<PayTabsPayment> {
  String? url;
  bool _isProcessed = false;

  void _handleFailure() {
    if (_isProcessed) return;
    _isProcessed = true;
    Provider.of<PlaceOrderService>(context, listen: false)
        .doNext(context, 'failed', paymentFailed: true);
  }

  @override
  Widget build(BuildContext context) {
    Future.delayed(const Duration(microseconds: 600), () {
      Provider.of<PlaceOrderService>(context, listen: false).setLoadingFalse();
    });

    return Scaffold(
      appBar: CommonHelper().appbarCommon('PayTabs', context, () {
        _handleFailure();
      }),
      body: WillPopScope(
        onWillPop: () async {
          _handleFailure();
          return false;
        },
        child: FutureBuilder(
            future: waitForIt(context),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasData || snapshot.hasError || url == null) {
                return const Center(
                  child: Text('Loading failed.'),
                );
              }
              final controller = WebViewController()
                ..setJavaScriptMode(JavaScriptMode.unrestricted)
                ..setNavigationDelegate(
                  NavigationDelegate(
                    onWebResourceError: (error) {
                      _handleFailure();
                    },
                    onPageFinished: (value) async {},
                    onPageStarted: (value) async {
                      if (!value.contains('result')) {
                        return;
                      }
                      if (_isProcessed) return;
                      bool paySuccess = await verifyPayment(value);

                      if (_isProcessed) return;

                      if (paySuccess) {
                        _isProcessed = true;
                        if (widget.isFromOrderExtraAccept == true) {
                          await Provider.of<OrderDetailsService>(context,
                                  listen: false)
                              .acceptOrderExtra(context);
                        } else if (widget.isFromWalletDeposite) {
                          await Provider.of<WalletService>(context,
                                  listen: false)
                              .makeDepositeToWalletSuccess(context);
                        } else if (widget.isFromHireJob) {
                          Provider.of<JobRequestService>(context,
                                  listen: false)
                              .goToJobSuccessPage(context);
                        } else {
                          await Provider.of<PlaceOrderService>(context,
                                  listen: false)
                              .makePaymentSuccess(context);
                        }
                        return;
                      }
                      _handleFailure();
                    },
                    onNavigationRequest: (navRequest) async {
                      return NavigationDecision.navigate;
                    },
                  ),
                )
                ..loadRequest(Uri.parse(url!));
              return WebViewWidget(controller: controller);
            }),
      ),
    );
  }

  waitForIt(BuildContext context) async {
    String profileId =
        Provider.of<PaymentGatewayListService>(context, listen: false)
            .paytabProfileId;
    String serverkey =
        Provider.of<PaymentGatewayListService>(context, listen: false)
            .serverkey;
    final currencyCode =
        Provider.of<RtlService>(context, listen: false).currencyCode;

    final requestUrl = Uri.parse('https://secure.paytabs.sa/payment/request');
    final header = {
      "Content-Type": "application/json",
      "Authorization": serverkey,
    };
    final response = await http.post(requestUrl,
        headers: header,
        body: json.encode({
          "profile_id": int.tryParse(profileId) ?? 0,
          "tran_type": "sale",
          "tran_class": "ecom",
          "cart_id": widget.orderId.toString(),
          "cart_description": "Fun Moments payment",
          "cart_currency": currencyCode,
          "cart_amount": widget.amount,
        }));

    if (response.statusCode == 200) {
      final resBody = jsonDecode(response.body);
      if (resBody is Map && resBody['redirect_url'] != null) {
        url = resBody['redirect_url'];
        return;
      }
    }

    return true;
  }

  Future<bool> verifyPayment(String resultUrl) async {
    try {
      final uri = Uri.parse(resultUrl);
      final response = await http.get(uri);
      return response.body.contains('successful');
    } catch (_) {
      return false;
    }
  }
}
