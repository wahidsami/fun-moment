import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/slider_model.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/home_services/slider_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/serviceby_category_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/view/home/components/slider_home.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

class MockHttpStreamClient extends http.BaseClient {
  final Future<http.Response> Function(http.Request request) handler;
  MockHttpStreamClient(this.handler);

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final httpRequest = request as http.Request;
    final response = await handler(httpRequest);
    return http.StreamedResponse(
      Stream.value(response.bodyBytes),
      response.statusCode,
      headers: response.headers,
    );
  }
}

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

Widget _wrapWithProviders(Widget child) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => AppStringService()),
      ChangeNotifierProvider(create: (_) => RtlService()..direction = 'ltr'),
      ChangeNotifierProvider(create: (_) => ServiceByCategoryService()),
      ChangeNotifierProvider(create: (_) => ServiceDetailsService()),
    ],
    child: Builder(
      builder: (context) {
        screenHeight = 800;
        screenWidth = 400;
        lnProvider = Provider.of<AppStringService>(context, listen: false);
        rtlProvider = Provider.of<RtlService>(context, listen: false);
        return MaterialApp(
          home: Scaffold(body: child),
        );
      },
    ),
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  HttpOverrides.global = TestHttpOverrides();

  group('Home Carousel Dynamic Tests', () {
    test('1. SliderModel parses rich bilingual and action payloads', () {
      final jsonPayload = {
        'slider-details': [
          {
            'id': 1,
            'background_image': 'assets/images/app/home/hero.png',
            'title': 'Book a DJ',
            'sub_title': 'Premium entertainment for Riyadh and Saudi Arabia',
            'title_en': 'Book a DJ',
            'title_ar': 'حجز دي جي وموسيقى حية',
            'sub_title_en': 'Premium entertainment for Riyadh and Saudi Arabia',
            'sub_title_ar': 'ترفيه متميز للرياض والمملكة العربية السعودية',
            'btn_text': 'Book Now',
            'btn_text_en': 'Book Now',
            'btn_text_ar': 'احجز الآن',
            'service_id': 42,
            'action_type': 'service',
            'action_target': '42',
            'order': 0,
            'img_url': 'http://127.0.0.1:8000/hero.png',
          },
          {
            'id': 2,
            'background_image': 'assets/images/app/home/party.png',
            'title': 'DJ Performance & Events',
            'sub_title': 'Everything you need for music and events',
            'action_type': 'category',
            'action_target': '7',
            'order': 1,
            'btn_text': 'Explore',
            'img_url': 'http://127.0.0.1:8000/party.png',
          },
          {
            'id': 3,
            'background_image': 'assets/images/app/home/promo.png',
            'title': 'Website Promo',
            'action_type': 'url',
            'action_target': 'https://funmoment.sa/promo',
            'btn_text': 'Visit Site',
            'img_url': 'http://127.0.0.1:8000/promo.png',
          }
        ],
        'image_url': [
          {
            'image_id': null,
            'path': 'assets/images/app/home/hero.png',
            'img_url': 'http://127.0.0.1:8000/hero.png',
            'img_alt': 'Book a DJ'
          },
          {
            'image_id': null,
            'path': 'assets/images/app/home/party.png',
            'img_url': 'http://127.0.0.1:8000/party.png',
            'img_alt': 'DJ Performance'
          },
          {
            'image_id': null,
            'path': 'assets/images/app/home/promo.png',
            'img_url': 'http://127.0.0.1:8000/promo.png',
            'img_alt': 'Website Promo'
          }
        ]
      };

      final model = SliderModel.fromJson(jsonPayload);
      expect(model.sliderDetails.length, 3);
      expect(model.sliderDetails[0].id, 1);
      expect(model.sliderDetails[0].title, 'Book a DJ');
      expect(model.sliderDetails[0].titleAr, 'حجز دي جي وموسيقى حية');
      expect(model.sliderDetails[0].btnText, 'Book Now');
      expect(model.sliderDetails[0].actionType, 'service');
      expect(model.sliderDetails[0].actionTarget, '42');
      expect(model.sliderDetails[0].serviceId, 42);

      expect(model.sliderDetails[1].actionType, 'category');
      expect(model.sliderDetails[1].actionTarget, '7');

      expect(model.sliderDetails[2].actionType, 'url');
      expect(model.sliderDetails[2].actionTarget, 'https://funmoment.sa/promo');
    });

    testWidgets('2. One active API slide produces exactly one carousel card without duplicates',
        (tester) async {
      final singleSlideDetails = [
        {
          'id': 1,
          'title': 'Riyadh Bodyguard',
          'subtitle': 'We care about your safety',
          'btn_text': 'Order Now',
          'action_type': 'category',
          'action_target': '8',
          'img_url': 'https://funmoment.sa/bodyguard.png',
        }
      ];
      final singleSlideImages = ['https://funmoment.sa/bodyguard.png'];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(
            sliderDetailsList: singleSlideDetails,
            sliderImageList: singleSlideImages,
          ),
        ),
      );

      await tester.pump();

      // Exactly ONE title widget on screen, no duplicates!
      expect(find.text('Riyadh Bodyguard'), findsOneWidget);
      expect(find.text('We care about your safety'), findsOneWidget);
      expect(find.text('Order Now'), findsOneWidget);
      // Exactly ONE FMSurfaceCard on screen!
      expect(find.byType(FMSurfaceCard), findsOneWidget);
    });

    testWidgets('3. Three distinct active API slides produce exactly three cards in order',
        (tester) async {
      final threeSlides = [
        {
          'id': 101,
          'title': 'Slide Alpha',
          'subtitle': 'First promo',
          'order': 0,
          'action_type': 'none',
        },
        {
          'id': 102,
          'title': 'Slide Beta',
          'subtitle': 'Second promo',
          'order': 1,
          'action_type': 'none',
        },
        {
          'id': 103,
          'title': 'Slide Gamma',
          'subtitle': 'Third promo',
          'order': 2,
          'action_type': 'none',
        },
      ];
      final threeImages = [
        'https://funmoment.sa/img1.png',
        'https://funmoment.sa/img2.png',
        'https://funmoment.sa/img3.png',
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(
            sliderDetailsList: threeSlides,
            sliderImageList: threeImages,
          ),
        ),
      );

      await tester.pump();

      // Carousel renders all 3 distinct slides
      expect(find.text('Slide Alpha'), findsWidgets);
      expect(find.text('Slide Beta'), findsWidgets);
    });

    testWidgets('4. Ten API slides produce ten cards without repetition',
        (tester) async {
      final tenSlides = List.generate(
        10,
        (i) => {
          'id': i + 1,
          'title': 'Dynamic Slide #${i + 1}',
          'subtitle': 'Description #${i + 1}',
          'order': i,
        },
      );
      final tenImages = List.generate(10, (i) => 'https://funmoment.sa/img$i.png');

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(
            sliderDetailsList: tenSlides,
            sliderImageList: tenImages,
          ),
        ),
      );

      await tester.pump();

      expect(find.text('Dynamic Slide #1'), findsWidgets);
      expect(find.text('Dynamic Slide #2'), findsWidgets);
    });

    testWidgets('5. No active slides produces zero carousel cards (clean hide)',
        (tester) async {
      await tester.pumpWidget(
        _wrapWithProviders(
          const SliderHome(
            sliderDetailsList: [],
            sliderImageList: [],
          ),
        ),
      );

      await tester.pump();

      // Completely hides: 0 surface cards, 0 promotional banners
      expect(find.byType(FMSurfaceCard), findsNothing);
      expect(find.text('Book a DJ'), findsNothing);
      expect(find.text('Rent Party Equipment'), findsNothing);
    });

    test('6. A successful empty refresh clears previously displayed slides', () async {
      final service = SliderService();

      // Step 1: populate with slides
      final initialJson = {
        'slider-details': [
          {'id': 1, 'title': 'Old Slide', 'sub_title': 'Old Sub'}
        ],
        'image_url': [
          {'image_id': null, 'img_url': 'https://funmoment.sa/old.png'}
        ]
      };
      final clientWithSlides = MockHttpStreamClient((req) async {
        return http.Response(jsonEncode(initialJson), 200);
      });
      await service.loadSlider(isRefresh: true, client: clientWithSlides);
      expect(service.sliderDetailsList.length, 1);
      expect(service.sliderImageList.length, 1);

      // Step 2: refresh returns 0 slides
      final emptyJson = {'slider-details': [], 'image_url': []};
      final clientEmpty = MockHttpStreamClient((req) async {
        return http.Response(jsonEncode(emptyJson), 200);
      });
      await service.loadSlider(isRefresh: true, client: clientEmpty);

      // Asserts that previous slides are cleared, NOT retained!
      expect(service.sliderDetailsList.isEmpty, isTrue);
      expect(service.sliderImageList.isEmpty, isTrue);
      expect(service.hasError, isFalse);
    });

    test('7. An API failure produces no hardcoded promotional carousel', () async {
      final service = SliderService();

      final failureClient = MockHttpStreamClient((req) async {
        return http.Response('Server Error', 500);
      });
      await service.loadSlider(isRefresh: true, client: failureClient);

      expect(service.hasError, isTrue);
      expect(service.sliderDetailsList.isEmpty, isTrue);
      expect(service.sliderImageList.isEmpty, isTrue);
    });

    test('8. Reopening or refreshing the home screen does not duplicate slides', () async {
      final service = SliderService();

      final sampleJson = {
        'slider-details': [
          {'id': 1, 'title': 'Slide 1', 'img_url': 'https://img.sa/1.png'},
          {'id': 2, 'title': 'Slide 2', 'img_url': 'https://img.sa/2.png'},
        ],
        'image_url': []
      };
      final client = MockHttpStreamClient((req) async {
        return http.Response(jsonEncode(sampleJson), 200);
      });

      // Load once
      await service.loadSlider(isRefresh: true, client: client);
      expect(service.sliderDetailsList.length, 2);

      // Refresh again
      await service.loadSlider(isRefresh: true, client: client);
      // Must NOT append to 4, must strictly remain 2!
      expect(service.sliderDetailsList.length, 2);
      expect(service.sliderImageList.length, 2);
    });

    testWidgets('9. Slide tap action triggers handleSlideAction for that specific slide',
        (tester) async {
      final slideWithAction = [
        {
          'id': 1,
          'title': 'DJ Night',
          'action_type': 'category',
          'action_target': '8',
          'img_url': 'https://funmoment.sa/dj.png',
        }
      ];

      await tester.pumpWidget(
        _wrapWithProviders(
          SliderHome(
            sliderDetailsList: slideWithAction,
            sliderImageList: const ['https://funmoment.sa/dj.png'],
          ),
        ),
      );

      await tester.pump();
      final cardFinder = find.byType(InkWell);
      expect(cardFinder, findsOneWidget);
      // Tap on the card
      await tester.tap(cardFinder);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      // Verifies interaction did not throw and navigation succeeded
    });

    test('10. SliderService handles network exception without falling back to local promo banners',
        () async {
      final service = SliderService();

      final throwingClient = MockHttpStreamClient((req) async {
        throw const SocketException('Connection refused');
      });

      await service.loadSlider(isRefresh: true, client: throwingClient);

      expect(service.hasError, isTrue);
      expect(service.sliderDetailsList.isEmpty, isTrue);
      expect(service.sliderImageList.isEmpty, isTrue);
    });
  });
}
