import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:provider/provider.dart';

import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/model/slider_model.dart';
import 'package:funmoments/service/all_services_service.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/buyer_music_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/home_services/top_all_services_service.dart';
import 'package:funmoments/service/music_player_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/serviceby_category_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/view/home/categories/all_categories_page.dart';
import 'package:funmoments/view/home/components/slider_home.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/home/top_all_service_page.dart';
import 'package:funmoments/view/music/music_marketplace_page.dart';
import 'package:funmoments/view/services/service_by_category_page.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:funmoments/view/wallet/wallet_page.dart';

class TestHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    return _MockHttpClient();
  }
}

class _MockHttpClient extends Fake implements HttpClient {
  @override
  bool autoUncompress = true;

  @override
  Future<HttpClientRequest> getUrl(Uri url) async => _MockHttpClientRequest();
}

class _MockHttpClientRequest extends Fake implements HttpClientRequest {
  @override
  final HttpHeaders headers = _MockHttpHeaders();

  @override
  Future<HttpClientResponse> close() async => _MockHttpClientResponse();
}

class _MockHttpHeaders extends Fake implements HttpHeaders {
  @override
  void add(String name, Object value, {bool preserveHeaderCase = false}) {}
  @override
  void set(String name, Object value, {bool preserveHeaderCase = false}) {}
}

class _MockHttpClientResponse extends Fake implements HttpClientResponse {
  @override
  int get statusCode => 200;

  @override
  int get contentLength => kTransparentImage.length;

  @override
  HttpClientResponseCompressionState get compressionState =>
      HttpClientResponseCompressionState.notCompressed;

  @override
  StreamSubscription<List<int>> listen(
    void Function(List<int> event)? onData, {
    Function? onError,
    void Function()? onDone,
    bool? cancelOnError,
  }) {
    return Stream<List<int>>.value(kTransparentImage).listen(
      onData,
      onError: onError,
      onDone: onDone,
      cancelOnError: cancelOnError,
    );
  }
}

final Uint8List kTransparentImage = Uint8List.fromList(<int>[
  0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A,
  0x00, 0x00, 0x00, 0x0D, 0x49, 0x48, 0x44, 0x52,
  0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01,
  0x08, 0x06, 0x00, 0x00, 0x00, 0x1F, 0x15, 0xC4,
  0x89, 0x00, 0x00, 0x00, 0x0A, 0x49, 0x44, 0x41,
  0x54, 0x78, 0x9C, 0x63, 0x00, 0x01, 0x00, 0x00,
  0x05, 0x00, 0x01, 0x0D, 0x0A, 0x2D, 0xB4, 0x00,
  0x00, 0x00, 0x00, 0x49, 0x45, 0x4E, 0x44, 0xAE,
  0x42, 0x60, 0x82,
]);

class TestNavObserver extends NavigatorObserver {
  final List<Route<dynamic>> pushedRoutes = [];

  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    pushedRoutes.add(route);
    super.didPush(route, previousRoute);
  }
}

