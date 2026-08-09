import 'package:flutter/material.dart';
import 'package:intl_phone_field/intl_phone_field.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/signup/signup_helper.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

class SignupPhonePass extends StatefulWidget {
  const SignupPhonePass({
    Key? key,
    this.passController,
    this.repeatPassController,
  }) : super(key: key);

  final passController;
  final repeatPassController;

  @override
  State<SignupPhonePass> createState() => _SignupPhonePassState();
}

class _SignupPhonePassState extends State<SignupPhonePass> {
  late bool _newpasswordVisible;
  late bool _repeatnewpasswordVisible;
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _newpasswordVisible = false;
    _repeatnewpasswordVisible = false;
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) => Consumer<SignupService>(
        builder: (context, provider, child) => Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                asProvider.getString('Phone'),
                style: Theme.of(context).textTheme.labelLarge,
              ),
              const SizedBox(height: 8),
              Consumer<RtlService>(
                builder: (context, rtlP, child) => IntlPhoneField(
                  decoration: SignupHelper().phoneFieldDecoration(),
                  searchText: asProvider.getString('Search country'),
                  initialCountryCode: provider.countryCode,
                  disableLengthCheck: true,
                  textAlign: rtlP.direction == 'ltr'
                      ? TextAlign.left
                      : TextAlign.right,
                  onChanged: (phone) {
                    provider.setCountryCode(phone.countryISOCode);
                    provider.setPhone(phone.completeNumber);
                  },
                ),
              ),
              const SizedBox(height: 18),
              FMTextField(
                controller: widget.passController,
                label: asProvider.getString('Password'),
                hintText: asProvider.getString('Enter password'),
                obscureText: !_newpasswordVisible,
                textInputAction: TextInputAction.next,
                prefixIcon: const Icon(Icons.lock_rounded),
                suffixIcon: IconButton(
                  onPressed: () {
                    setState(() {
                      _newpasswordVisible = !_newpasswordVisible;
                    });
                  },
                  icon: Icon(
                    _newpasswordVisible
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                  ),
                ),
                validator: (value) => value.toString().validPass,
              ),
              const SizedBox(height: 18),
              FMTextField(
                controller: widget.repeatPassController,
                label: asProvider.getString('Repeat password'),
                hintText: asProvider.getString('Enter password'),
                obscureText: !_repeatnewpasswordVisible,
                textInputAction: TextInputAction.done,
                prefixIcon: const Icon(Icons.lock_rounded),
                suffixIcon: IconButton(
                  onPressed: () {
                    setState(() {
                      _repeatnewpasswordVisible = !_repeatnewpasswordVisible;
                    });
                  },
                  icon: Icon(
                    _repeatnewpasswordVisible
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                  ),
                ),
                validator: (value) {
                  if (widget.passController.text != value) {
                    return asProvider.getString("Please retype your password");
                  }
                  return null;
                },
              ),
              const SizedBox(height: 18),
              Consumer<SignupService>(
                builder: (context, provider, child) => FMPrimaryButton(
                  label: asProvider.getString("Continue"),
                  onPressed: () {
                    final valid = _formKey.currentState?.validate();
                    if (valid != true) return;
                    if (widget.passController.text !=
                        widget.repeatPassController.text) {
                      OthersHelper().showToast(
                        asProvider.getString("Password did not match"),
                        Colors.black,
                      );
                    } else if (widget.passController.text.length < 6) {
                      OthersHelper().showToast(
                        asProvider.getString(
                            "Password must be at least 6 characters"),
                        Colors.black,
                      );
                    } else {
                      provider.pagecontroller.animateToPage(
                        provider.selectedPage + 1,
                        duration: const Duration(milliseconds: 300),
                        curve: Curves.ease,
                      );
                    }
                  },
                ),
              ),
              const SizedBox(height: 20),
              SignupHelper().haveAccount(context),
              const SizedBox(height: 4),
            ],
          ),
        ),
      ),
    );
  }
}

