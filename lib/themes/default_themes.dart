import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import '../view/utils/constant_colors.dart';

class DefaultThemes {
  InputDecorationTheme? inputDecorationTheme(BuildContext context) =>
      InputDecorationTheme(
          hintStyle: MaterialStateTextStyle.resolveWith((states) {
            return Theme.of(context).textTheme.titleSmall!.copyWith(
                  color: cc.black5,
                );
          }),
          counterStyle: MaterialStateTextStyle.resolveWith((states) {
            if (states.contains(MaterialState.focused)) {
              return Theme.of(context)
                  .textTheme
                  .titleSmall!
                  .copyWith(color: cc.primaryColor);
            }
            return Theme.of(context)
                .textTheme
                .titleSmall!
                .copyWith(color: cc.black3);
          }),
          fillColor: cc.black8,
          errorStyle: Theme.of(context)
              .textTheme
              .titleSmall!
              .copyWith(color: cc.warningColor),
          filled: true,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
          enabledBorder: OutlineInputBorder(
              borderSide: const BorderSide(color: Colors.transparent),
              borderRadius: BorderRadius.circular(8)),
          focusedBorder: OutlineInputBorder(
              borderSide: BorderSide(color: ConstantColors().primaryColor)),
          errorBorder: OutlineInputBorder(
              borderSide: BorderSide(color: ConstantColors().warningColor)),
          focusedErrorBorder: OutlineInputBorder(
              borderSide: BorderSide(color: ConstantColors().primaryColor)),
          prefixIconColor: MaterialStateColor.resolveWith((states) {
            if (states.contains(MaterialState.focused)) {
              return cc.primaryColor;
            }
            if (states.contains(MaterialState.error)) {
              return cc.warningColor;
            }
            return cc.black5;
          }));

