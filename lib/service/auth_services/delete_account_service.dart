import 'dart:convert';
import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform, TargetPlatform;
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DeleteAccountService with ChangeNotifier {
  bool isloading = false;
  var deactivateReasonDropdownList = ['Vacation', 'Personal reason'];
  var deactivateReasonDropdownIndexList = ['Vacation', 'Vacation'];
  var selecteddeactivateReason = 'Vacation';
  var selecteddeactivateReasonId = 'Vacation';

  setdeactivateReasonValue(value) {
    selecteddeactivateReason = value;
    notifyListeners();
  }

  setSelecteddeactivateReasonId(value) {
    selecteddeactivateReasonId = value;
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

  deleteAccount(BuildContext context, password, description) async {
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
      if (baseApi == 'https://funmoments.sa/api/v1') {
        await Future.delayed(const Duration(seconds: 1));
        OthersHelper()
            .showToast('This feature is turned off in test mode', Colors.black);
        setLoadingFalse();
        return;
      }
      var response = await http.post(
        Uri.parse(
            '$baseApi/account-delete?reason=$selecteddeactivateReasonId&description=$description&password=$password'),
        headers: header,
      );
      if (response.statusCode == 201) {
        try {
          final data = jsonDecode(response.body);
          if (data['message'] != null) {
            OthersHelper().showToast(data['message'], Colors.black);
          }
        } catch (e) {}
        var appleId = sPref.getString("appleId");
        var appleUserToken = sPref.getString("userToken");

        await appleTokenRevoke(
          appleUserToken,
          appleId,
        );
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

        clear();
        setLoadingFalse();
      } else {
        try {
          final data = jsonDecode(response.body);
          if (data['message'] != null) {
            OthersHelper().showToast(data['message'], Colors.black);
            setLoadingFalse();
            return;
          }
        } catch (e) {}
        print(response.body);
        OthersHelper().showToast('Something went wrong', Colors.black);
        setLoadingFalse();
      }
    }
  }

  appleTokenRevoke(token, id) async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.iOS) {
      return;
    }
    var header = {
      //if header type is application/json then the data should be in jsonEncode method
      // "Accept": "application/json",
      'content-type': 'application/x-www-form-urlencoded',
    };

    debugPrint(
        'https://appleid.apple.com/auth/revoke?client_id=$id&client_secret=$clientSecret&token=$token&token_type_hint=access_token');
    var response = await http.post(
      Uri.parse(
          'https://appleid.apple.com/auth/revoke?client_id=$id&client_secret=$clientSecret&token=$token&token_type_hint=access_token'),
      headers: header,
    );
    print(id);
    print(response.statusCode);
    if (response.statusCode == 200) {
      "Apple id revoked successfully".tr().showToast();
    } else {
      "Apple id revoke failed".tr().showToast();
    }
  }

  //clear saved email, pass and token
  clear() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    prefs.clear();
  }
}
