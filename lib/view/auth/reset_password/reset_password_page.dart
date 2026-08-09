import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class ResetPasswordPage extends StatefulWidget {
  const ResetPasswordPage({Key? key, this.email}) : super(key: key);

  final email;

  @override
  State<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends State<ResetPasswordPage> {
  late bool _newpasswordVisible;
  late bool _repeatnewpasswordVisible;
  final _formKey = GlobalKey<FormState>();
  final TextEditingController newPasswordController = TextEditingController();
  final TextEditingController repeatNewPasswordController =
      TextEditingController();

  @override
  void initState() {
    super.initState();
    _newpasswordVisible = false;
    _repeatnewpasswordVisible = false;
  }

  @override
  void dispose() {
    newPasswordController.dispose();
    repeatNewPasswordController.dispose();
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
                    const FMBrandLogo(height: 54),
                    const SizedBox(height: 20),
                    Text(
                      asProvider.getString('Enter new password'),
                      style: Theme.of(context)
                          .textTheme
                          .headlineMedium
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      asProvider.getString(
                        'Your new password should be different from previously used passwords',
                      ),
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 22),
                    FMTextField(
                      controller: newPasswordController,
                      label: asProvider.getString('Enter new password'),
                      hintText: asProvider.getString('New password'),
                      obscureText: !_newpasswordVisible,
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
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return asProvider.getString('Please enter your password');
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 18),
                    FMTextField(
                      controller: repeatNewPasswordController,
                      label: asProvider.getString('Repeat new password'),
                      hintText: asProvider.getString('Retype new password'),
                      obscureText: !_repeatnewpasswordVisible,
                      prefixIcon: const Icon(Icons.lock_rounded),
                      suffixIcon: IconButton(
                        onPressed: () {
                          setState(() {
                            _repeatnewpasswordVisible =
                                !_repeatnewpasswordVisible;
                          });
                        },
                        icon: Icon(
                          _repeatnewpasswordVisible
                              ? Icons.visibility_off_outlined
                              : Icons.visibility_outlined,
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return asProvider.getString('Please retype your password');
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 18),
                    Consumer<ResetPasswordService>(
                      builder: (context, provider, child) => FMPrimaryButton(
                        label: asProvider.getString('Change password'),
                        isLoading: provider.isloading,
                        onPressed: () {
                          if (provider.isloading == false &&
                              _formKey.currentState!.validate()) {
                            provider.resetPassword(
                              newPasswordController.text,
                              repeatNewPasswordController.text,
                              widget.email,
                              context,
                            );
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

