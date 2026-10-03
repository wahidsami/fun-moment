import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/service/auth_services/login_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/home/landing_page.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;

import '../push_notification_service.dart';

enum OtpSendStatus {
  none,
  sent,
  timeout,
  networkError,
  serverError,
  validationError,
}

class EmailVerifyService with ChangeNotifier {
  bool isloading = false;

  bool verifyOtpLoading = false;

  OtpSendStatus lastOtpStatus = OtpSendStatus.none;
  String lastOtpMessage = '';
  String? lastOtpCode;

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  void resetOtpState() {
    lastOtpStatus = OtpSendStatus.none;
    lastOtpMessage = '';
    lastOtpCode = null;
    notifyListeners();
  }

  Future<bool> sendOtpForEmailValidation(
    dynamic email,
    BuildContext? context,
    dynamic token, {
    http.Client? client,
    Duration timeoutDuration = const Duration(seconds: 15),
  }) async {
    lastOtpStatus = OtpSendStatus.none;
    lastOtpMessage = '';
    lastOtpCode = null;

    try {
      final connectivityResult = await (Connectivity().checkConnectivity());
      if (connectivityResult == ConnectivityResult.none) {
        lastOtpStatus = OtpSendStatus.networkError;
        lastOtpMessage = "Please turn on your internet connection";
        OthersHelper().showToast(lastOtpMessage, Colors.black);
        notifyListeners();
        return false;
      }
    } catch (e) {
      // If connectivity check is unavailable (e.g. in tests), continue with HTTP
      debugPrint('Connectivity check note: $e');
    }

    final header = {
      "Accept": "application/json",
      "Content-Type": "application/json",
    };
    final data = jsonEncode({
      'email': email,
    });

    final httpClient = client ?? http.Client();
    final shouldCloseClient = client == null;

    try {
      final response = await httpClient
          .post(
            Uri.parse('$baseApi/send-otp-in-mail'),
            headers: header,
            body: data,
          )
          .timeout(timeoutDuration);

      if (response.statusCode == 201) {
        final decoded = jsonDecode(response.body);
        final otpNumber = decoded['otp']?.toString() ?? '';
        lastOtpCode = otpNumber;

        if (context != null) {
          try {
            Provider.of<ResetPasswordService>(context, listen: false)
                .setOtp(otpNumber);
          } catch (e) {
            debugPrint('ResetPasswordService context note: $e');
          }
        }

        debugPrint('otp is $otpNumber');
        lastOtpStatus = OtpSendStatus.sent;
        lastOtpMessage = 'OTP sent successfully';
        notifyListeners();
        return true;
      } else if (response.statusCode == 422) {
        lastOtpStatus = OtpSendStatus.validationError;
        lastOtpMessage = _extractErrorMessage(
          response.body,
          'Validation error sending verification code',
        );
        OthersHelper().showToast(lastOtpMessage, Colors.black);
        notifyListeners();
        return false;
      } else if (response.statusCode >= 500) {
        lastOtpStatus = OtpSendStatus.serverError;
        lastOtpMessage = _extractErrorMessage(
          response.body,
          'Server error sending verification code (${response.statusCode})',
        );
        OthersHelper().showToast(lastOtpMessage, Colors.black);
        notifyListeners();
        return false;
      } else {
        lastOtpStatus = OtpSendStatus.serverError;
        lastOtpMessage = _extractErrorMessage(
          response.body,
          'Failed to send OTP code (${response.statusCode})',
        );
        OthersHelper().showToast(lastOtpMessage, Colors.black);
        notifyListeners();
        return false;
      }
    } on TimeoutException catch (e) {
      debugPrint('TimeoutException in sendOtpForEmailValidation: $e');
      lastOtpStatus = OtpSendStatus.timeout;
      lastOtpMessage = 'Connection timed out while sending verification code';
      OthersHelper().showToast(lastOtpMessage, Colors.black);
      notifyListeners();
      return false;
    } on SocketException catch (e) {
      debugPrint('SocketException in sendOtpForEmailValidation: $e');
      lastOtpStatus = OtpSendStatus.networkError;
      lastOtpMessage = 'Network error: Unable to reach email service';
      OthersHelper().showToast(lastOtpMessage, Colors.black);
      notifyListeners();
      return false;
    } on http.ClientException catch (e) {
      debugPrint('ClientException in sendOtpForEmailValidation: $e');
      lastOtpStatus = OtpSendStatus.networkError;
      lastOtpMessage = 'Network error: Connection failed';
      OthersHelper().showToast(lastOtpMessage, Colors.black);
      notifyListeners();
      return false;
    } catch (e) {
      debugPrint('Unexpected error in sendOtpForEmailValidation: $e');
      lastOtpStatus = OtpSendStatus.networkError;
      lastOtpMessage = 'Failed to send verification code';
      OthersHelper().showToast(lastOtpMessage, Colors.black);
      notifyListeners();
      return false;
    } finally {
      if (shouldCloseClient) {
        httpClient.close();
      }
    }
  }

