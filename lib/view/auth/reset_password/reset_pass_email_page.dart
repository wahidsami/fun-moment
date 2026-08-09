import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/reset_password/reset_pass_otp_page.dart';

class ResetPassEmailPage extends StatefulWidget {
  const ResetPassEmailPage({Key? key}) : super(key: key);

  @override
  State<ResetPassEmailPage> createState() => _ResetPassEmailPageState();
}

class _ResetPassEmailPageState extends State<ResetPassEmailPage> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController emailController = TextEditingController();

  @override
  void dispose() {
    emailController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          onPressed: () => Navigator.pop(context),
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Consumer<AppStringService>(
            builder: (context, asProvider, child) => FMSurfaceCard(
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const FMBrandLogo(height: 56),
                    const SizedBox(height: 20),
                    Text(
                      asProvider.getString("Reset password"),
                      style: Theme.of(context)
                          .textTheme
                          .headlineMedium
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      asProvider.getString(
                        "Enter the email you used to create account and we'll send instruction for resetting password",
                      ),
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 22),
                    FMTextField(
                      controller: emailController,
                      label: asProvider.getString("Enter Email"),
                      hintText: "Email",
                      keyboardType: TextInputType.emailAddress,
                      prefixIcon: const Icon(Icons.mail_rounded),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return asProvider.getString("Please enter your email");
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 18),
                    Consumer<ResetPasswordService>(
                      builder: (context, provider, child) => FMPrimaryButton(
                        label: asProvider.getString("Send Instructions"),
                        isLoading: provider.isloading,
                        onPressed: () {
                          if (provider.isloading == false &&
                              _formKey.currentState!.validate()) {
                            provider.sendOtp(emailController.text.trim(), context);
                          }
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

