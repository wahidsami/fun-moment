import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/signup/components/country_states_dropdowns.dart';
import 'package:funmoments/view/auth/signup/components/terms_conditions_modal.dart';
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
  int? acceptedTermsVersionId;
  String? acceptedTermsLanguage;

  Future<void> _openTermsModal() async {
    final result = await showTermsConditionsModal(
      context,
      role: 'buyer',
    );

    if (!mounted) return;
    if (result != null) {
      final rtl = Provider.of<RtlService>(context, listen: false);
      final currentLang = rtl.isArabic ? 'ar' : 'en';
      setState(() {
        termsAgree = true;
        acceptedTermsVersionId = result.versionId;
        acceptedTermsLanguage = result.language.isNotEmpty ? result.language : currentLang;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final rtl = Provider.of<RtlService>(context);
    final currentLang = rtl.isArabic ? 'ar' : 'en';

    // If language was changed after acceptance, clear previous consent
    if (acceptedTermsLanguage != null && acceptedTermsLanguage != currentLang) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && termsAgree) {
          setState(() {
            termsAgree = false;
            acceptedTermsVersionId = null;
            acceptedTermsLanguage = null;
          });
        }
      });
    }

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
            title: InkWell(
              onTap: _openTermsModal,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Text(
                  asProvider.getString('I agree with the terms and conditions'),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: termsAgree ? FMColors.magenta : null,
                    decoration: TextDecoration.underline,
                  ),
                ),
              ),
            ),
            value: termsAgree,
            onChanged: (newValue) {
              if (termsAgree) {
                setState(() {
                  termsAgree = false;
                  acceptedTermsVersionId = null;
                  acceptedTermsLanguage = null;
                });
              } else {
                _openTermsModal();
              }
            },
            controlAffinity: ListTileControlAffinity.leading,
          ),
          const SizedBox(height: 16),
          Consumer<SignupService>(
            builder: (context, provider, child) => FMPrimaryButton(
              label: asProvider.getString('Sign Up'),
              isLoading: provider.isloading,
              onPressed: () {
                if (!termsAgree || acceptedTermsVersionId == null || acceptedTermsLanguage == null) {
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
                    termsVersionId: acceptedTermsVersionId,
                    termsLanguage: acceptedTermsLanguage,
                    onStaleTerms: () {
                      if (mounted) {
                        setState(() {
                          termsAgree = false;
                          acceptedTermsVersionId = null;
                          acceptedTermsLanguage = null;
                        });
                        _openTermsModal();
                      }
                    },
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
