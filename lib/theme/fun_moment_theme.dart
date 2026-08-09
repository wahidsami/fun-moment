import 'package:flutter/material.dart';

class FMColors {
  static const Color background = Color(0xFF070810);
  static const Color backgroundSoft = Color(0xFF0C0F1A);
  static const Color surface = Color(0xFF121625);
  static const Color surfaceElevated = Color(0xFF181E2E);
  static const Color card = Color(0xFF161B29);
  static const Color inputSurface = Color(0xFF101421);
  static const Color navigationSurface = Color(0xFF0D1020);
  static const Color overlay = Color(0xCC05060B);

  static const Color magenta = Color(0xFFFF4FB3);
  static const Color magentaDark = Color(0xFFB61B7A);
  static const Color magentaLight = Color(0xFFFFC1EA);

  static const Color cyan = Color(0xFF2DE2FF);
  static const Color cyanDark = Color(0xFF0DA8D1);
  static const Color cyanLight = Color(0xFFA6F4FF);

  static const Color orange = Color(0xFFFF9D43);
  static const Color orangeDark = Color(0xFFE06B1A);
  static const Color orangeLight = Color(0xFFFFD3AD);

  static const Color textPrimary = Colors.white;
  static const Color textSecondary = Color(0xFFD3D7E3);
  static const Color textMuted = Color(0xFF9097A8);
  static const Color textDisabled = Color(0xFF5F6474);
  static const Color onBrandText = Colors.white;

  static const Color border = Color(0xFF273044);
  static const Color success = Color(0xFF40D38A);
  static const Color warning = Color(0xFFFFC257);
  static const Color error = Color(0xFFFF5B74);
  static const Color info = Color(0xFF56A7FF);
}

class FMSpacing {
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
  static const double xxxxl = 40;
  static const double giant = 48;

  static const double screen = 20;
  static const double section = 18;
  static const double card = 16;
  static const double component = 12;
  static const double modal = 20;
  static const double bottomNav = 16;
}

class FMRadii {
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 22;
  static const double xl = 28;
  static const double pill = 999;
}

class FMGradients {
  static const LinearGradient funGradient = LinearGradient(
    colors: [FMColors.cyan, FMColors.magenta],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );

  static const LinearGradient energyGradient = LinearGradient(
    colors: [FMColors.magenta, FMColors.orange],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient darkCinematic = LinearGradient(
    colors: [Color(0x00000000), Color(0xFF070810)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );
}

class FMShadows {
  static const List<BoxShadow> subtleGlow = [
    BoxShadow(
      color: Color(0x55FF4FB3),
      blurRadius: 18,
      spreadRadius: 1,
      offset: Offset(0, 0),
    ),
  ];

  static const List<BoxShadow> cyanGlow = [
    BoxShadow(
      color: Color(0x442DE2FF),
      blurRadius: 16,
      spreadRadius: 1,
      offset: Offset(0, 0),
    ),
  ];
}

class FMTheme {
  static ThemeData dark() {
    final base = ThemeData.dark(useMaterial3: true);
    final textTheme = base.textTheme.copyWith(
      displayLarge: const TextStyle(
        color: FMColors.textPrimary,
        fontSize: 34,
        fontWeight: FontWeight.w800,
        height: 1.08,
      ),
      headlineLarge: const TextStyle(
        color: FMColors.textPrimary,
        fontSize: 28,
        fontWeight: FontWeight.w800,
        height: 1.12,
      ),
      headlineMedium: const TextStyle(
        color: FMColors.textPrimary,
        fontSize: 22,
        fontWeight: FontWeight.w700,
        height: 1.18,
      ),
      headlineSmall: const TextStyle(
        color: FMColors.textPrimary,
        fontSize: 18,
        fontWeight: FontWeight.w700,
        height: 1.22,
      ),
      bodyLarge: const TextStyle(
        color: FMColors.textSecondary,
        fontSize: 16,
        fontWeight: FontWeight.w500,
        height: 1.45,
      ),
      bodyMedium: const TextStyle(
        color: FMColors.textSecondary,
        fontSize: 14,
        fontWeight: FontWeight.w400,
        height: 1.45,
      ),
      bodySmall: const TextStyle(
        color: FMColors.textMuted,
        fontSize: 13,
        fontWeight: FontWeight.w400,
        height: 1.4,
      ),
      labelLarge: const TextStyle(
        color: FMColors.textPrimary,
        fontSize: 14,
        fontWeight: FontWeight.w700,
        height: 1.1,
      ),
      labelMedium: const TextStyle(
        color: FMColors.textMuted,
        fontSize: 13,
        fontWeight: FontWeight.w600,
        height: 1.1,
      ),
    );

    return base.copyWith(
      primaryColor: FMColors.magenta,
      scaffoldBackgroundColor: FMColors.background,
      colorScheme: const ColorScheme.dark(
        primary: FMColors.magenta,
        secondary: FMColors.cyan,
        surface: FMColors.surface,
        error: FMColors.error,
        background: FMColors.background,
        onPrimary: FMColors.onBrandText,
        onSecondary: Colors.black,
        onSurface: FMColors.textPrimary,
        onError: Colors.white,
      ),
      textTheme: textTheme,
      appBarTheme: const AppBarTheme(
        backgroundColor: FMColors.background,
        surfaceTintColor: FMColors.background,
        elevation: 0,
        centerTitle: false,
        iconTheme: IconThemeData(color: FMColors.textPrimary),
        titleTextStyle: TextStyle(
          color: FMColors.textPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: FMColors.inputSurface,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        hintStyle: const TextStyle(color: FMColors.textMuted, fontSize: 14),
        labelStyle: const TextStyle(color: FMColors.textMuted),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FMRadii.md),
          borderSide: const BorderSide(color: FMColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FMRadii.md),
          borderSide: const BorderSide(color: FMColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FMRadii.md),
          borderSide: const BorderSide(color: FMColors.magenta, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FMRadii.md),
          borderSide: const BorderSide(color: FMColors.error, width: 1.2),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FMRadii.md),
          borderSide: const BorderSide(color: FMColors.error, width: 1.5),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: FMColors.magenta,
          foregroundColor: FMColors.onBrandText,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(FMRadii.md),
          ),
          textStyle: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: FMColors.magentaLight,
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: FMColors.textPrimary,
          side: const BorderSide(color: FMColors.border),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(FMRadii.md),
          ),
        ),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: FMColors.navigationSurface,
        selectedItemColor: FMColors.magenta,
        unselectedItemColor: FMColors.textMuted,
        type: BottomNavigationBarType.fixed,
        showUnselectedLabels: true,
        showSelectedLabels: true,
      ),
      cardTheme: CardTheme(
        color: FMColors.card,
        surfaceTintColor: FMColors.card,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(FMRadii.lg),
          side: const BorderSide(color: FMColors.border),
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: FMColors.border,
        thickness: 1,
        space: 1,
      ),
      checkboxTheme: CheckboxThemeData(
        fillColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return FMColors.magenta;
          }
          return FMColors.surface;
        }),
        side: const BorderSide(color: FMColors.border, width: 1.4),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(5),
        ),
      ),
      snackBarTheme: const SnackBarThemeData(
        backgroundColor: FMColors.surfaceElevated,
        contentTextStyle: TextStyle(color: FMColors.textPrimary),
        behavior: SnackBarBehavior.floating,
      ),
      tabBarTheme: const TabBarTheme(
        labelColor: FMColors.magenta,
        unselectedLabelColor: FMColors.textMuted,
        indicatorColor: FMColors.magenta,
      ),
      splashColor: FMColors.magenta.withOpacity(.12),
      highlightColor: Colors.transparent,
    );
  }
}

