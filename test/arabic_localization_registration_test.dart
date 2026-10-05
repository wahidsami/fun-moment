import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Arabic Registration & Address Flow Localization Tests', () {
    late AppStringService appStringService;
    late RtlService rtlService;

    setUp(() async {
      SharedPreferences.setMockInitialValues({});
      appStringService = AppStringService();
      rtlService = RtlService();
      // Initialize responsive lnProvider for testing
      lnProvider = appStringService;
    });

    test('1. Registration Page Title Localization (Arabic vs English)', () async {
      // English mode
      await rtlService.changeLanguage('en', stringService: appStringService);
      expect(appStringService.getString('Registration'), equals('Registration'));
      expect(appStringService.getString('Register to join us'), equals('Register to join us'));

      // Arabic mode
      await rtlService.changeLanguage('ar', stringService: appStringService);
      expect(appStringService.getString('Registration'), equals('التسجيل'));
      expect(appStringService.getString('Register to join us'), equals('سجل للانضمام إلينا'));
    });

    test('2. Customer Registration Form Field Labels & Hints', () async {
      await rtlService.changeLanguage('ar', stringService: appStringService);

      expect(appStringService.getString('Full name'), equals('الاسم الكامل'));
      expect(appStringService.getString('Enter your full name'), equals('أدخل اسمك الكامل'));
      expect(appStringService.getString('Please enter your full name'), equals('الرجاء إدخال اسمك الكامل'));

      expect(appStringService.getString('Username'), equals('اسم المستخدم'));
      expect(appStringService.getString('Enter your username'), equals('أدخل اسم المستخدم الخاص بك'));
      expect(appStringService.getString('Please enter your username'), equals('الرجاء إدخال اسم المستخدم الخاص بك'));

      expect(appStringService.getString('Email'), equals('البريد الإلكتروني'));
      expect(appStringService.getString('Enter your email'), equals('أدخل بريدك الإلكتروني'));
      expect(appStringService.getString('Please enter your email'), equals('الرجاء إدخال بريدك الإلكتروني'));

      expect(appStringService.getString('Phone'), equals('الهاتف'));
      expect(appStringService.getString('Enter phone number'), equals('أدخل رقم الهاتف'));
      expect(appStringService.getString('Search country'), equals('بحث عن دولة'));

      expect(appStringService.getString('Password'), equals('كلمة المرور'));
      expect(appStringService.getString('Enter password'), equals('أدخل كلمة المرور'));
      expect(appStringService.getString('Repeat Password'), equals('تأكيد كلمة المرور'));
      expect(appStringService.getString('Please retype your password'), equals('الرجاء إعادة كتابة كلمة المرور الخاصة بك'));
      expect(appStringService.getString('Password did not match'), equals('كلمة المرور غير متطابقة'));
      expect(appStringService.getString('Passwords do not match'), equals('كلمتا المرور غير متطابقتين'));

      expect(appStringService.getString('Continue'), equals('استمر'));
      expect(appStringService.getString('Sign Up'), equals('اشترك'));
      expect(appStringService.getString('Sign In'), equals('تسجيل الدخول'));
      expect(appStringService.getString('Have an account?'), equals('هل لديك حساب؟'));
      expect(appStringService.getString('I agree with the terms and conditions'), equals('أوافق على الشروط والأحكام'));
      expect(
        appStringService.getString('You must agree with the terms and conditions to register'),
        equals('يجب أن توافق على الشروط والأحكام للتسجيل'),
      );
      expect(
        appStringService.getString('You must select a state and area'),
        equals('يجب عليك اختيار ولاية ومنطقة'),
      );
      expect(
        appStringService.getString('You must select a city and area'),
        equals('يجب اختيار المدينة والحي'),
      );
    });

    test('3. Password Complexity Validation Messages via .validPass extension', () async {
      await rtlService.changeLanguage('ar', stringService: appStringService);

      // Empty password
      expect(''.validPass, equals('الرجاء إدخال كلمة المرور الخاصة بك'));

      // Under 8 characters
      expect('Ab1!'.validPass, equals('يجب أن تتكون كلمة المرور من 8 أحرف على الأقل'));

      // Missing uppercase
      expect('abcdef1!'.validPass, equals('يجب أن تحتوي كلمة المرور على حرف كبير واحد على الأقل'));

      // Missing lowercase
      expect('ABCDEF1!'.validPass, equals('يجب أن تحتوي كلمة المرور على حرف صغير واحد على الأقل'));

      // Missing digit
      expect('Abcdefg!'.validPass, equals('يجب أن تحتوي كلمة المرور على رقم واحد على الأقل'));

      // Missing special char
      expect('Abcdefg1'.validPass, equals('يجب أن تحتوي كلمة المرور على رمز خاص واحد على الأقل'));

      // Valid password returns null (passes)
      expect('Tekkentag2#.#'.validPass, isNull);
    });

    test('4. Address & Location Dropdown Labels and Saudi Location Translations', () async {
      await rtlService.changeLanguage('ar', stringService: appStringService);

      // Dropdown UI labels
      expect(appStringService.getString('Choose city'), equals('اختر المدينة'));
      expect(appStringService.getString('Choose area'), equals('اختر المنطقة'));
      expect(appStringService.getString('Choose Area'), equals('اختر المنطقة'));
      expect(appStringService.getString('Choose country'), equals('اختر الدولة'));
      expect(appStringService.getString('Search city'), equals('البحث عن مدينة'));
      expect(appStringService.getString('Search area'), equals('بحث عن منطقة'));
      expect(appStringService.getString('Saudi Arabia'), equals('المملكة العربية السعودية'));

      // Authoritative Saudi City translations from saudiLocationStrings
      expect(appStringService.getString('Riyadh'), equals('الرياض'));
      expect(appStringService.getString('Jeddah'), equals('جدة'));
      expect(appStringService.getString('Dammam'), equals('الدمام'));
      expect(appStringService.getString('Madinah'), equals('المدينة المنورة'));
      expect(appStringService.getString('Abha'), equals('ابها'));
      expect(appStringService.getString('Tabuk'), equals('تبوك'));

      // Authoritative Saudi Area translations from saudiLocationStrings
      expect(appStringService.getString('Olaya'), equals('العليا'));
      expect(appStringService.getString('Al Malqa'), equals('حي الملقا'));
    });

    test('5. Provider Registration Fields and Documents', () async {
      await rtlService.changeLanguage('ar', stringService: appStringService);

      expect(appStringService.getString('Provider Registration'), equals('تسجيل مزود الخدمة'));
      expect(appStringService.getString('Join as Service Provider'), equals('انضم كمزود خدمة'));
      expect(appStringService.getString('Provider Type'), equals('نوع المزود'));
      expect(appStringService.getString('Individual'), equals('فرد'));
      expect(appStringService.getString('Company'), equals('منشأة / شركة'));
      expect(appStringService.getString('Account Information'), equals('معلومات الحساب'));
      expect(appStringService.getString('Identity & Qualifications'), equals('الهوية والمؤهلات'));
      expect(appStringService.getString('National ID / Iqama Number'), equals('رقم الهوية الوطنية / الإقامة'));
      expect(appStringService.getString('National ID / Iqama Document'), equals('وثيقة الهوية الوطنية / الإقامة'));
      expect(appStringService.getString('Professional License Number'), equals('رقم الرخصة المهنية / العمل الحر'));
      expect(appStringService.getString('Professional License Document'), equals('وثيقة الرخصة المهنية'));
      expect(appStringService.getString('Company & Commercial Registration'), equals('بيانات الشركة والسجل التجاري'));
      expect(appStringService.getString('Commercial Registration (CR) Number'), equals('رقم السجل التجاري'));
      expect(appStringService.getString('CR Certificate Document'), equals('شهادة السجل التجاري'));
      expect(appStringService.getString('Contact Person Details'), equals('بيانات مسؤول التواصل'));
      expect(appStringService.getString('Offered Service Categories'), equals('فئات الخدمات المقدمة'));
      expect(appStringService.getString('Location & Operating Area'), equals('الموقع ونطاق العمل'));
      expect(appStringService.getString('Submit Provider Application'), equals('تقديم طلب مزود الخدمة'));

      // Document Tile actions
      expect(appStringService.getString('(Optional)'), equals('(اختياري)'));
      expect(appStringService.getString('Upload document (PDF / JPG / PNG)'), equals('ارفع المستند (PDF / JPG / PNG)'));
      expect(appStringService.getString('Replace'), equals('استبدال'));
      expect(appStringService.getString('Remove'), equals('حذف'));
    });

    test('6. OTP Verification Localization', () async {
      await rtlService.changeLanguage('ar', stringService: appStringService);

      expect(appStringService.getString('Verify Email'), equals('تحقق من البريد الإلكتروني'));
      expect(appStringService.getString('Enter the 4 digit code'), equals('أدخل الرمز المكون من 4 أرقام'));
      expect(appStringService.getString('Verify Code'), equals('تأكيد الرمز'));
      expect(appStringService.getString('Did not receive'), equals('لم يصلك الرمز؟'));
      expect(appStringService.getString('Send again'), equals('أرسل مرة أخرى'));
      expect(appStringService.getString('OTP sent successfully'), equals('تم إرسال رمز التحقق بنجاح'));
      expect(appStringService.getString("Otp didn't match"), equals('رمز التحقق غير مطابق'));
    });

    test('7. Dynamic Language Switching (English -> Arabic -> English)', () async {
      // Start English
      await rtlService.changeLanguage('en', stringService: appStringService);
      expect(rtlService.isArabic, false);
      expect(rtlService.direction, 'ltr');
      expect(appStringService.getString('Registration'), equals('Registration'));
      expect(appStringService.getString('Riyadh'), equals('Riyadh'));
      expect(appStringService.getString('Username'), equals('Username'));

      // Switch to Arabic
      await rtlService.changeLanguage('ar', stringService: appStringService);
      expect(rtlService.isArabic, true);
      expect(rtlService.direction, 'rtl');
      expect(appStringService.getString('Registration'), equals('التسجيل'));
      expect(appStringService.getString('Riyadh'), equals('الرياض'));
      expect(appStringService.getString('Username'), equals('اسم المستخدم'));

      // Switch back to English
      await rtlService.changeLanguage('en', stringService: appStringService);
      expect(rtlService.isArabic, false);
      expect(rtlService.direction, 'ltr');
      expect(appStringService.getString('Registration'), equals('Registration'));
      expect(appStringService.getString('Riyadh'), equals('Riyadh'));
      expect(appStringService.getString('Username'), equals('Username'));
    });
  });
}
