import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/login/login.dart';
import 'package:funmoments/view/utils/responsive.dart';

class SignupHelper {
  haveAccount(BuildContext context) {
    return Center(
      child: RichText(
        text: TextSpan(
          text: lnProvider.getString('Have an account?') + '  ',
          style: const TextStyle(color: FMColors.textMuted, fontSize: 14),
          children: <TextSpan>[
            TextSpan(
              recognizer: TapGestureRecognizer()
                ..onTap = () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const LoginPage(),
                    ),
                  );
                },
              text: lnProvider.getString('Sign in'),
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 14,
                color: FMColors.magenta,
              ),
            ),
          ],
        ),
      ),
    );
  }

  InputDecoration phoneFieldDecoration() {
    return InputDecoration(
      labelStyle: const TextStyle(color: FMColors.textMuted, fontSize: 14),
      filled: true,
      fillColor: FMColors.inputSurface,
      enabledBorder: OutlineInputBorder(
        borderSide: const BorderSide(color: FMColors.border),
        borderRadius: BorderRadius.circular(FMRadii.md),
      ),
      focusedBorder: OutlineInputBorder(
        borderSide: const BorderSide(color: FMColors.magenta),
        borderRadius: BorderRadius.circular(FMRadii.md),
      ),
      errorBorder: OutlineInputBorder(
        borderSide: const BorderSide(color: FMColors.error),
        borderRadius: BorderRadius.circular(FMRadii.md),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderSide: const BorderSide(color: FMColors.magenta),
        borderRadius: BorderRadius.circular(FMRadii.md),
      ),
      hintText: lnProvider.getString('Enter phone number'),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    );
  }
}
