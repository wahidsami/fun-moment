import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/constant_colors.dart';

class TextareaField extends StatelessWidget {
  const TextareaField({Key? key, this.notesController, this.hintText})
      : super(key: key);
  final notesController;
  final hintText;

  @override
  Widget build(BuildContext context) {
    return TextField(
        controller: notesController,
        maxLines: 6,
        style: const TextStyle(color: FMColors.textPrimary, fontSize: 14),
        textInputAction: TextInputAction.next,
        decoration: InputDecoration(
            filled: true,
            fillColor: FMColors.inputSurface,
            enabledBorder: OutlineInputBorder(
                borderSide: const BorderSide(color: FMColors.border),
                borderRadius: BorderRadius.circular(FMRadii.md)),
            focusedBorder: OutlineInputBorder(
                borderSide: const BorderSide(color: FMColors.magenta, width: 1.5),
                borderRadius: BorderRadius.circular(FMRadii.md)),
            errorBorder: OutlineInputBorder(
                borderSide: const BorderSide(color: FMColors.error),
                borderRadius: BorderRadius.circular(FMRadii.md)),
            focusedErrorBorder: OutlineInputBorder(
                borderSide: const BorderSide(color: FMColors.error, width: 1.5),
                borderRadius: BorderRadius.circular(FMRadii.md)),
            hintText: hintText,
            hintStyle: const TextStyle(color: FMColors.textMuted, fontSize: 14),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 18, vertical: 18)));
  }
}
