import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/view/auth/reset_password/reset_password_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ResetPasswordOtpService {
  Future<bool> checkOtp(
    dynamic enteredOtp,
    dynamic email,
    BuildContext? context, {
    http.Client? client,
  }) async {
    final trimmedOtp = enteredOtp?.toString().trim() ?? '';
    if (trimmedOtp.isEmpty) {
      OthersHelper().showToast("Please enter the 4 digit code", Colors.black);
      return false;
    }

    try {
      final connectivityResult = await Connectivity().checkConnectivity();
      if (connectivityResult == ConnectivityResult.none) {
        OthersHelper().showToast(
          "Please turn on your internet connection",
          Colors.black,
        );
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
      'otp': trimmedOtp,
    });

    final httpClient = client ?? http.Client();
    final shouldClose = client == null;

    try {
      final response = await httpClient.post(
        Uri.parse('$baseApi/verify-reset-otp'),
        headers: header,
        body: data,
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final res = jsonDecode(response.body);
        final resetToken = res['reset_token']?.toString();
        if (resetToken != null && resetToken.isNotEmpty) {
          if (context != null && context.mounted) {
            Navigator.pushReplacement(
              context,
              MaterialPageRoute<void>(
                builder: (BuildContext context) => ResetPasswordPage(
                  email: email,
                  resetToken: resetToken,
                ),
              ),
            );
          }
          return true;
        } else {
          OthersHelper().showToast("Invalid reset token received", Colors.black);
          return false;
        }
      } else {
        try {
          final res = jsonDecode(response.body);
          final msg = res['message'] ?? res['msg'] ?? "Otp didn't match";
          OthersHelper().showToast(msg.toString(), Colors.black);
        } catch (_) {
          OthersHelper().showToast("Otp didn't match", Colors.black);
        }
        return false;
      }
    } catch (e) {
      debugPrint('Error verifying reset OTP: $e');
      OthersHelper().showToast('Verification failed', Colors.black);
      return false;
    } finally {
      if (shouldClose) {
        httpClient.close();
      }
    }
  }
}
