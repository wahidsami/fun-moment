import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/view/auth/login/login.dart';
import 'package:funmoments/view/auth/reset_password/reset_pass_otp_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;

class ResetPasswordService with ChangeNotifier {
  bool isloading = false;

  String? otpNumber;

  setOtp(newOtp) {
    otpNumber = newOtp;
    notifyListeners();
  }

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  Future<bool> sendOtp(dynamic email, BuildContext? context,
      {bool isFromOtpPage = false, http.Client? client}) async {
    try {
      final connectivityResult = await (Connectivity().checkConnectivity());
      if (connectivityResult == ConnectivityResult.none) {
        OthersHelper()
            .showToast("Please turn on your internet connection", Colors.black);
        return false;
      }
    } catch (e) {
      debugPrint('Connectivity check note: $e');
    }

    final header = {
      "Accept": "application/json",
      "Content-Type": "application/json",
    };
    final data = jsonEncode({
      'email': email,
      'purpose': 'password_reset',
    });

    setLoadingTrue();
    final httpClient = client ?? http.Client();
    final shouldClose = client == null;

    try {
      final response = await httpClient.post(
        Uri.parse('$baseApi/send-otp-in-mail'),
        headers: header,
        body: data,
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        notifyListeners();
        if (!isFromOtpPage && context != null) {
          Navigator.push(
            context,
            MaterialPageRoute<void>(
              builder: (BuildContext context) => ResetPassOtpPage(
                email: email,
              ),
            ),
          );
        }
        setLoadingFalse();
        return true;
      } else {
        try {
          final res = jsonDecode(response.body);
          final msg = res['message'] ?? res['msg'] ?? 'Failed to send OTP code';
          OthersHelper().showToast(msg.toString(), Colors.black);
        } catch (_) {
          OthersHelper().showToast(
              'Failed to send OTP code (${response.statusCode})', Colors.black);
        }
        setLoadingFalse();
        return false;
      }
    } catch (e) {
      debugPrint('Error sending password reset OTP: $e');
      OthersHelper().showToast('Failed to send verification code', Colors.black);
      setLoadingFalse();
      return false;
    } finally {
      if (shouldClose) {
        httpClient.close();
      }
    }
  }

  Future<bool> resetPassword(
    dynamic newPass,
    dynamic repeatNewPass,
    dynamic email,
    BuildContext? context, {
    String? resetToken,
    http.Client? client,
  }) async {
    if (newPass != repeatNewPass) {
      OthersHelper().showToast("Password didn't match", Colors.black);
      return false;
    }

    try {
      final connectivityResult = await (Connectivity().checkConnectivity());
      if (connectivityResult == ConnectivityResult.none) {
        OthersHelper()
            .showToast("Please turn on your internet connection", Colors.black);
        return false;
      }
    } catch (e) {
      debugPrint('Connectivity check note: $e');
    }

    final header = {
      "Accept": "application/json",
      "Content-Type": "application/json"
    };
    final Map<String, dynamic> payload = {
      'email': email,
      'password': newPass,
    };
    if (resetToken != null && resetToken.isNotEmpty) {
      payload['reset_token'] = resetToken;
    }

    setLoadingTrue();
    final httpClient = client ?? http.Client();
    final shouldClose = client == null;

    try {
      final response = await httpClient.post(
        Uri.parse('$baseApi/reset-password'),
        headers: header,
        body: jsonEncode(payload),
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        OthersHelper()
            .showToast("Password updated successfully", Colors.black);
        setLoadingFalse();

        if (context != null) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute<void>(
              builder: (BuildContext context) => const LoginPage(
                hasBackButton: false,
              ),
            ),
          );
        }
        return true;
      } else {
        try {
          final res = jsonDecode(response.body);
          final msg = res['message'] ?? res['msg'] ?? 'Password reset failed';
          OthersHelper().showToast(msg.toString(), Colors.black);
        } catch (_) {
          OthersHelper().showToast(
              'Password reset failed (${response.statusCode})', Colors.black);
        }
        setLoadingFalse();
        return false;
      }
    } catch (e) {
      debugPrint('Error executing resetPassword: $e');
      OthersHelper().showToast('Password reset failed', Colors.black);
      setLoadingFalse();
      return false;
    } finally {
      if (shouldClose) {
        httpClient.close();
      }
    }
  }
}
