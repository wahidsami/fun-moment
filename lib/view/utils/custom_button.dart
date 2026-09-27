import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

import 'others_helper.dart';

class CustomButton extends StatelessWidget {
  final void Function()? onPressed;
  final String btText;
  final bool isLoading;
  final double? height;
  final double? width;
  final Color? backgroundColor;
  final Color? foregroundColor;

  const CustomButton({
    Key? key,
    required this.onPressed,
    required this.btText,
    required this.isLoading,
    this.height = 40,
    this.width,
    this.backgroundColor,
    this.foregroundColor,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      width: width ?? double.infinity,
      child: ElevatedButton(
        onPressed: onPressed == null
            ? null
            : isLoading
                ? () {}
                : () {
                    onPressed!();
                  },
        style: ButtonStyle(
          elevation: MaterialStateProperty.all(0),
          shape: MaterialStateProperty.all(
            RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(FMRadii.md),
            ),
          ),
          backgroundColor: MaterialStateProperty.resolveWith((states) {
            if (states.contains(MaterialState.disabled)) {
              return FMColors.surfaceElevated;
            }
            if (states.contains(MaterialState.pressed)) {
              return FMColors.magentaDark;
            }
            return backgroundColor ?? FMColors.magenta;
          }),
          foregroundColor: MaterialStateProperty.resolveWith((states) {
            if (states.contains(MaterialState.disabled)) {
              return FMColors.textMuted;
            }
            return foregroundColor ?? Colors.white;
          }),
        ),
        child: isLoading
            ? SizedBox(
                child: OthersHelper().showLoading(Colors.white),
              )
            : FittedBox(
                child: Text(
                  btText,
                  maxLines: 1,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
      ),
    );
  }
}
