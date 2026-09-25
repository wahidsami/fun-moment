import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/utils/app_strings.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Language Switching and RTL Unit Tests', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
    });

    test('Initial RtlService defaults to LTR', () {
      final rtl = RtlService();
      expect(rtl.direction, 'ltr');
      expect(rtl.isRtl, false);
      expect(rtl.isArabic, false);
    });

    test('changeLanguage switches to Arabic (RTL) and updates SharedPreferences', () async {
      SharedPreferences.setMockInitialValues({});
      final rtl = RtlService();
      final stringService = AppStringService();

      await rtl.changeLanguage('ar', stringService: stringService);

      expect(rtl.langSlug, 'ar');
      expect(rtl.direction, 'rtl');
      expect(rtl.isArabic, true);
      expect(rtl.isRtl, true);

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString(RtlService.userSelectedLangKey), 'ar');
      expect(prefs.getString('slug'), 'ar');
    });

    test('changeLanguage switches back to English (LTR)', () async {
      SharedPreferences.setMockInitialValues({
        RtlService.userSelectedLangKey: 'ar',
        'slug': 'ar',
      });
      final rtl = RtlService();
      final stringService = AppStringService();

      await rtl.changeLanguage('en', stringService: stringService);

      expect(rtl.langSlug, 'en');
      expect(rtl.direction, 'ltr');
      expect(rtl.isArabic, false);
      expect(rtl.isRtl, false);

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString(RtlService.userSelectedLangKey), 'en');
      expect(prefs.getString('slug'), 'en');
    });

    test('AppStringService translates key navigation and provider strings to Arabic', () {
      final stringService = AppStringService();
      stringService.setLanguage('ar');

      expect(stringService.getString('Discover'), 'استكشاف');
      expect(stringService.getString('Bookings'), 'الحجوزات');
      expect(stringService.getString('Profile'), 'الملف الشخصي');
      expect(stringService.getString('Dashboard'), 'لوحة التحكم');
      expect(stringService.getString('Services'), 'الخدمات');
      expect(stringService.getString('Jobs'), 'الوظائف');
      expect(stringService.getString('Provider Workspace'), 'مساحة عمل المزود');
      expect(stringService.getString('Pending Orders'), 'الطلبات المعلقة');
      expect(
        stringService.getString('Manage your services, client orders, and earnings'),
        'إدارة خدماتك وطلبات العملاء والأرباح',
      );
      expect(stringService.getString('Language'), 'اللغة');
      expect(stringService.getString('English'), 'الإنجليزية');
      expect(stringService.getString('Arabic'), 'العربية');
      expect(stringService.getString('Welcome back'), 'مرحبًا بعودتك');
      expect(stringService.getString('Welcome to FUN MOMENT'), 'مرحبًا بك في FUN MOMENT');
      expect(stringService.getString('Guest'), 'ضيف');
    });

    test('AppStringService restores English strings when setLanguage("en")', () {
      final stringService = AppStringService();
      stringService.setLanguage('ar');
      expect(stringService.getString('Discover'), 'استكشاف');

      stringService.setLanguage('en');
      expect(stringService.getString('Discover'), 'Discover');
      expect(stringService.getString('Bookings'), 'Bookings');
      expect(stringService.getString('Profile'), 'Profile');
      expect(stringService.getString('Dashboard'), 'Dashboard');
      expect(stringService.getString('Provider Workspace'), 'Provider Workspace');
      expect(stringService.getString('Language'), 'Language');
    });

    test('Language persistence: loadSavedLanguage hydrates user choice correctly', () async {
      SharedPreferences.setMockInitialValues({
        RtlService.userSelectedLangKey: 'ar',
      });

      final rtl = RtlService();
      await rtl.loadSavedLanguage(null);

      expect(rtl.langSlug, 'ar');
      expect(rtl.direction, 'rtl');
      expect(rtl.isArabic, true);
      expect(rtl.isRtl, true);
    });

    test('Unmapped string fallback returns static text intact', () {
      final stringService = AppStringService();
      stringService.setLanguage('ar');
      expect(stringService.getString('NonExistentCustomString123'), 'NonExistentCustomString123');
    });
  });
}
