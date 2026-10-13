// ignore_for_file: use_build_context_synchronously

import 'dart:convert';

// import 'package:firebase_auth/firebase_auth.dart';
import 'package:funmoments/helper/extension/context_extension.dart';

import '../../view/utils/constant_colors.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';
import '../../view/auth/signup/components/terms_conditions_modal.dart';
import '../../view/utils/others_helper.dart';
import '../common_service.dart';
import 'package:http/http.dart' as http;

import '../profile_service.dart';
import '../push_notification_service.dart';

class AppleSignInService with ChangeNotifier {
  bool isloading = false;

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  Future appleLogin(BuildContext context, {autoLogin = false}) async {
    debugPrint("trying to sign in using apple id".toString());
    final credential = await SignInWithApple.getAppleIDCredential(
      scopes: [
        AppleIDAuthorizationScopes.email,
        AppleIDAuthorizationScopes.fullName,
      ],
    );
    if (credential.userIdentifier != null) {
      await socialLogin(
        credential.email,
        (credential.givenName ?? "") +
            (credential.givenName != null ? ' ' : '') +
            (credential.familyName ?? ''),
        credential.userIdentifier,
        credential.userIdentifier,
        credential.identityToken,
        context,
        autoLogin,
      );

      return true;
    } else {
      OthersHelper().showToast('Something went wrong', Colors.black);
      notifyListeners();
      return false;
    }
    // } catch (e) {}
  }

//Logout from google ====>

  Future<bool> socialLogin(
      email, username, id, appleId, userToken, BuildContext context, autoLogin,
      {bool isAppleLogin = true,
      int? termsVersionId,
      String? termsLanguage,
      http.Client? client}) async {
    var connection = await checkConnection();
    if (connection) {
      if (isAppleLogin == true) {
        setLoadingTrue();
      }
      final Map<String, dynamic> payload = {
        'email': email,
        'displayName': username,
        'id': id,
        'isApple': 1,
      };
      if (termsVersionId != null) {
        payload['terms_version_id'] = termsVersionId;
      }
      if (termsLanguage != null && termsLanguage.isNotEmpty) {
        payload['terms_language'] = termsLanguage;
      }
      String data = jsonEncode(payload);

      var header = {
        "Accept": "application/json",
        "Content-Type": "application/json",
      };
      debugPrint(data.toString());

      final httpClient = client ?? http.Client();
      final shouldClose = client == null;

      try {
        var response = await httpClient.post(Uri.parse('$baseApi/social/login'),
            body: data, headers: header);
        print(response.statusCode);

      if (response.statusCode == 200) {
        print(response.body);

        String token = jsonDecode(response.body)['token'];
        int userId = jsonDecode(response.body)['users']['id'];
        String username = jsonDecode(response.body)['users']['username'];
        String email = jsonDecode(response.body)['users']['email'];
        await saveDetailsAfterSocialLogin(
            email, username, token, userId, userToken, isAppleLogin, appleId);
        await Provider.of<ProfileService>(context, listen: false)
            .getProfileDetails();
        await Provider.of<PushNotificationService>(context, listen: false)
            .fetchPusherCredential(context: context);
        setLoadingFalse();

        print(response.body);
        context.popFalse;
        return true;
      } else if (response.statusCode == 422) {
        debugPrint(response.body);
        try {
          final res = jsonDecode(response.body);
          if (res is Map && res['errors'] is Map && res['errors'].containsKey('terms_version_id')) {
            // First-time Apple account creation requires buyer terms acceptance
            setLoadingFalse();
            final acceptedTerms = await showTermsConditionsModal(
              context,
              role: 'buyer',
            );

            if (acceptedTerms == null) {
              debugPrint('[AppleSignIn] Terms acceptance declined for new Apple account.');
              return false;
            }

            // Retry social login with accepted immutable version metadata
            return await socialLogin(
              email,
              username,
              id,
              appleId,
              userToken,
              context,
              autoLogin,
              isAppleLogin: isAppleLogin,
              termsVersionId: acceptedTerms.versionId,
              termsLanguage: acceptedTerms.language,
              client: client,
            );
          } else {
            final msg = res['message'] ?? res['msg'] ?? 'Something went wrong';
            OthersHelper().showToast(msg.toString(), Colors.black);
          }
        } catch (_) {
          OthersHelper().showToast('Something went wrong (${response.statusCode})', Colors.black);
        }

        setLoadingFalse();
        return false;
      } else if (response.body.contains('deleted')) {
        setLoadingFalse();
        return false;
      } else {
        debugPrint("body is".toString());
        try {
          final j = jsonDecode(response.body);
          OthersHelper()
              .showToast(j["message"] ?? 'Something went wrong', Colors.black);
        } catch (e) {
          OthersHelper().showToast('Something went wrong', Colors.black);
        }
        debugPrint(response.body);

        setLoadingFalse();
        return false;
      }
    } finally {
      if (shouldClose) {
        httpClient.close();
      }
    }
  } else {
      //internet off
      return false;
    }
  }

  saveDetailsAfterSocialLogin(String email, userName, String token, int userId,
      String userToken, bool isAppleLogin, appleId) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    print('token is $token');
    print('user id is $userId');
    prefs.setBool('keepLoggedIn', true);

    prefs.setString("email", email);
    prefs.setString("userName", email);

    prefs.setString("token", token);
    prefs.setInt('userId', userId);
    prefs.setString('userToken', userToken);
    prefs.setString('appleId', appleId);

    if (isAppleLogin == true) {
      prefs.setBool('appleLogin', true);
    } else {
      prefs.setBool('fbLogin', true);
    }

    return true;
  }
}
