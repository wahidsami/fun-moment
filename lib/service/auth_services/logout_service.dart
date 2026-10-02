import 'dart:async';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:funmoments/service/auth_services/google_sign_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/jobs_service/my_jobs_service.dart';
import 'package:funmoments/service/live_chat/chat_list_service.dart';
import 'package:funmoments/service/live_chat/chat_message_service.dart';
import 'package:funmoments/service/my_orders_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/provider_availability_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/report_services/report_message_service.dart';
import 'package:funmoments/service/report_services/report_service.dart';
import 'package:funmoments/service/support_ticket/support_messages_service.dart';
import 'package:funmoments/service/support_ticket/support_ticket_service.dart';
import 'package:funmoments/service/book_confirmation_service.dart';
import 'package:funmoments/service/book_steps_service.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/filter_services_service.dart';
import 'package:funmoments/service/orders_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/view/auth/login/login.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class LogoutService with ChangeNotifier {
  bool isloading = false;

  void setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  void setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  void _safeReset<T extends ChangeNotifier>(
      BuildContext context, void Function(T provider) resetFn) {
    try {
      final provider = Provider.of<T>(context, listen: false);
      resetFn(provider);
    } catch (e) {
      debugPrint('LogoutService: safe reset of $T skipped: $e');
    }
  }

  Future<void> logout(BuildContext context) async {
    setLoadingTrue();

    try {
      final connection = await checkConnection().catchError((_) => false);
      if (connection) {
        // 1. Best-effort FCM Device Token unregistration
        try {
          final pushService =
              Provider.of<PushNotificationService>(context, listen: false);
          await pushService
              .removeDeviceTokenFromBackend()
              .timeout(const Duration(seconds: 3));
        } catch (e) {
          debugPrint('LogoutService: FCM token removal non-fatal error: $e');
        }

        // 2. Best-effort Server Logout API call
        try {
          final prefs = await SharedPreferences.getInstance();
          final token = prefs.getString('token');
          if (token != null && token.isNotEmpty) {
            final header = {
              "Accept": "application/json",
              "Authorization": "Bearer $token",
            };
            await http
                .post(Uri.parse('$baseApi/user/logout'), headers: header)
                .timeout(const Duration(seconds: 3));
          }
        } catch (e) {
          debugPrint('LogoutService: Server logout non-fatal error: $e');
        }
      }
    } catch (e) {
      debugPrint('LogoutService: Pre-teardown non-fatal error: $e');
    } finally {
      // 3. Guaranteed Local Session Teardown
      try {
        await GoogleSignInService().logOutFromGoogleLogin();
      } catch (e) {
        debugPrint('LogoutService: Google signout non-fatal: $e');
      }

      // Reset all user-scoped providers
      _safeReset<ProfileService>(context, (p) => p.setEverythingToDefault());
      _safeReset<ProviderAvailabilityService>(context, (p) => p.resetState());
      _safeReset<ProviderServiceManagementService>(
          context, (p) => p.resetState());
      _safeReset<MyOrdersService>(context, (p) => p.resetState());
      _safeReset<OrderDetailsService>(context, (p) => p.resetState());
      _safeReset<OrdersService>(context, (p) => p.resetState());
      _safeReset<FilterServicesService>(context, (p) => p.resetState());
      _safeReset<BookConfirmationService>(context, (p) => p.resetState());
      _safeReset<BookStepsService>(context, (p) => p.resetState());
      _safeReset<PlaceOrderService>(context, (p) => p.resetState());
      _safeReset<WalletService>(context, (p) => p.resetState());
      _safeReset<PushNotificationService>(context, (p) => p.resetState());
      _safeReset<ChatListService>(context, (p) => p.resetState());
      _safeReset<ChatMessagesService>(context, (p) => p.resetState());
      _safeReset<SupportTicketService>(context, (p) => p.resetState());
      _safeReset<SupportMessagesService>(context, (p) => p.resetState());
      _safeReset<ReportService>(context, (p) => p.resetState());
      _safeReset<ReportMessagesService>(context, (p) => p.resetState());
      _safeReset<MyJobsService>(context, (p) => p.resetState());

      // Clear all stored authentication & session keys
      await clear();

      HomepageHelper.tabIndex.value = 0;
      setLoadingFalse();

      // Safely dismiss any open dialog
      try {
        if (Navigator.of(context, rootNavigator: true).canPop()) {
          Navigator.of(context, rootNavigator: true).pop();
        }
      } catch (e) {
        debugPrint('LogoutService: Dialog pop non-fatal: $e');
      }

      // Guaranteed deterministic navigation to LoginPage with clean route stack
      navigatorKey.currentState?.pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (BuildContext context) => const LoginPage(
            hasBackButton: false,
          ),
        ),
        (route) => false,
      );
    }
  }

  // Clear all saved user auth & session keys, while preserving global app configuration
  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove("email");
    await prefs.remove("pass");
    await prefs.remove("token");
    await prefs.remove("userId");
    await prefs.remove("userType");
    await prefs.remove("state");
    await prefs.remove("countryId");
    await prefs.remove("keepLoggedIn");
    await prefs.remove("googleLogin");
    await prefs.remove("fbLogin");
    await prefs.remove("appleLogin");
    await prefs.remove("userName");
    await prefs.remove("userToken");
    await prefs.remove("appleId");
  }
}
