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

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  HttpOverrides.global = TestHttpOverrides();

  group('Home Carousel Dynamic Tests', () {
    test('1. SliderModel parses rich bilingual and action payloads', () {
      final jsonPayload = {
        'slider-details': [
          {
            'id': 1,
            'background_image': 'assets/images/app/home/fun_moment_home_hero_01.png',
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
            'img_url': 'http://127.0.0.1:8000/assets/images/app/home/fun_moment_home_hero_01.png',
          },
          {
            'id': 2,
            'background_image': 'assets/images/app/home/fun_moment_dj_performance_01.png',
            'title': 'DJ Performance & Events',
            'sub_title': 'Everything you need for music and events',
            'action_type': 'category',
            'action_target': '7',
            'order': 1,
            'btn_text': 'Explore',
            'img_url': 'http://127.0.0.1:8000/assets/images/app/home/fun_moment_dj_performance_01.png',
          },
          {
            'id': 3,
            'background_image': 'assets/images/app/home/promo.png',
            'title': 'Website Promo',
            'action_type': 'url',
            'action_target': 'https://funmoment.sa/promo',
            'btn_text': 'Visit Site',
            'img_url': 'http://127.0.0.1:8000/assets/images/app/home/promo.png',
          }
        ],
        'image_url': [
          {
            'image_id': null,
            'path': 'assets/images/app/home/fun_moment_home_hero_01.png',
            'img_url': 'http://127.0.0.1:8000/assets/images/app/home/fun_moment_home_hero_01.png',
            'img_alt': 'Book a DJ'
          },
          {
            'image_id': null,
            'path': 'assets/images/app/home/fun_moment_dj_performance_01.png',
            'img_url': 'http://127.0.0.1:8000/assets/images/app/home/fun_moment_dj_performance_01.png',
            'img_alt': 'DJ Performance'
          },
          {
            'image_id': null,
            'path': 'assets/images/app/home/promo.png',
            'img_url': 'http://127.0.0.1:8000/assets/images/app/home/promo.png',
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

    test('2. SliderService sends X-Language and parses action metadata', () async {
      String? sentLanguageHeader;
      String? requestedUri;

      final mockClient = MockHttpStreamClient((request) async {
        sentLanguageHeader = request.headers['X-Language'];
        requestedUri = request.url.toString();

        final sampleJson = {
          'slider-details': [
            {
              'id': 1,
              'background_image': 'assets/images/app/home/hero.png',
              'title': 'حجز دي جي وموسيقى حية',
              'sub_title': 'ترفيه متميز للرياض',
              'title_en': 'Book a DJ',
              'title_ar': 'حجز دي جي وموسيقى حية',
              'btn_text': 'احجز الآن',
              'action_type': 'service',
              'action_target': '10',
              'service_id': 10,
              'order': 0,
              'img_url': 'http://127.0.0.1:8000/hero.png',
            }
          ],
          'image_url': [
            {
              'image_id': null,
              'path': 'assets/images/app/home/hero.png',
              'img_url': 'http://127.0.0.1:8000/hero.png',
              'img_alt': 'Hero'
            }
          ]
        };

        return http.Response.bytes(
          utf8.encode(jsonEncode(sampleJson)),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      });

      final service = SliderService();
      await service.loadSlider(isRefresh: true, client: mockClient, language: 'ar');

      expect(sentLanguageHeader, 'ar');
      expect(requestedUri, contains('lang=ar'));
      expect(service.isLoaded, true);
      expect(service.sliderDetailsList.length, 1);
      expect(service.sliderImageList.length, 1);

      final firstSlide = service.sliderDetailsList[0];
      expect(firstSlide['action_type'], 'service');
      expect(firstSlide['action_target'], '10');
      expect(firstSlide['btn_text'], 'احجز الآن');
    });

    testWidgets('3. SliderHome renders remote dynamic slides and CTA button', (tester) async {
      final sampleDetails = [
        {
          'id': 1,
          'title': 'Book a DJ',
          'subtitle': 'Premium entertainment for Riyadh and Saudi Arabia',
          'btn_text': 'Book Now',
          'action_type': 'service',
          'action_target': '42',
        }
      ];
      final sampleImages = ['https://funmoment.sa/sample.png'];

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()..direction = 'ltr'),
          ],
          child: Builder(
            builder: (context) {
              screenHeight = 800;
              screenWidth = 400;
              lnProvider = Provider.of<AppStringService>(context, listen: false);
              rtlProvider = Provider.of<RtlService>(context, listen: false);
              return MaterialApp(
                home: Scaffold(
                  body: SliderHome(
                    sliderDetailsList: sampleDetails,
                    sliderImageList: sampleImages,
                  ),
                ),
              );
            },
          ),
        ),
      );

      await tester.pump();

      expect(find.text('Book a DJ'), findsWidgets);
      expect(find.text('Premium entertainment for Riyadh and Saudi Arabia'), findsWidgets);
      expect(find.text('Book Now'), findsWidgets);
    });

    testWidgets('4. SliderHome gracefully renders local fallback when list is empty', (tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()..direction = 'ltr'),
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
      expect(find.byType(SliderHome), findsOneWidget);
    });
  });
}
