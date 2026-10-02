import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/signup/components/email_name_fields.dart';
import 'package:funmoments/view/auth/signup/provider/provider_registration_page.dart';
import 'package:funmoments/view/auth/signup/signup_helper.dart';

class SignupEmailName extends StatefulWidget {
  const SignupEmailName({
    Key? key,
    this.fullNameController,
    this.userNameController,
    this.emailController,
  }) : super(key: key);

  final fullNameController;
  final userNameController;
  final emailController;

  @override
  State<SignupEmailName> createState() => _SignupEmailNameState();
}

class _SignupEmailNameState extends State<SignupEmailName> {
  final _formKey = GlobalKey<FormState>();

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) => Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Consumer<SignupService>(
              builder: (context, provider, child) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    asProvider.getString("What would you like to do?"),
                    style: const TextStyle(
                      color: FMColors.textPrimary,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: GestureDetector(
                          onTap: () => provider.setUserType(1),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 200),
                            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
                            decoration: BoxDecoration(
                              color: provider.selectedUserType == 1 ? FMColors.magenta.withOpacity(0.15) : FMColors.surface,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: provider.selectedUserType == 1 ? FMColors.magenta : FMColors.border,
                                width: provider.selectedUserType == 1 ? 2 : 1,
                              ),
                            ),
                            child: Column(
                              children: [
                                Icon(
                                  Icons.shopping_bag_outlined,
                                  color: provider.selectedUserType == 1 ? FMColors.magenta : FMColors.textMuted,
                                  size: 22,
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  asProvider.getString("Book services"),
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: provider.selectedUserType == 1 ? FMColors.textPrimary : FMColors.textMuted,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: GestureDetector(
                          onTap: () => provider.setUserType(0),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 200),
                            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
                            decoration: BoxDecoration(
                              color: provider.selectedUserType == 0 ? FMColors.cyan.withOpacity(0.15) : FMColors.surface,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: provider.selectedUserType == 0 ? FMColors.cyan : FMColors.border,
                                width: provider.selectedUserType == 0 ? 2 : 1,
                              ),
                            ),
                            child: Column(
                              children: [
                                Icon(
                                  Icons.storefront_outlined,
                                  color: provider.selectedUserType == 0 ? FMColors.cyan : FMColors.textMuted,
                                  size: 22,
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  asProvider.getString("Offer my services"),
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: provider.selectedUserType == 0 ? FMColors.textPrimary : FMColors.textMuted,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
            EmailNameFields(
              emailController: widget.emailController,
              fullNameController: widget.fullNameController,
              userNameController: widget.userNameController,
            ),
            const SizedBox(height: 18),
            Consumer<SignupService>(
              builder: (context, provider, child) => FMPrimaryButton(
                label: asProvider.getString("Continue"),
                onPressed: () {
                  if (_formKey.currentState!.validate()) {
                    if (provider.selectedUserType == 0) {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => ProviderRegistrationPage(
                            fullName: widget.fullNameController.text.trim(),
                            userName: widget.userNameController.text.trim(),
                            email: widget.emailController.text.trim(),
                          ),
                        ),
                      );
                    } else {
                      provider.pagecontroller.animateToPage(
                        provider.selectedPage + 1,
                        duration: const Duration(milliseconds: 300),
                        curve: Curves.ease,
                      );
                    }
                  }
                },
              ),
            ),
            const SizedBox(height: 16),
            SignupHelper().haveAccount(context),
          ],
        ),
      ),
    );
  }
}

