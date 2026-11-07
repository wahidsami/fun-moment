import 'dart:async';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:pin_code_fields/pin_code_fields.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

class EmailVerifyPage extends StatefulWidget {
  const EmailVerifyPage({
    Key? key,
    required this.email,
    required this.token,
    required this.userId,
    required this.state,
    required this.countryId,
    this.userType = 1,
    this.initialOtpFailed = false,
  }) : super(key: key);

  final String? email;
  final String? token;
  final dynamic userId;
  final dynamic state;
  final dynamic countryId;
  final int userType;
  final bool initialOtpFailed;

  @override
  _EmailVerifyPageState createState() => _EmailVerifyPageState();
}

class _EmailVerifyPageState extends State<EmailVerifyPage> {
  final TextEditingController textEditingController = TextEditingController();
  StreamController<ErrorAnimationType>? errorController;

  int _secondsRemaining = 60;
  Timer? _countdownTimer;
  bool _isResending = false;

  @override
  void initState() {
    super.initState();
    if (widget.initialOtpFailed) {
      _secondsRemaining = 0;
    } else {
      _startCountdown(60);
    }
  }

  void _startCountdown([int seconds = 60]) {
    _countdownTimer?.cancel();
    if (!mounted) return;
    setState(() {
      _secondsRemaining = seconds;
    });
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_secondsRemaining <= 1) {
        timer.cancel();
        setState(() {
          _secondsRemaining = 0;
        });
      } else {
        setState(() {
          _secondsRemaining--;
        });
      }
    });
  }

  Future<void> _handleResend(EmailVerifyService provider) async {
    if (_secondsRemaining > 0 || _isResending || provider.isSendingOtp) {
      return;
    }

    setState(() {
      _isResending = true;
    });

    final success = await provider.sendOtpForEmailValidation(
      widget.email,
      context,
      widget.token,
      purpose: 'email_verify',
    );

    if (mounted) {
      setState(() {
        _isResending = false;
      });
      if (success) {
        OthersHelper().showToast(
          lnProvider.getString('Verification code sent successfully'),
          ConstantColors().successColor,
        );
        _startCountdown(60);
      }
    }
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _countdownTimer = null;
    errorController?.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
          builder: (context, provider, child) => SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 25, vertical: 20),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const SizedBox(height: 20),
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
                const SizedBox(height: 13),
                CommonHelper().paragraphCommon(
                  lnProvider.getString(
                      'Enter the 4 digit code we sent to to your email in order verify your email'),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 20),

                // Initial delivery notice if first send failed
                if (widget.initialOtpFailed && _secondsRemaining == 0)
                  Container(
                    margin: const EdgeInsets.only(bottom: 20),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: FMColors.surface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.amber.withValues(alpha: 0.5)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.info_outline, color: Colors.amber, size: 20),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            lnProvider.getString(
                              "We couldn't deliver the code automatically. Tap Send again below to receive your verification code.",
                            ),
                            style: const TextStyle(
                              color: FMColors.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
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
                        userType: widget.userType,
                      );
                    },
                    onChanged: (value) {},
                    beforeTextPaste: (text) {
                      return true;
                    },
                  ),
                ),

                const SizedBox(height: 22),
                FMPrimaryButton(
                  label: lnProvider.getString("Verify Code"),
                  isLoading: provider.verifyOtpLoading,
                  onPressed: provider.verifyOtpLoading
                      ? null
                      : () {
                          if (textEditingController.text.trim().length == 4) {
                            provider.verifyOtpAndLogin(
                              textEditingController.text.trim(),
                              context,
                              widget.email,
                              widget.token,
                              widget.userId,
                              widget.state,
                              widget.countryId,
                              userType: widget.userType,
                            );
                          } else {
                            OthersHelper().showToast(
                              lnProvider.getString("Please enter the 4 digit code"),
                              Colors.black,
                            );
                          }
                        },
                ),
                const SizedBox(height: 20),

                // Resend action with live 60-second countdown and duplicate protection
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (_isResending || provider.isSendingOtp)
                      OthersHelper().showLoading(FMColors.magenta)
                    else if (_secondsRemaining > 0)
                      RichText(
                        text: TextSpan(
                          text: '${lnProvider.getString("Did not receive")}? ',
                          style: const TextStyle(
                            color: FMColors.textMuted,
                            fontSize: 14,
                          ),
                          children: [
                            TextSpan(
                              text: '${lnProvider.getString("Resend in")} ${_secondsRemaining}s',
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                                fontSize: 14,
                                color: FMColors.textMuted,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      RichText(
                        text: TextSpan(
                          text: '${lnProvider.getString("Did not receive")}?  ',
                          style: const TextStyle(
                            color: FMColors.textMuted,
                            fontSize: 14,
                          ),
                          children: [
                            TextSpan(
                              recognizer: TapGestureRecognizer()
                                ..onTap = () => _handleResend(provider),
                              text: lnProvider.getString("Send again"),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 14,
                                color: FMColors.magenta,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
