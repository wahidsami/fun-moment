import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/service/rtl_service.dart';

void main() {
  group('Issue 1: Category Arabic Localization Tests', () {
    test('Category.displayName returns nameAr when present in Arabic mode', () {
      final cat = Category(
        id: 1,
        name: 'Music & DJ',
        nameAr: 'الموسيقى والـ DJ',
      );
      expect(cat.displayName(true), equals('الموسيقى والـ DJ'));
      expect(cat.displayName(false), equals('Music & DJ'));
    });

    test('Category.displayName falls back to defaultCategoryTranslations in Arabic mode when nameAr is null or empty', () {
      final cat1 = Category(
        id: 1,
        name: 'Music & DJ',
        nameAr: null,
      );
      final cat2 = Category(
        id: 2,
        name: 'Food & Hospitality',
        nameAr: '   ',
      );
      final cat3 = Category(
        id: 3,
        name: 'Sound & Lighting',
      );
      expect(cat1.displayName(true), equals('الموسيقى والـ DJ'));
      expect(cat2.displayName(true), equals('الأطعمة والضيافة'));
      expect(cat3.displayName(true), equals('الصوت والإضاءة'));
    });

    test('RtlService.isArabic is true when direction is rtl even if slug is default', () {
      final rtl = RtlService();
      rtl.direction = 'rtl';
      rtl.langSlug = 'en_US';
      expect(rtl.isArabic, isTrue);
      expect(rtl.isRtl, isTrue);
    });

    test('RtlService.isArabic is true when langSlug starts with ar', () {
      final rtl = RtlService();
      rtl.direction = 'ltr';
      rtl.langSlug = 'ar';
      expect(rtl.isArabic, isTrue);
      expect(rtl.isRtl, isTrue);
    });
  });
}
