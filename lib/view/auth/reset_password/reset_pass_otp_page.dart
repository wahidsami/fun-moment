import 'dart:async';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:pin_code_fields/pin_code_fields.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/reset_pass_otp_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class ResetPassOtpPage extends StatefulWidget {
  const ResetPassOtpPage({Key? key, this.email}) : super(key: key);

  final email;

  @override
  State<ResetPassOtpPage> createState() => _ResetPassOtpPageState();
}

class _ResetPassOtpPageState extends State<ResetPassOtpPage> {
  final TextEditingController textEditingController = TextEditingController();
  StreamController<ErrorAnimationType>? errorController;
  String currentText = "";

  @override
  void dispose() {
    textEditingController.dispose();
    errorController?.close();
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
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const FMBrandLogo(height: 54),
                  const SizedBox(height: 20),
                  Text(
                    asProvider.getString("Enter the 4 digit code"),
                    style: Theme.of(context)
                        .textTheme
                        .headlineMedium
                        ?.copyWith(fontWeight: FontWeight.w800),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 10),
                  Text(
                    asProvider.getString(
                      "Enter the 4 digit code we sent to to your email in order to reset password",
                    ),
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 22),
                  PinCodeTextField(
                    appContext: context,
                    length: 4,
                    keyboardType: TextInputType.number,
                    obscureText: false,
                    animationType: AnimationType.fade,
                    showCursor: true,
                    cursorColor: FMColors.textMuted,
                    pinTheme: PinTheme(
                      shape: PinCodeFieldShape.box,
                      borderRadius: BorderRadius.circular(14),
                      fieldHeight: 54,
                      fieldWidth: 62,
                      activeFillColor: FMColors.surface,
                      borderWidth: 1.5,
                      selectedColor: FMColors.magenta,
                      activeColor: FMColors.magenta,
                      inactiveColor: FMColors.border,
                    ),
                    animationDuration: const Duration(milliseconds: 200),
                    errorAnimationController: errorController,
                    controller: textEditingController,
                    onCompleted: (otp) {
                      ResetPasswordOtpService().checkOtp(otp, widget.email, context);
                    },
                    onChanged: (value) {
                      setState(() {
                        currentText = value;
                      });
                    },
                    beforeTextPaste: (text) => true,
                  ),
                  const SizedBox(height: 18),
                  Consumer<ResetPasswordService>(
                    builder: (context, provider, child) => provider.isloading
                        ? const CircularProgressIndicator(
                            strokeWidth: 2.2,
                            valueColor:
                                AlwaysStoppedAnimation<Color>(FMColors.magenta),
                          )
                        : Center(
                            child: RichText(
                              text: TextSpan(
                                text: '${asProvider.getString("Did not receive")}?  ',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium
                                    ?.copyWith(color: FMColors.textMuted),
                                children: <TextSpan>[
                                  TextSpan(
                                    recognizer: TapGestureRecognizer()
                                      ..onTap = () {
                                        provider.sendOtp(
                                          widget.email,
                                          context,
                                          isFromOtpPage: true,
                                        );
                                      },
                                    text: asProvider.getString("Send again"),
                                    style: Theme.of(context)
                                        .textTheme
                                        .labelLarge
                                        ?.copyWith(color: FMColors.magenta),
                                  ),
                                ],
                              ),
                            ),
                          ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