  String _extractErrorMessage(String body, String defaultMsg) {
    try {
      final res = jsonDecode(body);
      if (res is Map) {
        if (res['message'] != null && res['message'].toString().trim().isNotEmpty) {
          return res['message'].toString();
        }
        if (res['msg'] != null && res['msg'].toString().trim().isNotEmpty) {
          return res['msg'].toString();
        }
        if (res['errors'] is Map) {
          final errors = res['errors'] as Map;
          if (errors.isNotEmpty) {
            final firstVal = errors.values.first;
            if (firstVal is List && firstVal.isNotEmpty) {
              return firstVal.first.toString();
            }
            return firstVal.toString();
          }
        }
      }
      return defaultMsg;
    } catch (_) {
      return defaultMsg;
    }
  }

  verifyOtpAndLogin(enteredOtp, BuildContext context, email, token, userId,
      state, countryId, {int userType = 1}) async {
    var otpNumber =
        Provider.of<ResetPasswordService>(context, listen: false).otpNumber;
    if (otpNumber != null) {
      if (enteredOtp == otpNumber) {
        //Set Loading true
        verifyOtpLoading = true;
        notifyListeners();

        var header = {
          //if header type is application/json then the data should be in jsonEncode method
          "Accept": "application/json",
          "Content-Type": "application/json",
          "Authorization": "Bearer $token",
        };
        var data = jsonEncode({'user_id': userId, 'email_verified': 1});

        var response = await http.post(
            Uri.parse('$baseApi/user/send-otp-in-mail/success'),
            headers: header,
            body: data);

        //Set loading false
        verifyOtpLoading = false;
        notifyListeners();

        if (response.statusCode == 201) {
          //save the details for later login
          LoginService().saveDetails(email, token, userId, state, countryId, userType: userType);
          // );
          await Provider.of<ProfileService>(context, listen: false).fetchData();
          await Provider.of<PushNotificationService>(context, listen: false)
              .fetchPusherCredential(context: context);

          HomepageHelper.tabIndex.value = 0;
          Navigator.pushAndRemoveUntil(
            context,
            MaterialPageRoute<void>(
              builder: (BuildContext context) => const LandingPage(),
            ),
            (route) => false,
          );
        } else {
          debugPrint('Verify OTP response: ${response.body}');
          OthersHelper().showToast(
              'Your entered the otp correctly but something went wrong. Please try again later',
              Colors.black);
        }
      } else {
        OthersHelper().showToast("Otp didn't match", Colors.black);
      }
    } else {
      OthersHelper().showToast('Otp is null', Colors.black);
    }
  }
}

// Navigator.pushReplacement(
//           context,
//           MaterialPageRoute<void>(
//             builder: (BuildContext context) => ResetPasswordPage(
//               email: email,
//             ),
//           ),
//         );
