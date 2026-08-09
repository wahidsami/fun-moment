import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/signup/components/country_states_dropdowns.dart';
import 'package:funmoments/view/auth/signup/signup_helper.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class SignupCountryStates extends StatefulWidget {
  const SignupCountryStates({
    Key? key,
    this.fullNameController,
    this.userNameController,
    this.emailController,
    this.passController,
  }) : super(key: key);

  final fullNameController;
  final userNameController;
  final emailController;
  final passController;

  @override
  State<SignupCountryStates> createState() => _SignupCountryStatesState();
}

class _SignupCountryStatesState extends State<SignupCountryStates> {
  bool termsAgree = false;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const CountryStatesDropdowns(),
          const SizedBox(height: 16),
          CheckboxListTile(
            checkColor: Colors.white,
            activeColor: FMColors.magenta,
            contentPadding: EdgeInsets.zero,
            title: Text(
              asProvider.getString('I agree with the terms and conditions'),
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            value: termsAgree,
            onChanged: (newValue) {
              setState(() {
                termsAgree = !termsAgree;
              });
            },
            controlAffinity: ListTileControlAffinity.leading,
          ),
          const SizedBox(height: 16),
          Consumer<SignupService>(
            builder: (context, provider, child) => FMPrimaryButton(
              label: asProvider.getString('Sign Up'),
              isLoading: provider.isloading,
              onPressed: () {
                if (!termsAgree) {
                  OthersHelper().showToast(
                    asProvider.getString(
                      'You must agree with the terms and conditions to register',
                    ),
                    Colors.black,
                  );
                  return;
                }

                if (provider.isloading == false) {
                  final selectedStateId = Provider.of<StateDropdownService>(
                    context,
                    listen: false,
                  ).selectedStateId;
                  final selectedAreaId = Provider.of<AreaDropdownService>(
                    context,
                    listen: false,
                  ).selectedAreaId;

                  if (selectedStateId == '0' ||
                      selectedAreaId == '0' ||
                      selectedAreaId == null) {
                    OthersHelper().showSnackBar(
                      context,
                      asProvider.getString("You must select a state and area"),
                      FMColors.warning,
                    );
                    return;
                  }

                  provider.signup(
                    widget.fullNameController.text.trim(),
                    widget.emailController.text.trim(),
                    widget.userNameController.text.trim(),
                    widget.passController.text.trim(),
                    context,
                  );
                }
              },
            ),
          ),
          const SizedBox(height: 20),
          SignupHelper().haveAccount(context),
        ],
      ),
    );
  }
}

