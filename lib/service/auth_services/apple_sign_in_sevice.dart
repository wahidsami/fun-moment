// ignore_for_file: use_build_context_synchronously

import 'dart:convert';
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

  Future<bool> appleLogin(BuildContext context, {autoLogin = false}) async {
    debugPrint("trying to sign in using apple id".toString());
    try {
      final credential = await SignInWithApple.getAppleIDCredential(
        scopes: [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
      );

      if (credential.userIdentifier != null) {
        String? emailToUse = credential.email;
        if (emailToUse == null && credential.identityToken != null) {
          try {
            final parts = credential.identityToken!.split('.');
            if (parts.length == 3) {
              final normalized = base64Url.normalize(parts[1]);
              final payloadString = utf8.decode(base64Url.decode(normalized));
              final tokenPayload = jsonDecode(payloadString);
              if (tokenPayload is Map && tokenPayload['email'] != null) {
                emailToUse = tokenPayload['email'].toString();
              }
            }
          } catch (e) {
            debugPrint('Could not parse email from identityToken: $e');
          }
        }

        final displayName = (credential.givenName ?? "") +
            (credential.givenName != null ? ' ' : '') +
            (credential.familyName ?? '');

        final bool success = await socialLogin(
          emailToUse,
          displayName.trim().isNotEmpty ? displayName.trim() : null,
          credential.userIdentifier,
          credential.userIdentifier,
          credential.identityToken,
          context,
          autoLogin,
        );

        return success == true;
      } else {
        OthersHelper().showToast('Something went wrong', Colors.black);
        notifyListeners();
        return false;
      }
    } catch (e) {
      debugPrint("Apple Sign-In authorization error: $e");
      notifyListeners();
      return false;
    }
  }

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
      if (userToken != null && userToken.toString().isNotEmpty) {
        payload['identity_token'] = userToken;
      }
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
        debugPrint(response.statusCode.toString());

        if (response.statusCode == 200 || response.statusCode == 201) {
          debugPrint(response.body.toString());

          var responseData = jsonDecode(response.body);
          String token = responseData['token']?.toString() ?? '';
          int userId = int.tryParse(responseData['users']?['id']?.toString() ?? '') ?? 0;
          int userType = responseData['users']?['user_type'] != null
              ? int.tryParse(responseData['users']['user_type'].toString()) ?? 1
              : 1;
          String userDisplayName = responseData['users']?['name']?.toString() ??
              responseData['users']?['username']?.toString() ??
              '';
          String resolvedEmail = responseData['users']?['email']?.toString() ?? (email ?? '');

          await saveDetailsAfterSocialLogin(
              resolvedEmail,
              userDisplayName,
              token,
              userId,
              userToken?.toString() ?? '',
              isAppleLogin,
              appleId?.toString() ?? '',
              userType: userType);

          await Provider.of<ProfileService>(context, listen: false)
              .fetchData(client: client);
          await Provider.of<PushNotificationService>(context, listen: false)
              .fetchPusherCredential(context: context);
          setLoadingFalse();

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
          OthersHelper().showToast('Your account has been deleted', Colors.black);
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
      // internet off
      OthersHelper().showToast('Please check your internet connection', Colors.black);
      return false;
    }
  }

  saveDetailsAfterSocialLogin(String email, String userName, String token, int userId,
      String userToken, bool isAppleLogin, String appleId, {int userType = 1}) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    debugPrint('token is $token');
    debugPrint('user id is $userId');
    prefs.setBool('keepLoggedIn', true);

    prefs.setString("email", email);
    prefs.setString("userName", userName.isNotEmpty ? userName : email);

    prefs.setString("token", token);
    prefs.setInt('userId', userId);
    prefs.setInt('userType', userType);
    prefs.setString('userToken', userToken);
    prefs.setString('appleId', appleId);

    if (isAppleLogin == true) {
      prefs.setBool('appleLogin', true);
      prefs.setBool('googleLogin', false);
      prefs.setBool('fbLogin', false);
    } else {
      prefs.setBool('fbLogin', true);
      prefs.setBool('appleLogin', false);
    }

    return true;
  }
}
