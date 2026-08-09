import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/view/auth/signup/components/email_name_fields.dart';
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
                    provider.pagecontroller.animateToPage(
                      provider.selectedPage + 1,
                      duration: const Duration(milliseconds: 300),
                      curve: Curves.ease,
                    );
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

