import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:pusher_beams/pusher_beams.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/pay_services/stripe_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/view/home/landing_page.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../view/auth/signup/components/email_verify_page.dart';
import 'email_verify_service.dart';

class LoginService with ChangeNotifier {
  bool isloading = false;

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  Future<bool> login(email, pass, BuildContext context, bool keepLoggedIn,
      {isFromLoginPage = true}) async {
    var connection = await checkConnection();
    if (connection) {
      setLoadingTrue();
      var data = jsonEncode({
        'email': email,
        'password': pass,
      });
      var header = {
        //if header type is application/json then the data should be in jsonEncode method
        "Accept": "application/json",
        "Content-Type": "application/json"
      };

      var response = await http.post(Uri.parse('$baseApi/login'),
          body: data, headers: header);

      print(response.body);

      if (response.statusCode == 201) {
        if (isFromLoginPage) {
          OthersHelper()
              .showToast("Login successful", ConstantColors().successColor);
        }
        var responseData = jsonDecode(response.body);
        String token = responseData['token']?.toString() ?? '';
        int userId = int.tryParse(responseData['users']?['id']?.toString() ?? '') ?? 0;
        String state = responseData['users']?['state']?.toString() ?? '';
        String countryId =
            responseData['users']?['country_id']?.toString() ?? '';
        int userType = responseData['users']?['user_type'] != null
            ? int.tryParse(responseData['users']['user_type'].toString()) ?? 1
            : 1;

        if (responseData["users"] != null &&
            responseData["users"]["email_verified"]?.toString() != "1") {
          var isOtepSent =
              await Provider.of<EmailVerifyService>(context, listen: false)
                  .sendOtpForEmailValidation(
                      responseData["users"]["email"]?.toString() ?? '', context, token);

          if (isOtepSent) {
            Navigator.pushReplacement(
              context,
              MaterialPageRoute<void>(
                builder: (BuildContext context) => EmailVerifyPage(
                  email: responseData["users"]["email"]?.toString() ?? '',
                  token: token,
                  userId: userId,
                  state: state,
                  countryId: countryId,
                  userType: userType,
                ),
              ),
            );
          } else {
            "Otp send failed".tr().showToast();
          }
          setLoadingFalse();
          return false;
        }

        if (keepLoggedIn) {
          saveDetails(email, token, userId, state, countryId,
              pass: pass, keepLogin: keepLoggedIn, userType: userType);
        } else {
          setKeepLoggedInFalseSaveToken(token,
              userType: userType,
              userId: userId,
              email: email,
              state: state,
              countryId: countryId);
        }

        //start pusher
        //============>
        await Provider.of<PushNotificationService>(context, listen: false)
            .fetchPusherCredential(context: context);

        await Provider.of<ProfileService>(context, listen: false).fetchData();
        try {
          Provider.of<ProviderServiceManagementService>(context, listen: false).resetState();
        } catch (_) {}
        //start stripe
        //============>

        // =======>
        // Navigator.pushReplacement<void, void>(
        //   context,
        //   MaterialPageRoute<void>(
        //     builder: (BuildContext context) => const LandingPage(),
        //   ),
        // );
        setLoadingFalse();

        return true;
      } else {
        print(response.body);
        try {
          final res = jsonDecode(response.body);
          final msg = res['message'] ?? res['msg'] ?? 'Login failed';
          OthersHelper().showToast(msg.toString(), Colors.black);
        } catch (_) {
          OthersHelper().showToast('Login failed (${response.statusCode})', Colors.black);
        }
        setLoadingFalse();
        return false;
      }
    } else {
      //internet off
      return false;
    }
  }

  saveDetails(String email, String token, int userId, state, countryId,
      {String? pass, bool keepLogin = true, int userType = 1}) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    prefs.setString("email", email);
    prefs.setBool('keepLoggedIn', keepLogin);
    if (keepLogin) {
      prefs.setString("pass", pass ?? "");
    } else {
      prefs.remove("pass");
    }
    prefs.setString("token", token);
    prefs.setInt('userId', userId);
    prefs.setString("state", state);
    prefs.setString("countryId", countryId);
    prefs.setInt('userType', userType);
  }

  setKeepLoggedInFalseSaveToken(token,
      {int userType = 1,
      int? userId,
      String? email,
      String? state,
      String? countryId}) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    prefs.setBool('keepLoggedIn', false);
    prefs.setString("token", token);
    prefs.setInt('userType', userType);
    if (userId != null) prefs.setInt('userId', userId);
    if (email != null) prefs.setString('email', email);
    if (state != null) prefs.setString('state', state);
    if (countryId != null) prefs.setString('countryId', countryId);
  }
}
