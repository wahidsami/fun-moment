import 'dart:convert';
import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:provider/provider.dart';
import 'package:pusher_beams/pusher_beams.dart';
import 'package:shared_preferences/shared_preferences.dart';

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
      {bool isGoogleLogin = true}) async {
    var connection = await checkConnection();
    if (connection) {
      if (isGoogleLogin == true) {
        setLoadingTrue();
      }
      var data = jsonEncode({
        'email': email,
        'displayName': username,
        'id': id,
        'isGoogle': isGoogle
      });
      log(data.toString());
      var header = {
        //if header type is application/json then the data should be in jsonEncode method
        "Accept": "application/json",
        "Content-Type": "application/json"
      };

      var response = await http.post(Uri.parse('$baseApi/social/login'),
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
