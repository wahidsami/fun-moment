import 'dart:convert';
import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:provider/provider.dart';
import 'package:pusher_beams/pusher_beams.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../view/auth/signup/components/terms_conditions_modal.dart';
import '../../view/home/landing_page.dart';
import '../../view/utils/others_helper.dart';
import '../common_service.dart';
import 'package:http/http.dart' as http;

import '../profile_service.dart';
import '../push_notification_service.dart';

class GoogleSignInService with ChangeNotifier {
  bool isloading = false;

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  static const String _serverClientId = String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID');
  final googleSignIn = GoogleSignIn(
    serverClientId: _serverClientId.isNotEmpty ? _serverClientId : null,
  );

  GoogleSignInAccount? _user;
  GoogleSignInAccount get user => _user!;

  Future googleLogin(BuildContext context) async {
    try {
      final googleUser = await googleSignIn.signIn();

      if (googleUser == null) return;
      _user = googleUser;

      if (_user != null) {
        await socialLogin(_user!.email, _user!.displayName, _user?.id, 1, context);
      }
    } catch (e) {
      debugPrint('Google Sign-In failed: $e');
      String msg = 'Google Sign-In is not currently available.';
      if (e.toString().contains('ApiException: 10')) {
        msg = 'Google Sign-In setup is pending in Firebase Console.';
      }
      OthersHelper().showToast(msg, Colors.black);
    } finally {
      notifyListeners();
    }
  }

//Logout from google ====>
  logOutFromGoogleLogin() {
    googleSignIn.signOut();
  }

  Future<bool> socialLogin(
      email, username, id, int isGoogle, BuildContext context,
      {bool isGoogleLogin = true,
      int? termsVersionId,
      String? termsLanguage,
      http.Client? client}) async {
    var connection = await checkConnection();
    if (connection) {
      if (isGoogleLogin == true) {
        setLoadingTrue();
      }
      final Map<String, dynamic> payload = {
        'email': email,
        'displayName': username,
        'id': id,
        'isGoogle': isGoogle
      };
      if (termsVersionId != null) {
        payload['terms_version_id'] = termsVersionId;
      }
      if (termsLanguage != null && termsLanguage.isNotEmpty) {
        payload['terms_language'] = termsLanguage;
      }
      var data = jsonEncode(payload);
      log(data.toString());
      var header = {
        //if header type is application/json then the data should be in jsonEncode method
        "Accept": "application/json",
        "Content-Type": "application/json"
      };

      final httpClient = client ?? http.Client();
      final shouldClose = client == null;

      try {
        var response = await httpClient.post(Uri.parse('$baseApi/social/login'),
            body: data, headers: header);

      if (response.statusCode == 200 || response.statusCode == 201) {
        setLoadingFalse();
        print(response.body);

        var responseData = jsonDecode(response.body);
        String token = responseData['token']?.toString() ?? '';
        int userId = int.tryParse(responseData['users']?['id']?.toString() ?? '') ?? 0;
        int userType = responseData['users']?['user_type'] != null
            ? int.tryParse(responseData['users']['user_type'].toString()) ?? 1
            : 1;

        await saveDetailsAfterSocialLogin(
            email, username, token, userId, isGoogleLogin, userType: userType);
        await Provider.of<ProfileService>(context, listen: false)
            .getProfileDetails();
        await Provider.of<PushNotificationService>(context, listen: false)
            .fetchPusherCredential(context: context);
        var pusherInstance =
            Provider.of<PushNotificationService>(context, listen: false)
                .pusherInstance;

        if (pusherInstance != null) {
          try {
            await PusherBeams.instance.start(pusherInstance);
          } catch (e) {
            debugPrint('Pusher error: $e');
          }
        }
        Navigator.pushReplacement<void, void>(
          context,
          MaterialPageRoute<void>(
            builder: (BuildContext context) => const LandingPage(),
          ),
        );

        return true;
      } else if (response.statusCode == 422) {
        debugPrint(response.body);
        try {
          final res = jsonDecode(response.body);
          if (res is Map && res['errors'] is Map && res['errors'].containsKey('terms_version_id')) {
            // First-time social account creation requires buyer terms acceptance
            setLoadingFalse();
            final acceptedTerms = await showTermsConditionsModal(
              context,
              role: 'buyer',
            );

            if (acceptedTerms == null) {
              debugPrint('[GoogleSignIn] Terms acceptance declined for new social account.');
              return false;
            }

            // Retry social login with accepted immutable version metadata
            return await socialLogin(
              email,
              username,
              id,
              isGoogle,
              context,
              isGoogleLogin: isGoogleLogin,
              termsVersionId: acceptedTerms.versionId,
              termsLanguage: acceptedTerms.language,
              client: client,
            );
          } else {
            final msg = res['message'] ?? res['msg'] ?? 'Social login failed';
            OthersHelper().showToast(msg.toString(), Colors.black);
          }
        } catch (_) {
          OthersHelper().showToast('Social login failed (${response.statusCode})', Colors.black);
        }

        setLoadingFalse();
        return false;
      } else {
        debugPrint(response.body);
        try {
          final res = jsonDecode(response.body);
          final msg = res['message'] ?? res['msg'] ?? 'Social login failed';
          OthersHelper().showToast(msg.toString(), Colors.black);
        } catch (_) {
          OthersHelper().showToast('Social login failed (${response.statusCode})', Colors.black);
        }

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
      bool isGoogleLogin, {int userType = 1}) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    print('token is $token');
    print('user id is $userId');
    prefs.setBool('keepLoggedIn', true);

    prefs.setString("email", email);
    prefs.setString("userName", email);

    prefs.setString("token", token);
    prefs.setInt('userId', userId);
    prefs.setInt('userType', userType);

    if (isGoogleLogin == true) {
      prefs.setBool('googleLogin', true);
    }

    return true;
  }
}
