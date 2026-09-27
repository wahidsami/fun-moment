// ignore_for_file: avoid_print, prefer_typing_uninitialized_variables

import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:http/http.dart' as http;

import '../../service/booking_services/place_order_service.dart';
import '../../service/jobs_service/job_request_service.dart';
import '../../service/order_details_service.dart';
import '../../service/wallet_service.dart';
import '../utils/common_helper.dart';
import '../utils/others_helper.dart';

class PayTabsPayment extends StatefulWidget {
  const PayTabsPayment({
    Key? key,
    required this.amount,
    required this.name,
    required this.phone,
    required this.email,
    required this.orderId,
    required this.isFromOrderExtraAccept,
    required this.isFromWalletDeposite,
    required this.isFromHireJob,
  }) : super(key: key);

  final dynamic amount;
  final dynamic name;
  final dynamic phone;
  final dynamic email;
  final dynamic orderId;
  final bool isFromOrderExtraAccept;
  final bool isFromWalletDeposite;
  final bool isFromHireJob;

  @override
  State<PayTabsPayment> createState() => _PayTabsPaymentState();
}

class _PayTabsPaymentState extends State<PayTabsPayment> {
  String? _paymentUrl;
  String? _tranRef;
  bool _isLoading = true;
  bool _isVerifying = false;
  bool _isProcessed = false;
  String? _errorMessage;
  WebViewController? _webViewController;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initiatePaymentSession();
    });
  }

  /// Request the backend to securely initiate a PayTabs session.
  /// Server-side credentials are used; secrets are never exposed on mobile.
  Future<void> _initiatePaymentSession() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final initiateUrl = Uri.parse('$baseApi/user/paytabs/initiate');
      final headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      };

      final parsedOrderId = int.tryParse(widget.orderId.toString()) ?? widget.orderId;
      final body = jsonEncode({
        'order_id': parsedOrderId,
      });

      debugPrint('[PayTabs] Initiating checkout for order $parsedOrderId');
      final response = await http
          .post(initiateUrl, headers: headers, body: body)
          .timeout(const Duration(seconds: 30));

      final data = jsonDecode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        final redirectUrl = data['redirect_url'] as String?;
        final tranRef = data['tran_ref'] as String?;

        if (redirectUrl != null && redirectUrl.isNotEmpty) {
          _paymentUrl = redirectUrl;
          _tranRef = tranRef;
          _setupWebViewController(redirectUrl);
          setState(() {
            _isLoading = false;
          });
          return;
        }
      }

      // If backend reports order is already paid, fast-path to success
      if (data['already_paid'] == true) {
        debugPrint('[PayTabs] Order is already paid on server.');
        _handleSuccess();
        return;
      }

      final errMsg = data['message'] ?? 'Failed to initialize secure payment session.';
      setState(() {
        _isLoading = false;
        _errorMessage = errMsg;
      });
    } catch (e) {
      debugPrint('[PayTabs] Exception during session initiation: $e');
      setState(() {
        _isLoading = false;
        _errorMessage = 'Network error contacting payment server. Please check your connection.';
      });
    }
  }

  void _setupWebViewController(String initialUrl) {
    _webViewController = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onWebResourceError: (error) {
            debugPrint('[PayTabs WebView] Resource error: ${error.description}');
          },
          onPageStarted: (url) {
            debugPrint('[PayTabs WebView] Page started: $url');
            _inspectNavigation(url);
          },
          onPageFinished: (url) {
            debugPrint('[PayTabs WebView] Page finished: $url');
            _inspectNavigation(url);
          },
          onNavigationRequest: (navRequest) {
            debugPrint('[PayTabs WebView] Nav request: ${navRequest.url}');
            _inspectNavigation(navRequest.url);
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(initialUrl));
  }

  /// Inspect the current URL for return redirect or transaction reference.
  void _inspectNavigation(String url) {
    if (_isProcessed || _isVerifying) return;

    final uri = Uri.tryParse(url);
    if (uri == null) return;

    final path = uri.path.toLowerCase();
    final isReturnUrl = path.contains('/paytabs/return') || path.contains('paytabs-return');
    final hasTranRef = uri.queryParameters.containsKey('tranRef') ||
        uri.queryParameters.containsKey('tran_ref');

    if (isReturnUrl || hasTranRef) {
      final tranRef = uri.queryParameters['tranRef'] ??
          uri.queryParameters['tran_ref'] ??
          _tranRef;
      final respStatus = uri.queryParameters['respStatus'] ?? '';
      final respMessage = uri.queryParameters['respMessage'];

      debugPrint('[PayTabs] Detected return: tranRef=$tranRef, respStatus=$respStatus');

      // If explicitly declined or cancelled
      if (respStatus == 'D' || respStatus == 'C') {
        _handleFailure(message: respMessage ?? 'Payment was declined or cancelled.');
        return;
      }

      if (tranRef != null && tranRef.isNotEmpty) {
        _verifyWithBackend(tranRef);
      }
    }
  }

  /// Authoritative server-side verification.
  /// The client NEVER decides payment success on its own.
  Future<void> _verifyWithBackend(String tranRef) async {
    if (_isProcessed || _isVerifying) return;

    setState(() {
      _isVerifying = true;
    });

    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token') ?? '';

      final verifyUrl = Uri.parse('$baseApi/user/paytabs/verify-transaction');
      final headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      };

      final parsedOrderId = int.tryParse(widget.orderId.toString()) ?? widget.orderId;
      final body = jsonEncode({
        'order_id': parsedOrderId,
        'tran_ref': tranRef,
      });

      debugPrint('[PayTabs] Verifying on backend: order=$parsedOrderId, tran_ref=$tranRef');
      final response = await http
          .post(verifyUrl, headers: headers, body: body)
          .timeout(const Duration(seconds: 40));

      final data = jsonDecode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        if (data['payment_status'] == 'complete') {
          debugPrint('[PayTabs] Server confirmed payment complete!');
          _handleSuccess();
          return;
        }
      }

      final errMsg = data['message'] ?? 'Payment verification failed.';
      debugPrint('[PayTabs] Verification rejected by server: $errMsg');
      _handleFailure(message: errMsg);
    } catch (e) {
      debugPrint('[PayTabs] Exception during backend verification: $e');
      _handleFailure(message: 'Error confirming payment with server. Please check your order history.');
    } finally {
      if (mounted) {
        setState(() {
          _isVerifying = false;
        });
      }
    }
  }

  void _handleSuccess() {
    if (_isProcessed) return;
    _isProcessed = true;

    OthersHelper().showToast(
      'Payment successfully verified!',
      Colors.green,
    );

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
      Provider.of<PlaceOrderService>(context, listen: false)
          .doNext(context, 'Complete');
    }
  }

  void _handleFailure({String? message}) {
    if (_isProcessed) return;
    _isProcessed = true;

    if (message != null && message.isNotEmpty) {
      OthersHelper().showToast(message, Colors.redAccent);
    }

    Provider.of<PlaceOrderService>(context, listen: false)
        .doNext(context, 'failed', paymentFailed: true);
  }

  Future<bool> _onWillPop() async {
    if (_isProcessed) return true;

    final shouldLeave = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Payment?'),
        content: const Text(
          'If you have already submitted your payment details, please check status instead of cancelling.',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(ctx, false);
              if (_tranRef != null && _tranRef!.isNotEmpty) {
                _verifyWithBackend(_tranRef!);
              } else {
                _initiatePaymentSession();
              }
            },
            child: const Text('Check Status'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(ctx, false);
            },
            child: const Text('Continue Payment'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(ctx, true);
            },
            child: const Text(
              'Cancel',
              style: TextStyle(color: Colors.red),
            ),
          ),
        ],
      ),
    );

    if (shouldLeave == true) {
      _handleFailure(message: 'Payment cancelled.');
      return false;
    }

    return false;
  }

  @override
  Widget build(BuildContext context) {
    return WillPopScope(
      onWillPop: _onWillPop,
      child: Scaffold(
        appBar: CommonHelper().appbarCommon('PayTabs', context, () {
          _onWillPop();
        }),
        body: Stack(
          children: [
            if (_errorMessage != null)
              Center(
                child: Padding(
                  padding: const EdgeInsets.all(24.0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(
                        Icons.error_outline,
                        color: Colors.redAccent,
                        size: 48,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        _errorMessage!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 15),
                      ),
                      const SizedBox(height: 24),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          ElevatedButton(
                            onPressed: _initiatePaymentSession,
                            child: const Text('Retry'),
                          ),
                          const SizedBox(width: 12),
                          OutlinedButton(
                            onPressed: () {
                              _handleFailure(message: 'Payment cancelled.');
                            },
                            child: const Text('Cancel'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              )
            else if (_paymentUrl != null && _webViewController != null)
              WebViewWidget(controller: _webViewController!)
            else
              const SizedBox.shrink(),

            // Loading / initiating overlay
            if (_isLoading)
              Container(
                color: Colors.black54,
                child: const Center(
                  child: Card(
                    margin: EdgeInsets.symmetric(horizontal: 32),
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          CircularProgressIndicator(),
                          SizedBox(height: 16),
                          Text(
                            'Connecting to secure payment gateway...',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 14),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),

            // Server-side verification in progress overlay
            if (_isVerifying)
              Container(
                color: Colors.black87,
                child: const Center(
                  child: Card(
                    margin: EdgeInsets.symmetric(horizontal: 32),
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          CircularProgressIndicator(color: Colors.green),
                          SizedBox(height: 16),
                          Text(
                            'Verifying payment with bank...\nPlease do not close this screen.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
