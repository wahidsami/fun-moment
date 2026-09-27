// ignore_for_file: must_be_immutable

import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class CustomDropdown extends StatelessWidget {
  String hintText;
  List listData;
  String? value;
  void Function(dynamic)? onChanged;
  CustomDropdown(this.hintText, this.listData, this.onChanged,
      {this.value, Key? key})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 56,
      width: double.infinity,
      margin: const EdgeInsets.symmetric(vertical: 5),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: FMColors.inputSurface,
        borderRadius: BorderRadius.circular(FMRadii.md),
        border: Border.all(
          color: FMColors.border,
          width: 1,
        ),
      ),
      child: DropdownButton(
        dropdownColor: FMColors.surfaceElevated,
        hint: Text(
          hintText,
          style: const TextStyle(
            color: FMColors.textMuted,
            fontSize: 14,
          ),
        ),
        underline: Container(),
        isExpanded: true,
        elevation: 4,
        isDense: true,
        value: value,
        style: const TextStyle(
          color: FMColors.textPrimary,
          fontSize: 14,
          fontWeight: FontWeight.w500,
        ),
        icon: const Icon(
          Icons.keyboard_arrow_down_rounded,
          color: FMColors.magenta,
        ),
        onChanged: onChanged,
        items: (listData).map((value) {
          return DropdownMenuItem(
            alignment: rtlProvider.direction == 'left'
                ? Alignment.centerRight
                : Alignment.centerLeft,
            value: value,
            child: SizedBox(
              // width: screenWidth - 140,
              child: Padding(
                padding: const EdgeInsets.only(left: 5),
                child:
                    Text(lnProvider.getString(value).toString().capitalize()),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

class RTLService {}
