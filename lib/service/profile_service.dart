import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:funmoments/model/profile_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;

class ProfileService with ChangeNotifier {
  bool isloading = false;
  bool hasError = false;

  ProfileModel? _profileDetails;
  dynamic get profileDetails => _profileDetails;
  set profileDetails(dynamic value) {
    if (value is ProfileModel) {
      _profileDetails = value;
    } else {
      _profileDetails = null;
    }
  }

  var profileImage;

  List ordersList = [0, 0, 0, 0];
  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  setEverythingToDefault() {
    _profileDetails = null;
    profileImage = null;
    hasError = false;
    ordersList = [0, 0, 0, 0];
    _cachedUserType = null;
    notifyListeners();
  }

  Future<bool> getProfileDetails({bool isFromProfileupdatePage = false}) async {
    if (isFromProfileupdatePage == true) {
      //if from update profile page then load it anyway

      setEverythingToDefault();
      await fetchData();
      return true;
    } else {
      //not from profile page. check if data already loaded
      if (profileDetails == null) {
        fetchData();
        return true;
      } else {
        print('profile data already loaded');
        return true;
      }
    }
  }

  Future<bool> fetchData() async {
    var connection = await checkConnection();
    if (!connection) return false;
    //internet connection is on
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var token = prefs.getString('token');

    print('token is $token');

    setLoadingTrue();

    var header = {
      //if header type is application/json then the data should be in jsonEncode method
      "Accept": "application/json",
      // "Content-Type": "application/json"
      "Authorization": "Bearer $token",
    };

    var response =
        await http.get(Uri.parse('$baseApi/user/profile'), headers: header);
    if (response.statusCode == 200 || response.statusCode == 201) {
      var data = ProfileModel.fromJson(jsonDecode(response.body));
      profileDetails = data;
      hasError = false;
      if (data.userDetails.userType != null) {
        _cachedUserType = data.userDetails.userType;
        prefs.setInt('userType', data.userDetails.userType!);
      }

      ordersList[0] = data.pendingOrder;
      ordersList[1] = data.activeOrder;
      ordersList[2] = data.completeOrder;
      ordersList[3] = data.totalOrder;

      if (jsonDecode(response.body)['profile_image'] is List) {
        //then dont do anything because it means image is missing from database
      } else {
        profileImage = jsonDecode(response.body)['profile_image']['img_url'];
      }

      setLoadingFalse();
      notifyListeners();
      return true;
    } else {
      print(response.body);
      profileDetails = null;
      hasError = true;
      setLoadingFalse();
      notifyListeners();

      return false;
    }
  }

  int? _cachedUserType;

  Future<void> initUserType() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    _cachedUserType = prefs.getInt('userType');
    notifyListeners();
  }

  bool get isSeller {
    if (profileDetails is ProfileModel) {
      return (profileDetails as ProfileModel).userDetails.userType == 0;
    }
    return _cachedUserType == 0;
  }

  bool get isBuyer => !isSeller;

  int? get userType {
    if (profileDetails is ProfileModel) {
      return (profileDetails as ProfileModel).userDetails.userType;
    }
    return _cachedUserType;
  }
}
