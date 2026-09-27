import 'dart:async';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:pin_code_fields/pin_code_fields.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

class EmailVerifyPage extends StatefulWidget {
  const EmailVerifyPage(
      {Key? key,
      required this.email,
      required this.token,
      required this.userId,
      required this.state,
      required this.countryId,
      this.userType = 1})
      : super(key: key);

  final email;
  final token;
  final userId;
  final state;
  final countryId;
  final int userType;

  @override
  _EmailVerifyPageState createState() => _EmailVerifyPageState();
}

class _EmailVerifyPageState extends State<EmailVerifyPage> {
  TextEditingController textEditingController = TextEditingController();
  StreamController<ErrorAnimationType>? errorController;

  // String currentText = "";
  @override
  Widget build(BuildContext context) {
    ConstantColors cc = ConstantColors();
    return Listener(
      onPointerDown: (_) {
        FocusScopeNode currentFocus = FocusScope.of(context);
        if (!currentFocus.hasPrimaryFocus) {
          currentFocus.focusedChild?.unfocus();
        }
      },
      child: Scaffold(
        backgroundColor: FMColors.background,
        appBar: CommonHelper().appbarCommon('Verify Email', context, () {
          Navigator.pop(context);
        }),
        body: Consumer<EmailVerifyService>(
          builder: (context, provider, child) => Container(
            padding: const EdgeInsets.symmetric(horizontal: 25),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  height: 80.0,
                  width: 80.0,
                  margin: const EdgeInsets.only(bottom: 20),
                  decoration: const BoxDecoration(
                    image: DecorationImage(
                      image: AssetImage('assets/icons/email-circle.png'),
                      fit: BoxFit.cover,
                    ),
                  ),
                ),
                CommonHelper().titleCommon("Enter the 4 digit code"),
                const SizedBox(
                  height: 13,
                ),
                CommonHelper().paragraphCommon(
                    lnProvider.getString(
                        'Enter the 4 digit code we sent to to your email in order verify your email'),
                    textAlign: TextAlign.center),
                const SizedBox(
                  height: 33,
                ),
                Form(
                  child: PinCodeTextField(
                    appContext: context,
                    length: 4,
                    keyboardType: TextInputType.number,
                    obscureText: false,
                    animationType: AnimationType.fade,
                    showCursor: true,
                    cursorColor: FMColors.textMuted,
                    textStyle: const TextStyle(
                      color: FMColors.textPrimary,
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                    pinTheme: PinTheme(
                      shape: PinCodeFieldShape.box,
                      borderRadius: BorderRadius.circular(14),
                      fieldHeight: 54,
                      fieldWidth: 62,
                      activeFillColor: FMColors.surface,
                      selectedFillColor: FMColors.surface,
                      inactiveFillColor: FMColors.inputSurface,
                      borderWidth: 1.5,
                      selectedColor: FMColors.magenta,
                      activeColor: FMColors.magenta,
                      inactiveColor: FMColors.border,
                    ),
                    animationDuration: const Duration(milliseconds: 200),
                    enableActiveFill: true,
                    errorAnimationController: errorController,
                    controller: textEditingController,
                    onCompleted: (otp) {
                      provider.verifyOtpAndLogin(
                          otp,
                          context,
                          widget.email,
                          widget.token,
                          widget.userId,
                          widget.state,
                          widget.countryId,
                          userType: widget.userType);
                    },
                    onChanged: (value) {},
                    beforeTextPaste: (text) {
                      return true;
                    },
                  ),
                ),

                //Loading bar
                provider.verifyOtpLoading == true
                    ? Container(
                        margin: const EdgeInsets.only(top: 15, bottom: 5),
                        alignment: Alignment.center,
                        child: OthersHelper().showLoading(FMColors.magenta),
                      )
                    : Container(),

                const SizedBox(
                  height: 13,
                ),
                Consumer<ResetPasswordService>(
                  builder: (context, provider, child) => Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      provider.isloading == false
                          ? RichText(
                              text: TextSpan(
                                text:
                                    '${lnProvider.getString("Did not receive")}?  ',
                                style: const TextStyle(
                                    color: FMColors.textMuted, fontSize: 14),
                                children: <TextSpan>[
                                  TextSpan(
                                      recognizer: TapGestureRecognizer()
                                        ..onTap = () {
                                          provider.sendOtp(
                                              widget.email, context,
                                              isFromOtpPage: true);
                                        },
                                      text: lnProvider.getString("Send again"),
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 14,
                                        color: FMColors.magenta,
                                      )),
                                ],
                              ),
                            )
                          : OthersHelper().showLoading(FMColors.magenta),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