class FMAssets {
  static const String logo = 'logo.png';

  static const String homeHero =
      'assets/images/app/hero/fun_moment_home_hero_01.png';
  static const String djHero =
      'assets/images/app/hero/fun_moment_dj_hero_01.png';
  static const String equipmentHero =
      'assets/images/app/hero/fun_moment_equipment_hero_01.png';

  static const String djPerformance =
      'assets/images/app/dj/fun_moment_dj_performance_01.png';
  static const String djRiyadhSkyline =
      'assets/images/app/promotions/fun_moment_dj_riyadh_skyline_01.png';
  static const String riyadhEvent =
      'assets/images/app/promotions/fun_moment_riyadh_event_01.png';
  static const String partyEnergy =
      'assets/images/app/promotions/fun_moment_party_energy_01.png';
  static const String privateParty =
      'assets/images/app/booking/fun_moment_private_party_01.png';

  static const String djEquipment =
      'assets/images/app/equipment/fun_moment_dj_equipment_01.png';
  static const String djEquipmentPackage =
      'assets/images/app/equipment/fun_moment_dj_equipment_package_01.png';
  static const String soundSystem =
      'assets/images/app/equipment/fun_moment_sound_system_01.png';
  static const String lightingEquipment =
      'assets/images/app/equipment/fun_moment_lighting_equipment_01.png';
  static const String generator =
      'assets/images/app/equipment/fun_moment_generator_01.png';
  static const String partyLighting =
      'assets/images/app/equipment/fun_moment_party_lighting_01.png';
  static const String eventSetup =
      'assets/images/app/equipment/fun_moment_event_setup_01.png';

  static const List<String> homePromoAssets = [
    homeHero,
    djPerformance,
    djRiyadhSkyline,
    equipmentHero,
    riyadhEvent,
    partyLighting,
    partyEnergy,
  ];

  static const List<String> categoryFallbackAssets = [
    djEquipment,
    djEquipmentPackage,
    soundSystem,
    lightingEquipment,
    generator,
    partyLighting,
    eventSetup,
  ];

  static String categoryFallbackForIndex(int index) {
    if (categoryFallbackAssets.isEmpty) return djEquipment;
    return categoryFallbackAssets[index % categoryFallbackAssets.length];
  }
}
