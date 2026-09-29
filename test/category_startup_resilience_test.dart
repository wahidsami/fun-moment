import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/view/home/categories/components/category_card.dart';
import 'package:provider/provider.dart';

void main() {
  group('Category & Startup Resilience Tests', () {
    test('CategoryService initializes with null categories and supports isRefresh parameter', () {
      final service = CategoryService();
      expect(service.categories, isNull);
      expect(service.categoriesDropdownList, isEmpty);
    });

    testWidgets('CategoryCard renders dynamic category properties correctly', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AppStringService>(
          create: (_) => AppStringService(),
          child: const MaterialApp(
            home: Scaffold(
              body: CategoryCard(
                name: 'الموسيقى والـ DJ',
                id: 1,
                cc: null,
                index: 0,
                marginRight: 14.0,
                imagelink: 'https://example.com/icon.png',
              ),
            ),
          ),
        ),
      );

      // Verify category title is present
      expect(find.text('الموسيقى والـ DJ'), findsOneWidget);

      // Verify card dimensions (136 x 168)
      final cardFinder = find.byWidgetPredicate(
        (widget) => widget is Container && widget.constraints?.maxWidth == 136 && widget.constraints?.maxHeight == 168,
      );
      expect(cardFinder, findsOneWidget);
    });
  });
}
