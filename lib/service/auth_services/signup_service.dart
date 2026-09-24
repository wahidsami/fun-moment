import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/view/auth/signup/components/email_verify_page.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class SignupService with ChangeNotifier {
  int selectedPage = 0;
  var pagecontroller;
  bool isloading = false;

  String phoneNumber = '0';
  String countryCode = 'BD';

  setPhone(value) {
    phoneNumber = value;
    notifyListeners();
  }

  setCountryCode(code) {
    countryCode = code;
    notifyListeners();
  }

  setPageController(p) {
    pagecontroller = p;
    Future.delayed(const Duration(milliseconds: 400), () {
      notifyListeners();
    });
  }

  setSelectedPage(int i) {
    selectedPage = i;
    notifyListeners();
  }

  setSelectedPageO(int i) {
    selectedPage = i;
  }

  prevPage(int i) {
    selectedPage = i;
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

  int selectedUserType = 1; // 1 = Buyer, 0 = Seller

  setUserType(int type) {
    selectedUserType = type;
    notifyListeners();
  }

  Future signup(
    String fullName,
    String email,
    String userName,
    String password,
    BuildContext context,
  ) async {
    var connection = await checkConnection();

    if (connection) {
      setLoadingTrue();
      var data = jsonEncode({
        'name': fullName,
        'email': email,
        'username': userName,
        'phone': phoneNumber,
        'password': password,
        'service_city':
            Provider.of<StateDropdownService>(context, listen: false)
                .selectedStateId,
        'service_area': Provider.of<AreaDropdownService>(context, listen: false)
            .selectedAreaId,
        'country_id':
            Provider.of<CountryDropdownService>(context, listen: false)
                .selectedCountryId,
        'terms_conditions': 1,
        'country_code': countryCode,
        'user_type': selectedUserType
      });
      var header = {
        //if header type is application/json then the data should be in jsonEncode method
        "Accept": "application/json",
        "Content-Type": "application/json"
      };

      var response = await http.post(Uri.parse('$baseApi/register'),
          body: data, headers: header);

      if (response.statusCode == 201) {
        OthersHelper().showToast(
            "Registration successful", ConstantColors().successColor);
        print(response.body);

        // Navigator.pushReplacement<void, void>(
        //   context,
        //   MaterialPageRoute<void>(
        //     builder: (BuildContext context) => const LandingPage(),
        //   ),
        // );

        var responseData = jsonDecode(response.body);
        String token = responseData['token']?.toString() ?? '';
        int userId = int.tryParse(responseData['users']?['id']?.toString() ?? '') ?? 0;
        String state = responseData['users']?['state']?.toString() ?? '';
        String countryId =
            responseData['users']?['country_id']?.toString() ?? '';

        //Send otp
        var isOtepSent =
            await Provider.of<EmailVerifyService>(context, listen: false)
                .sendOtpForEmailValidation(email, context, token);
        setLoadingFalse();
        if (isOtepSent) {
          Navigator.pushReplacement<void, void>(
            context,
            MaterialPageRoute<void>(
              builder: (BuildContext context) => EmailVerifyPage(
                email: email,
                token: token,
                userId: userId,
                state: state,
                countryId: countryId,
                userType: selectedUserType,
              ),
            ),
          );
        } else {
          // Backend error toast was already shown by sendOtpForEmailValidation
        }

        return true;
      } else {
        //Sign up unsuccessful ==========>
        print('sign up failed ${response.body}');
        try {
          var errBody = jsonDecode(response.body);
          if (errBody is Map && errBody.containsKey('errors')) {
            showError(errBody['errors']);
          } else if (errBody is Map && errBody.containsKey('message')) {
            OthersHelper().showToast(errBody['message']?.toString() ?? 'Registration failed', Colors.black);
          } else {
            OthersHelper().showToast('Registration failed', Colors.black);
          }
        } catch (_) {
          OthersHelper().showToast('Registration failed (${response.statusCode})', Colors.black);
        }

        setLoadingFalse();
        return false;
      }
    } else {
      //internet connection off
      return false;
    }
  }

  showError(error) {
    if (error.containsKey('email')) {
      OthersHelper().showToast(error['email'][0], Colors.black);
    } else if (error.containsKey('username')) {
      OthersHelper().showToast(error['username'][0], Colors.black);
    } else if (error.containsKey('phone')) {
      OthersHelper().showToast(error['phone'][0], Colors.black);
    } else if (error.containsKey('password')) {
      OthersHelper().showToast(error['password'][0], Colors.black);
    } else {
      OthersHelper().showToast('Something went wrong', Colors.black);
    }
  }
}