Widget _wrapWithProviders(Widget child, {TestNavObserver? navObserver}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => AppStringService()),
      ChangeNotifierProvider(create: (_) => RtlService()..direction = 'ltr'),
      ChangeNotifierProvider(create: (_) => ServiceByCategoryService()),
      ChangeNotifierProvider(create: (_) => ServiceDetailsService()),
      ChangeNotifierProvider(create: (_) {
        final catService = CategoryService();
        catService.categories = CategoryModel(category: []);
        return catService;
      }),
      ChangeNotifierProvider(create: (_) => AllServicesService()),
      ChangeNotifierProvider(create: (_) => TopAllServicesService()),
      ChangeNotifierProvider(create: (_) => WalletService()),
      ChangeNotifierProvider(create: (_) => BuyerMusicService()),
      ChangeNotifierProvider(create: (_) => MusicPlayerService()),
    ],
    child: Builder(
      builder: (context) {
        screenHeight = 800;
        screenWidth = 400;
        lnProvider = Provider.of<AppStringService>(context, listen: false);
        rtlProvider = Provider.of<RtlService>(context, listen: false);
        return MaterialApp(
          navigatorObservers: navObserver != null ? [navObserver] : [],
          home: Scaffold(body: child),
        );
      },
    ),
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  HttpOverrides.global = TestHttpOverrides();

  setUpAll(() {
    SharedPreferences.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('xyz.luan/audioplayers.global'),
            (call) async => null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('xyz.luan/audioplayers'),
            (call) async => null);
  });

  group('Home Carousel Section Navigation Tests', () {
    test('1. SliderModel correctly parses section action_type and target', () {
      final json = {
        'slider-details': [
          {
            'id': 1,
            'title': 'Music Promo',
            'action_type': 'section',
            'action_target': 'music_marketplace',
            'img_url': 'https://funmoments.com/music.png',
          },
          {
            'id': 2,
            'title': 'All Categories Promo',
            'action_type': 'section',
            'action_target': 'all_categories',
            'img_url': 'https://funmoments.com/cat.png',
          }
        ],
        'image_url': []
      };

      final model = SliderModel.fromJson(json);
      expect(model.sliderDetails.length, 2);
      expect(model.sliderDetails[0].actionType, 'section');
      expect(model.sliderDetails[0].actionTarget, 'music_marketplace');
      expect(model.sliderDetails[1].actionType, 'section');
      expect(model.sliderDetails[1].actionTarget, 'all_categories');
    });

    testWidgets('2. Promotional card targeting music_marketplace navigates to MusicMarketplacePage', (tester) async {
      final navObserver = TestNavObserver();
      final slide = [
        {
          'id': 1,
          'title': 'Music Marketplace Banner',
          'action_type': 'section',
          'action_target': 'music_marketplace',
          'btn_text': 'Explore Tracks',
          'img_url': 'https://funmoments.com/music.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(sliderDetailsList: slide),
          navObserver: navObserver,
        ),
      );

      await tester.pump();
      expect(find.text('Music Marketplace Banner'), findsOneWidget);

      final cardFinder = find.byType(InkWell);
      expect(cardFinder, findsOneWidget);

      await tester.tap(cardFinder);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      // Assert that a route building MusicMarketplacePage was pushed
      final hasMarketplaceRoute = navObserver.pushedRoutes.any(
        (r) => r is MaterialPageRoute && r.builder.toString().contains('MusicMarketplacePage'),
      );
      expect(hasMarketplaceRoute, isTrue);
    });

    testWidgets('3. Promotional card targeting all_categories navigates to AllCategoriesPage', (tester) async {
      final navObserver = TestNavObserver();
      final slide = [
        {
          'id': 2,
          'title': 'Categories Directory Banner',
          'action_type': 'section',
          'action_target': 'all_categories',
          'btn_text': 'View Categories',
          'img_url': 'https://funmoments.com/cat.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(sliderDetailsList: slide),
          navObserver: navObserver,
        ),
      );

      await tester.pump();
      final cardFinder = find.byType(InkWell);
      await tester.tap(cardFinder);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      final hasCategoryRoute = navObserver.pushedRoutes.any(
        (r) => r is MaterialPageRoute && r.builder.toString().contains('AllCategoriesPage'),
      );
      expect(hasCategoryRoute, isTrue);
    });

    testWidgets('4. Promotional card targeting bookings tab switches HomepageHelper.tabIndex to 2', (tester) async {
      HomepageHelper.tabIndex.value = 0;

      final slide = [
        {
          'id': 3,
          'title': 'Check Your Orders',
          'action_type': 'section',
          'action_target': 'bookings',
          'img_url': 'https://funmoments.com/orders.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(SliderHome(sliderDetailsList: slide)),
      );

      await tester.pump();
      final cardFinder = find.byType(InkWell);
      await tester.tap(cardFinder);
      await tester.pump();

      expect(HomepageHelper.tabIndex.value, 2);
    });

    testWidgets('5. Promotional card targeting discover tab switches HomepageHelper.tabIndex to 1', (tester) async {
      HomepageHelper.tabIndex.value = 0;

      final slide = [
        {
          'id': 4,
          'title': 'Discover Services',
          'action_type': 'section',
          'action_target': 'discover',
          'img_url': 'https://funmoments.com/search.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(SliderHome(sliderDetailsList: slide)),
      );

      await tester.pump();
      final cardFinder = find.byType(InkWell);
      await tester.tap(cardFinder);
      await tester.pump();

      expect(HomepageHelper.tabIndex.value, 1);
    });

    testWidgets('6. Promotional card targeting wallet navigates to WalletPage', (tester) async {
      final navObserver = TestNavObserver();
      final slide = [
        {
          'id': 5,
          'title': 'Wallet Top-up Banner',
          'action_type': 'section',
          'action_target': 'wallet',
          'img_url': 'https://funmoments.com/wallet.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(sliderDetailsList: slide),
          navObserver: navObserver,
        ),
      );

      await tester.pump();
      final cardFinder = find.byType(InkWell);
      await tester.tap(cardFinder);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      final hasWalletRoute = navObserver.pushedRoutes.any(
        (r) => r is MaterialPageRoute && r.builder.toString().contains('WalletPage'),
      );
      expect(hasWalletRoute, isTrue);
    });

    testWidgets('7. Promotional card targeting top_services navigates to TopAllServicePage', (tester) async {
      final navObserver = TestNavObserver();
      final slide = [
        {
          'id': 6,
          'title': 'Top Services Banner',
          'action_type': 'section',
          'action_target': 'top_services',
          'img_url': 'https://funmoments.com/top.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(sliderDetailsList: slide),
          navObserver: navObserver,
        ),
      );

      await tester.pump();
      final cardFinder = find.byType(InkWell);
      await tester.tap(cardFinder);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      final hasTopServicesRoute = navObserver.pushedRoutes.any(
        (r) => r is MaterialPageRoute && r.builder.toString().contains('TopAllServicePage'),
      );
      expect(hasTopServicesRoute, isTrue);
    });

    testWidgets('8. Backward compatibility: Category slide routes to ServicebyCategoryPage', (tester) async {
      final navObserver = TestNavObserver();
      final slide = [
        {
          'id': 7,
          'title': 'Photography Category',
          'action_type': 'category',
          'action_target': '6',
          'img_url': 'https://funmoments.com/cat6.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(sliderDetailsList: slide),
          navObserver: navObserver,
        ),
      );

      await tester.pump();
      final cardFinder = find.byType(InkWell);
      await tester.tap(cardFinder);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      final hasCategoryRoute = navObserver.pushedRoutes.any(
        (r) => r is MaterialPageRoute && r.builder.toString().contains('ServicebyCategoryPage'),
      );
      expect(hasCategoryRoute, isTrue);
    });

    testWidgets('9. Safe fallback: Corrupt/unknown section target executes safe no-op without crashing', (tester) async {
      final navObserver = TestNavObserver();

      final slide = [
        {
          'id': 8,
          'title': 'Corrupted Target Slide',
          'action_type': 'section',
          'action_target': 'unknown_or_future_screen_key_xyz',
          'img_url': 'https://funmoments.com/corrupt.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(sliderDetailsList: slide),
          navObserver: navObserver,
        ),
      );

      await tester.pump();
      final initialRoutesCount = navObserver.pushedRoutes.length;

      final cardFinder = find.byType(InkWell);
      expect(cardFinder, findsOneWidget);

      // Tapping must NOT throw any exception
      await tester.tap(cardFinder);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      // No new route should be pushed
      expect(navObserver.pushedRoutes.length, initialRoutesCount);
    });

    testWidgets('10. Safe fallback: Action type "none" disables tap interaction', (tester) async {
      final slide = [
        {
          'id': 9,
          'title': 'Pure Visual Banner',
          'action_type': 'none',
          'action_target': null,
          'img_url': 'https://funmoments.com/visual.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(SliderHome(sliderDetailsList: slide)),
      );

      await tester.pump();
      final inkWellFinder = find.byType(InkWell);
      expect(inkWellFinder, findsOneWidget);
      final inkWell = tester.widget<InkWell>(inkWellFinder);
      expect(inkWell.onTap, isNull);
    });
  });
}
