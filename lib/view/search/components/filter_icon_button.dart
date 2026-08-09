import 'package:flutter/material.dart';
import 'package:funmoments/helper/extension/int_extension.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/view/utils/constant_colors.dart';

class FilterIconButton extends StatelessWidget {
  final String icon;
  final String subtitle;
  final onPressed;
  const FilterIconButton(
      {Key? key,
      required this.icon,
      required this.subtitle,
      required this.onPressed})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Expanded(
      flex: 1,
      child: IconButton(
          onPressed: onPressed,
          icon: Column(
            children: [
              icon.toSVGSized(
                20,
                color: cc.black3,
              ),
              6.toHeight,
              Text(subtitle),
            ],
          )),
    );
  }
}