  CheckboxThemeData? checkboxTheme() => CheckboxThemeData(
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        side: BorderSide(
          width: 2,
          color: cc.black7,
        ),
        fillColor: MaterialStateColor.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return cc.primaryColor;
          }
          return cc.white;
        }),
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(3),
            side: BorderSide(
              // width: 1,
              color: cc.primaryColor,
            )),
      );
  RadioThemeData? radioThemeData(dProvider) => RadioThemeData(
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        fillColor: MaterialStateColor.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return dProvider.secondaryColor;
          }
          return cc.white;
        }),
        visualDensity: VisualDensity.compact,
      );

  OutlinedButtonThemeData? outlinedButtonTheme(BuildContext context) =>
      OutlinedButtonThemeData(
          style: ButtonStyle(
        overlayColor:
            MaterialStateColor.resolveWith((states) => Colors.transparent),
        shape: MaterialStateProperty.resolveWith<OutlinedBorder?>((states) {
          return RoundedRectangleBorder(borderRadius: BorderRadius.circular(8));
          // }
        }),
        side: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.pressed)) {
            return BorderSide(
              color: cc.primaryColor,
            );
          }
          return BorderSide(
            color: cc.black8,
          );
        }),
        textStyle: MaterialStateProperty.resolveWith((states) =>
            context.titleMedium!.copyWith(fontWeight: FontWeight.w600)),
        foregroundColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.pressed)) {
            return cc.primaryColor;
          }
          return cc.black5;
        }),
      ));

  ElevatedButtonThemeData? elevatedButtonTheme(BuildContext context) =>
      ElevatedButtonThemeData(
          style: ButtonStyle(
        elevation: MaterialStateProperty.resolveWith((states) => 0),
        overlayColor:
            MaterialStateColor.resolveWith((states) => Colors.transparent),
        shape: MaterialStateProperty.resolveWith<OutlinedBorder?>((states) {
          return RoundedRectangleBorder(borderRadius: BorderRadius.circular(8));
          // }
        }),
        backgroundColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.disabled)) {
            return cc.primaryColor.withOpacity(.05);
          }
          if (states.contains(MaterialState.pressed)) {
            return cc.black3;
          }
          return cc.primaryColor;
        }),
        textStyle: MaterialStateProperty.resolveWith((states) =>
            Theme.of(context)
                .textTheme
                .titleMedium!
                .copyWith(fontWeight: FontWeight.w600)),
        foregroundColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.disabled)) {
            return cc.black5;
          }
          if (states.contains(MaterialState.pressed)) {
            return cc.white;
          }
          return cc.white;
        }),
      ));
  TextButtonThemeData? textButtonThemeData(BuildContext context) =>
      TextButtonThemeData(
          style: ButtonStyle(
              elevation: MaterialStateProperty.resolveWith((states) => 0),
              overlayColor: MaterialStateColor.resolveWith(
                  (states) => Colors.transparent),
              backgroundColor: MaterialStateProperty.resolveWith((states) {
                return cc.black3.withOpacity(0.0);
              }),
              foregroundColor: MaterialStateProperty.resolveWith((states) {
                if (states.contains(MaterialState.disabled)) {
                  return cc.black5;
                }
                if (states.contains(MaterialState.pressed)) {
                  return cc.black3;
                }
                return cc.black3;
              }),
              textStyle: MaterialStateProperty.resolveWith((states) =>
                  Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w600))));

  appBarTheme(BuildContext context) => AppBarTheme(
        backgroundColor: cc.white,
        foregroundColor: cc.black3,
        titleTextStyle: Theme.of(context)
            .textTheme
            .titleLarge
            ?.copyWith(fontWeight: FontWeight.w600),
        iconTheme: IconThemeData(color: cc.black3),
        actionsIconTheme: IconThemeData(color: cc.black3),
        elevation: 0,
        surfaceTintColor: cc.white,
        systemOverlayStyle: const SystemUiOverlayStyle(
            statusBarColor: Colors.transparent,
            statusBarIconBrightness: Brightness.dark),
      );

  sliderTheme(BuildContext context) {
    return SliderThemeData(
      thumbColor: cc.white,
      inactiveTrackColor: cc.black7,
      activeTrackColor: cc.primaryColor,
      trackHeight: 3,
    );
  }

  // themeData(BuildContext context, dProvider) => ThemeData(
  //     primaryColor: cc.primaryColor,
  //     fontFamily: "Gilroy",
  //     scaffoldBackgroundColor: Colors.transparent,
  //     scrollbarTheme: scrollbarTheme(dProvider),
  //     useMaterial3: true,
  //     appBarTheme: DefaultThemes().appBarTheme(context),
  //     elevatedButtonTheme: elevatedButtonTheme(dProvider, context),
  //     outlinedButtonTheme: outlinedButtonTheme(dProvider, context),
  //     inputDecorationTheme: inputDecorationTheme(context, dProvider),
  //     checkboxTheme: checkboxTheme(dProvider),
  //     textButtonTheme: textButtonThemeData(),
  //     // navigationBarTheme: navigationBarThemeData(context),
  //     switchTheme: switchThemeData(dProvider),
  //     radioTheme: radioThemeData(dProvider));

  // navigationBarThemeData(BuildContext context) {
  //   return NavigationBarThemeData(
  //     backgroundColor: context.dProvider.navBarColor,
  //     surfaceTintColor: context.dProvider.navBarColor,
  //     indicatorColor: context.dProvider.navBarColor,
  //   );
  // }
}

SwitchThemeData switchThemeData() => SwitchThemeData(
      thumbColor:
          MaterialStateProperty.resolveWith<Color>((Set<MaterialState> states) {
        if (states.contains(MaterialState.disabled)) {
          return cc.primaryColor.withOpacity(.10);
        }
        return cc.white;
      }),
      trackColor:
          MaterialStateProperty.resolveWith<Color>((Set<MaterialState> states) {
        if (!states.contains(MaterialState.selected)) {
          return cc.black8;
        }
        return cc.primaryColor.withOpacity(.60);
      }),
      trackOutlineColor:
          MaterialStateProperty.resolveWith<Color>((Set<MaterialState> states) {
        if (!states.contains(MaterialState.selected)) {
          return cc.black8;
        }
        return cc.primaryColor.withOpacity(.40);
      }),
    );

ScrollbarThemeData scrollbarTheme() => ScrollbarThemeData(
      thumbVisibility: MaterialStateProperty.resolveWith((states) {
        if (states.contains(MaterialState.scrolledUnder)) {
          return true;
        }
        return false;
      }),
      thickness: MaterialStateProperty.resolveWith((states) => 6),
      thumbColor: MaterialStateProperty.resolveWith(
          (states) => cc.primaryColor.withOpacity(.60)),
    );
