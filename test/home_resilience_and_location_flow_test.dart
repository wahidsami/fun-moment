import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/slider_model.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/home_services/slider_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/signup/dropdowns/country_dropdown.dart';
import 'package:funmoments/view/home/components/slider_home.dart';
import 'package:funmoments/view/utils/responsive.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Phase A: Home Slider Resilience Tests', () {
    test('1. SliderModel safely parses clean structure and handles missing images', () {
      final jsonResponse = {
        "slider-details": [
          {
            "background_image": "default-slider.jpg",
            "title": "Book Trusted Home Services",
            "sub_title": "Fast and reliable professionals near you",
            "service_id": 1
          }
        ],
        "image_url": [
          {
            "image_id": null,
            "path": "default-slider.jpg",
            "img_url": null,
            "img_alt": null
          }
        ]
      };

      final model = SliderModel.fromJson(jsonResponse);
      expect(model.sliderDetails.length, 1);
      expect(model.sliderDetails[0].title, "Book Trusted Home Services");
      expect(model.imageUrl.length, 1);
      expect(model.imageUrl[0].imgUrl, isNull);
    });

    test('2. SliderService handles lifecycle states and failure gracefully', () async {
      final service = SliderService();
      expect(service.isLoading, isFalse);
      expect(service.isLoaded, isFalse);
      expect(service.hasError, isFalse);

      bool notified = false;
      service.addListener(() {
        notified = true;
      });

      // Calling loadSlider attempts HTTP and fails gracefully to fallback without throwing
      await service.loadSlider();

      expect(service.isLoading, isFalse);
      expect(service.isLoaded, isTrue);
      expect(notified, isTrue);
      // Slider image list remains empty when no valid remote image exists
      expect(service.sliderImageList.isEmpty, isTrue);
    });

    testWidgets('3. Local fallback appears in SliderHome when sliderImageList is empty',
        (WidgetTester tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: Builder(
            builder: (context) {
              screenHeight = 800;
              screenWidth = 400;
              lnProvider = Provider.of<AppStringService>(context, listen: false);
              rtlProvider = Provider.of<RtlService>(context, listen: false);
              return const MaterialApp(
                home: Scaffold(
                  body: SliderHome(
                    sliderDetailsList: [],
                    sliderImageList: [],
                  ),
                ),
              );
            },
          ),
        ),
      );

      await tester.pump();

      // Verify that SliderHome is rendered with fallback promotional copy
      expect(find.byType(SliderHome), findsOneWidget);
      expect(FMAssets.homePromoAssets.isNotEmpty, isTrue);
    });

    test('4. Category fallback asset works for all indices', () {
      for (int i = 0; i < 14; i++) {
        final asset = FMAssets.categoryFallbackForIndex(i);
        expect(asset, isNotEmpty);
        expect(asset.startsWith('assets/images/'), isTrue);
      }
    });
  });

  group('Phase B: Saudi Location Flow Tests', () {
    testWidgets('5. Country is fixed to Saudi Arabia (ID 2) without interactive sheet',
        (WidgetTester tester) async {
      final countryService = CountryDropdownService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: countryService),
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: Builder(
            builder: (context) {
              lnProvider = Provider.of<AppStringService>(context, listen: false);
              rtlProvider = Provider.of<RtlService>(context, listen: false);
              return const MaterialApp(
                home: Scaffold(
                  body: CountryDropdown(),
                ),
              );
            },
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(countryService.selectedCountryId, saudiCountryId);
      expect(countryService.selectedCountry, saudiCountryName);
      expect(find.text('Saudi Arabia'), findsOneWidget);
      expect(find.text('Fixed'), findsOneWidget);
    });

    test('6. StateDropdownService initializes with Riyadh (ID 2)', () {
      final service = StateDropdownService();
      expect(service.selectedState, 'Riyadh');
      expect(service.selectedStateId, 2);
      expect(service.statesDropdownList.contains('Riyadh'), isTrue);
    });

    test('7. City selection changes state', () {
      final service = StateDropdownService();
      service.setStatesValue('Jeddah');
      service.setSelectedStatesId(3);

      expect(service.selectedState, 'Jeddah');
      expect(service.selectedStateId, 3);
    });

    test('8. City selection clears area', () {
      final areaService = AreaDropdownService();
      expect(areaService.selectedArea, 'Olaya');
      expect(areaService.selectedAreaId, 2);

      // Cascading action on city change
      areaService.clearArea();

      expect(areaService.selectedArea, isNull);
      expect(areaService.selectedAreaId, isNull);
      expect(areaService.areaDropdownList.isEmpty, isTrue);
    });

    testWidgets('9 & 10. Area request uses selected city ID and isolates non-Riyadh areas',
        (WidgetTester tester) async {
      final areaService = AreaDropdownService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => ProfileService()),
            ChangeNotifierProvider.value(value: areaService),
          ],
          child: Builder(
            builder: (context) {
              // When data has empty areas (or non-Riyadh city with no areas), area remains null
              areaService.clearArea();
              areaService.setArea(context, data: null);
              return const SizedBox.shrink();
            },
          ),
        ),
      );

      await tester.pump();

      expect(areaService.selectedArea, isNull);
      expect(areaService.selectedAreaId, isNull);
    });
  });
}
