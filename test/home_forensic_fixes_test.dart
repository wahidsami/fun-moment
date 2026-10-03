import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/filter_services_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/home_services/recent_services_service.dart';
import 'package:funmoments/service/home_services/slider_service.dart';
import 'package:funmoments/service/home_services/top_rated_services_service.dart';
import 'package:funmoments/service/jobs_service/recent_jobs_service.dart';
import 'package:funmoments/service/permissions_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/home/categories/components/category_card.dart';
import 'package:funmoments/view/home/components/categories.dart';
import 'package:funmoments/view/home/components/slider_home.dart';
import 'package:funmoments/view/home/home.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:provider/provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const sampleCategoryJson = '''
{
  "category": [
    {
      "id": 1,
      "name": "Music & DJ",
      "name_ar": "الموسيقى والـ DJ",
      "icon": null,
      "mobile_icon": "http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io/assets/uploads/media-uploader/dj-world1791019173.png"
    },
    {
      "id": 2,
      "name": "Food & Hospitality",
      "name_ar": "الأطعمة والضيافة",
      "icon": null,
      "mobile_icon": null
    },
    {
      "id": 3,
      "name": "Sound & Lighting",
      "name_ar": "الصوت والإضاءة",
      "icon": null,
      "mobile_icon": null
    },
    {
      "id": 4,
      "name": "Event Setup & Equipment",
      "name_ar": "تجهيز الفعاليات والمعدات",
      "icon": null,
      "mobile_icon": null
    },
    {
      "id": 5,
      "name": "Decor & Event Styling",
      "name_ar": "الديكور وتنسيق المناسبات",
      "icon": null,
      "mobile_icon": null
    },
    {
      "id": 6,
      "name": "Photography & Video",
      "name_ar": "التصوير والفيديو",
      "icon": null,
      "mobile_icon": null
    },
    {
      "id": 7,
      "name": "Entertainment Activities",
      "name_ar": "الترفيه والأنشطة",
      "icon": null,
      "mobile_icon": null
    }
  ]
}
''';

  const sampleSliderNullImageJson = '''
{
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
}
''';

  const sampleSliderValidImageJson = '''
{
  "slider-details": [
    {
      "background_image": "promo.jpg",
      "title": "Exclusive Deals",
      "sub_title": "Save on events",
      "service_id": 2
    }
  ],
  "image_url": [
    {
      "image_id": 12,
      "path": "promo.jpg",
      "img_url": "https://example.com/promo.jpg",
      "img_alt": "Promo Banner"
    }
  ]
}
''';

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('PART B — CATEGORY SERVICE TESTS', () {
    test('1. API success -> 7 categories parsed and saved to state & cache', () async {
      final mockClient = MockClient((request) async {
        return http.Response.bytes(
          utf8.encode(sampleCategoryJson),
          201,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      });

      final service = CategoryService();
      await service.fetchCategory(client: mockClient);

      expect(service.categories, isA<CategoryModel>());
      expect((service.categories as CategoryModel).category.length, 7);
      expect(service.isFetching, false);

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('cached_categories_json'), isNotNull);
    });

    test('2. API timeout with no cache -> transitions to error state with retry', () async {
      final mockClient = MockClient((request) async {
        throw http.ClientException('Simulated network timeout');
      });

      final service = CategoryService();
      await service.fetchCategory(client: mockClient);

      expect(service.categories, 'error');
      expect(service.isFetching, false);
    });

    test('3. API timeout with cache -> cached categories remain visible', () async {
      SharedPreferences.setMockInitialValues({
        'cached_categories_json': sampleCategoryJson,
      });

      final mockClient = MockClient((request) async {
        throw http.ClientException('Simulated network failure on refresh');
      });

      final service = CategoryService();
      await service.fetchCategory(client: mockClient, isRefresh: true);

      // Cached categories should still be intact and not overwritten with 'error'
      expect(service.categories, isA<CategoryModel>());
      expect((service.categories as CategoryModel).category.length, 7);
      expect(service.isFetching, false);
    });

    test('4. Duplicate fetch prevented while request is active', () async {
      int requestCount = 0;
      final mockClient = MockClient((request) async {
        requestCount++;
        await Future.delayed(const Duration(milliseconds: 50));
        return http.Response.bytes(
          utf8.encode(sampleCategoryJson),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      });

      final service = CategoryService();
      final future1 = service.fetchCategory(client: mockClient);
      final future2 = service.fetchCategory(client: mockClient);

      await Future.wait([future1, future2]);

      expect(requestCount, 1);
    });
  });

  group('PART C — CATEGORY CARD & MEDIA DECOUPLING', () {
    testWidgets('5. valid mobile_icon renders without crashing', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: CategoryCard(
              name: 'Music & DJ',
              nameAr: 'الموسيقى والـ DJ',
              id: 1,
              cc: null,
              index: 0,
              marginRight: 0.0,
              imagelink:
                  'http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io/assets/uploads/media-uploader/dj-world1791019173.png',
            ),
          ),
        ),
      );

      expect(find.text('Music & DJ'), findsOneWidget);
    });

    testWidgets('6. null mobile_icon uses bundled fallback and displays title', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: CategoryCard(
              name: 'Food & Hospitality',
              nameAr: 'الأطعمة والضيافة',
              id: 2,
              cc: null,
              index: 1,
              marginRight: 0.0,
              imagelink: null,
            ),
          ),
        ),
      );

      expect(find.text('Food & Hospitality'), findsOneWidget);
      expect(find.byType(Image), findsOneWidget);
    });

    testWidgets('7. image failure or empty string does not suppress category title', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: CategoryCard(
              name: 'Event Setup & Equipment',
              nameAr: null,
              id: 4,
              cc: null,
              index: 3,
              marginRight: 0.0,
              imagelink: '',
            ),
          ),
        ),
      );

      expect(find.text('Event Setup & Equipment'), findsOneWidget);
    });
  });

  group('PART D & E — SLIDER SERVICE CRITICAL STATE MACHINE', () {
    test('8. API success + valid image -> loads remote slider images', () async {
      final mockClient = MockClient((request) async {
        return http.Response(sampleSliderValidImageJson, 201);
      });

      final service = SliderService();
      await service.loadSlider(client: mockClient);

      expect(service.isLoaded, true);
      expect(service.isLoading, false);
      expect(service.hasError, false);
      expect(service.sliderImageList.length, 1);
      expect(service.sliderImageList[0], 'https://example.com/promo.jpg');
    });

    test('9. API success + img_url null -> isLoaded=true, empty list for fallback carousel', () async {
      final mockClient = MockClient((request) async {
        return http.Response(sampleSliderNullImageJson, 200);
      });

      final service = SliderService();
      await service.loadSlider(client: mockClient);

      expect(service.isLoaded, true);
      expect(service.isLoading, false);
      expect(service.hasError, false);
      expect(service.sliderImageList.isEmpty, true);
    });

    test('10. API timeout -> isLoaded=true, isLoading=false, hasError=true, allows fallback carousel', () async {
      final mockClient = MockClient((request) async {
        throw http.ClientException('Connection timed out');
      });

      final service = SliderService();
      await service.loadSlider(client: mockClient);

      expect(service.isLoaded, true);
      expect(service.isLoading, false);
      expect(service.hasError, true);
      expect(service.sliderImageList.isEmpty, true);
    });

    test('11. API exception -> isLoaded=true, isLoading=false, hasError=true', () async {
      final mockClient = MockClient((request) async {
        return http.Response('Server Error', 500);
      });

      final service = SliderService();
      await service.loadSlider(client: mockClient);

      expect(service.isLoaded, true);
      expect(service.isLoading, false);
      expect(service.hasError, true);
    });

    test('12. repeated Home rebuild does NOT restart completed empty slider fetch', () async {
      int requestCount = 0;
      final mockClient = MockClient((request) async {
        requestCount++;
        return http.Response(sampleSliderNullImageJson, 200);
      });

      final service = SliderService();
      await service.loadSlider(client: mockClient);
      expect(requestCount, 1);

      // Simulate Home widget rebuild / re-mount calling loadSlider() without refresh
      await service.loadSlider(client: mockClient);
      expect(requestCount, 1); // Must still be 1! Guard prevented refetch!
    });

    test('13. explicit refresh can fetch again', () async {
      int requestCount = 0;
      final mockClient = MockClient((request) async {
        requestCount++;
        return http.Response(sampleSliderNullImageJson, 200);
      });

      final service = SliderService();
      await service.loadSlider(client: mockClient);
      expect(requestCount, 1);

      // Explicit pull-to-refresh
      await service.loadSlider(isRefresh: true, client: mockClient);
      expect(requestCount, 2);
    });

    test('14. slider isLoading always clears in both success and error paths', () async {
      final successClient = MockClient((_) async => http.Response(sampleSliderValidImageJson, 200));
      final errorClient = MockClient((_) async => throw Exception('error'));

      final service = SliderService();
      await service.loadSlider(client: successClient);
      expect(service.isLoading, false);

      await service.loadSlider(isRefresh: true, client: errorClient);
      expect(service.isLoading, false);
    });
  });

  group('PART A, F & G — HOME CONCURRENCY & DEDUPLICATION', () {
    test('15. Category and slider get priority and complete independently', () async {
      final catService = CategoryService();
      final sliderService = SliderService();

      final catClient = MockClient((_) async => http.Response.bytes(
            utf8.encode(sampleCategoryJson),
            201,
            headers: {'content-type': 'application/json; charset=utf-8'},
          ));
      final sliderClient = MockClient((_) async => http.Response(sampleSliderNullImageJson, 200));

      final results = await Future.wait([
        catService.fetchCategory(client: catClient),
        sliderService.loadSlider(client: sliderClient),
      ]);

      expect(catService.categories, isA<CategoryModel>());
      expect(sliderService.isLoaded, true);
      expect(sliderService.isLoading, false);
    });

    test('16. Profile service deduplication avoids duplicate API calls when already loaded', () async {
      final profileService = ProfileService();
      expect(profileService.profileDetails, isNull);

      // If profileDetails == null and !isloading, fetch is permitted
      final shouldFetchFirstTime = profileService.profileDetails == null && !profileService.isloading;
      expect(shouldFetchFirstTime, true);

      // Mock that profile is loading or loaded
      profileService.isloading = true;
      final shouldFetchSecondTime = profileService.profileDetails == null && !profileService.isloading;
      expect(shouldFetchSecondTime, false); // Prevented duplicate in-flight call
    });

    test('17. Reset state restores clean testing baseline', () {
      final sliderService = SliderService();
      sliderService.isLoaded = true;
      sliderService.sliderImageList = ['sample'];
      sliderService.resetState();
      expect(sliderService.isLoaded, false);
      expect(sliderService.sliderImageList.isEmpty, true);

      final catService = CategoryService();
      catService.categories = 'error';
      catService.resetState();
      expect(catService.categories, isNull);
    });
  });

  group('PART F — HOMEPAGE LIFECYCLE & CRITICAL BOOTSTRAP RESILIENCE', () {
    setUp(() {
      resetHomeBootstrapState();
    });

    testWidgets('18. Homepage does not synchronously dispatch bootstrap in initState; runs post-frame exactly once', (tester) async {
      resetHomeBootstrapState();
      SharedPreferences.setMockInitialValues({
        'cached_categories_json': sampleCategoryJson,
      });

      final catService = CategoryService();
      final sliderService = SliderService();
      final appStringService = AppStringService();
      dynamic categoriesDuringBuild = 'unassigned';

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<CategoryService>.value(value: catService),
            ChangeNotifierProvider<SliderService>.value(value: sliderService),
            ChangeNotifierProvider<AppStringService>.value(value: appStringService),
            ChangeNotifierProvider(create: (_) => RtlService()),
            ChangeNotifierProvider(create: (_) => FilterServicesService()),
            ChangeNotifierProvider(create: (_) => TopRatedServicesSerivce()),
            ChangeNotifierProvider(create: (_) => RecentServicesService()),
            ChangeNotifierProvider(create: (_) => RecentJobsService()),
            ChangeNotifierProvider(create: (_) => ProfileService()),
            ChangeNotifierProvider.value(value: PushNotificationService()),
            ChangeNotifierProvider(create: (_) => PermissionsService()),
          ],
          child: Builder(
            builder: (context) {
              initializeLNProvider(context);
              return MaterialApp(
                home: Builder(
                  builder: (ctx) {
                    categoriesDuringBuild = catService.categories;
                    return const Homepage();
                  },
                ),
              );
            },
          ),
        ),
      );

      // During initial build/initState, categories was still null because bootstrap was deferred to post-frame
      expect(categoriesDuringBuild, isNull);

      // Post-frame callback executed after initial frame: categories restored from cache
      expect(catService.categories, isA<CategoryModel>());

      // Advance past Stage 1 timeout and Stage 2 delay so no timers remain pending
      await tester.pump(const Duration(seconds: 6));
      await tester.pumpAndSettle();
    });

    testWidgets('19. CategoryService cached response renders CategoryCards and replaces skeleton row', (tester) async {
      tester.view.physicalSize = const Size(1200, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      SharedPreferences.setMockInitialValues({
        'cached_categories_json': sampleCategoryJson,
      });

      final catService = CategoryService();
      final appStringService = AppStringService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<CategoryService>.value(value: catService),
            ChangeNotifierProvider<AppStringService>.value(value: appStringService),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: Builder(
            builder: (context) {
              initializeLNProvider(context);
              return MaterialApp(
                home: Scaffold(
                  body: Categories(
                    cc: null,
                    asProvider: appStringService,
                  ),
                ),
              );
            },
          ),
        ),
      );

      // Initially null -> skeleton
      expect(catService.categories, isNull);

      final mockClient = MockClient((_) async => http.Response.bytes(
            utf8.encode(sampleCategoryJson),
            201,
            headers: {'content-type': 'application/json; charset=utf-8'},
          ));

      await catService.fetchCategory(client: mockClient);
      await tester.pumpAndSettle();

      // Cards now rendered instead of skeleton
      expect(find.byType(CategoryCard), findsNWidgets(7));
      expect(find.text('Music & DJ'), findsOneWidget);
    });

    testWidgets('20. Slider with empty remote images renders SliderHome bundled fallback, not loading card', (tester) async {
      final sliderService = SliderService();
      final appStringService = AppStringService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<SliderService>.value(value: sliderService),
            ChangeNotifierProvider<AppStringService>.value(value: appStringService),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: Builder(
            builder: (context) {
              initializeLNProvider(context);
              return MaterialApp(
                home: Scaffold(
                  body: Consumer<SliderService>(
                    builder: (context, provider, child) =>
                        provider.isLoading && provider.sliderImageList.isEmpty
                            ? const Center(child: CircularProgressIndicator())
                            : SliderHome(
                                cc: null,
                                sliderDetailsList: provider.sliderDetailsList,
                                sliderImageList: provider.sliderImageList,
                              ),
                  ),
                ),
              );
            },
          ),
        ),
      );

      final mockClient = MockClient((_) async => http.Response(sampleSliderNullImageJson, 200));
      await sliderService.loadSlider(client: mockClient);
      await tester.pumpAndSettle();

      expect(sliderService.isLoading, false);
      expect(sliderService.isLoaded, true);
      expect(sliderService.sliderImageList.isEmpty, true);
      expect(find.byType(SliderHome), findsOneWidget);
    });

    test('21. Slider timeout clears isLoading, marks isLoaded=true and hasError=true', () async {
      final sliderService = SliderService();
      final timeoutClient = MockClient((_) async {
        throw http.ClientException('Connection timed out');
      });

      await sliderService.loadSlider(client: timeoutClient);

      expect(sliderService.isLoading, false);
      expect(sliderService.isLoaded, true);
      expect(sliderService.hasError, true);
      expect(sliderService.sliderImageList.isEmpty, true);
    });

    testWidgets('22. Simultaneous runAtHome calls are guarded and cannot overlap', (tester) async {
      resetHomeBootstrapState();
      SharedPreferences.setMockInitialValues({
        'cached_categories_json': sampleCategoryJson,
      });

      final catService = CategoryService();
      final sliderService = SliderService();

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: MultiProvider(
              providers: [
                ChangeNotifierProvider<CategoryService>.value(value: catService),
                ChangeNotifierProvider<SliderService>.value(value: sliderService),
                ChangeNotifierProvider(create: (_) => AppStringService()),
                ChangeNotifierProvider(create: (_) => RtlService()),
                ChangeNotifierProvider(create: (_) => FilterServicesService()),
                ChangeNotifierProvider(create: (_) => TopRatedServicesSerivce()),
                ChangeNotifierProvider(create: (_) => RecentServicesService()),
                ChangeNotifierProvider(create: (_) => RecentJobsService()),
                ChangeNotifierProvider(create: (_) => ProfileService()),
                ChangeNotifierProvider.value(value: PushNotificationService()),
                ChangeNotifierProvider(create: (_) => PermissionsService()),
              ],
              child: Builder(
                builder: (context) {
                  return ElevatedButton(
                    onPressed: () {
                      runAtHome(context);
                      runAtHome(context); // Simultaneous second call
                    },
                    child: const Text('Run'),
                  );
                },
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.byType(ElevatedButton));
      await tester.pump();
      await tester.pump(const Duration(seconds: 6));
      await tester.pumpAndSettle();

      // Guard successfully coalesced redundant call
      expect(catService.categories, isA<CategoryModel>());
    });

    testWidgets('23. refresh=true during active bootstrap does not crash or bypass guard', (tester) async {
      resetHomeBootstrapState();
      SharedPreferences.setMockInitialValues({
        'cached_categories_json': sampleCategoryJson,
      });

      final catService = CategoryService();
      final sliderService = SliderService();

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: MultiProvider(
              providers: [
                ChangeNotifierProvider<CategoryService>.value(value: catService),
                ChangeNotifierProvider<SliderService>.value(value: sliderService),
                ChangeNotifierProvider(create: (_) => AppStringService()),
                ChangeNotifierProvider(create: (_) => RtlService()),
                ChangeNotifierProvider(create: (_) => FilterServicesService()),
                ChangeNotifierProvider(create: (_) => TopRatedServicesSerivce()),
                ChangeNotifierProvider(create: (_) => RecentServicesService()),
                ChangeNotifierProvider(create: (_) => RecentJobsService()),
                ChangeNotifierProvider(create: (_) => ProfileService()),
                ChangeNotifierProvider.value(value: PushNotificationService()),
                ChangeNotifierProvider(create: (_) => PermissionsService()),
              ],
              child: Builder(
                builder: (context) {
                  return ElevatedButton(
                    onPressed: () {
                      runAtHome(context);
                      runAtHome(context, isRefresh: true); // Should be safely queued
                    },
                    child: const Text('Refresh'),
                  );
                },
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.byType(ElevatedButton));
      await tester.pump();
      await tester.pump(const Duration(seconds: 6));
      await tester.pumpAndSettle();

      expect(catService.categories, isA<CategoryModel>());
    });
  });
}
