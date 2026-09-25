import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:pusher_beams/pusher_beams.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/auth/login/login.dart';
import 'package:funmoments/view/home/landing_page.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../push_notification_service.dart';

class LogoutService with ChangeNotifier {
  bool isloading = false;

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  logout(BuildContext context) async {
    var connection = await checkConnection();
    if (connection) {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      var token = prefs.getString('token');

      var header = {
        //if header type is application/json then the data should be in jsonEncode method
        "Accept": "application/json",
        // "Content-Type": "application/json",
        "Authorization": "Bearer $token",
      };

      setLoadingTrue();
      var response = await http.post(
        Uri.parse('$baseApi/user/logout'),
        headers: header,
      );
      if (response.statusCode == 201 ||
          response.statusCode == 200 ||
          response.statusCode == 401) {
        notifyListeners();
        try {
          var pusherInstance =
              Provider.of<PushNotificationService>(context, listen: false)
                  .pusherInstance;

          if (pusherInstance != null) {
            await PusherBeams.instance.clearAllState();
          }
        } catch (e) {}

        // Navigator.pushAndRemoveUntil<dynamic>(
        //   context,
        //   MaterialPageRoute<dynamic>(
        //     builder: (BuildContext context) => const LoginPage(
        //       hasBackButton: false,
        //     ),
        //   ),
        //   (route) => false,
        // );

        // clear profile data =====>
        Provider.of<ProfileService>(context, listen: false)
            .setEverythingToDefault();

        await clear();
        setLoadingFalse();
        HomepageHelper.tabIndex.value = 0;
        Navigator.of(context, rootNavigator: true).pop();
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute<void>(
            builder: (BuildContext context) => const LandingPage(),
          ),
          (route) => false,
        );
      } else {
        print(response.body);
        OthersHelper().showToast('Something went wrong', Colors.black);
        setLoadingFalse();
      }
    }
  }

  //clear saved auth session keys
  clear() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
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
  }
}
