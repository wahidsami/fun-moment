import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/model/sub_category_model.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/home/categories/components/category_card.dart';
import 'package:provider/provider.dart';

void main() {
  group('Bilingual Category Model & Display Tests', () {
    test('Category deserializes name_ar and preserves name', () {
      final json = {
        'id': 1,
        'name': 'Music & DJ',
        'name_ar': 'الموسيقى والـ DJ',
        'icon': 'music.svg',
        'mobile_icon': 'https://example.com/icon.png',
      };

      final cat = Category.fromJson(json);
      expect(cat.id, 1);
      expect(cat.name, 'Music & DJ');
      expect(cat.nameAr, 'الموسيقى والـ DJ');
      expect(cat.icon, 'music.svg');
      expect(cat.mobileIcon, 'https://example.com/icon.png');
    });

    test('Category displayName returns name_ar for Arabic and name for English', () {
      final cat = Category(
        id: 1,
        name: 'Music & DJ',
        nameAr: 'الموسيقى والـ DJ',
      );

      // In Arabic mode
      expect(cat.displayName(true), 'الموسيقى والـ DJ');

      // In English mode
      expect(cat.displayName(false), 'Music & DJ');
    });

    test('Category displayName falls back to name if name_ar is null or empty in Arabic mode', () {
      final catNullAr = Category(id: 2, name: 'Food & Hospitality', nameAr: null);
      expect(catNullAr.displayName(true), 'Food & Hospitality');

      final catEmptyAr = Category(id: 2, name: 'Food & Hospitality', nameAr: '   ');
      expect(catEmptyAr.displayName(true), 'Food & Hospitality');
    });

    test('SubCategory deserializes name_ar and provides displayName', () {
      final json = {
        'id': 10,
        'name': 'DJ Equipment',
        'name_ar': 'معدات دي جي',
      };

      final sub = SubCategory.fromJson(json);
      expect(sub.id, 10);
      expect(sub.name, 'DJ Equipment');
      expect(sub.nameAr, 'معدات دي جي');
      expect(sub.displayName(true), 'معدات دي جي');
      expect(sub.displayName(false), 'DJ Equipment');
    });

    testWidgets('CategoryCard displays Arabic name when RTL is Arabic', (WidgetTester tester) async {
      final rtlService = RtlService();
      rtlService.langSlug = 'ar';
      rtlService.direction = 'rtl';

      await tester.pumpWidget(
        ChangeNotifierProvider<RtlService>.value(
          value: rtlService,
          child: const MaterialApp(
            home: Scaffold(
              body: CategoryCard(
                name: 'Music & DJ',
                nameAr: 'الموسيقى والـ DJ',
                id: 1,
                cc: null,
                index: 0,
                marginRight: 0.0,
                imagelink: null,
              ),
            ),
          ),
        ),
      );

      // Verify Arabic text is shown
      expect(find.text('الموسيقى والـ DJ'), findsOneWidget);
      expect(find.text('Music & DJ'), findsNothing);
    });

    testWidgets('CategoryCard displays English name when RTL is English', (WidgetTester tester) async {
      final rtlService = RtlService();
      rtlService.langSlug = 'en_US';
      rtlService.direction = 'ltr';

      await tester.pumpWidget(
        ChangeNotifierProvider<RtlService>.value(
          value: rtlService,
          child: const MaterialApp(
            home: Scaffold(
              body: CategoryCard(
                name: 'Music & DJ',
                nameAr: 'الموسيقى والـ DJ',
                id: 1,
                cc: null,
                index: 0,
                marginRight: 0.0,
                imagelink: null,
              ),
            ),
          ),
        ),
      );

      // Verify English text is shown
      expect(find.text('Music & DJ'), findsOneWidget);
      expect(find.text('الموسيقى والـ DJ'), findsNothing);
    });
  });
}
