import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/provider_registration_model.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/auth_services/google_sign_service.dart';
import 'package:funmoments/service/auth_services/provider_registration_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/terms_service.dart';
import 'package:funmoments/view/auth/signup/components/terms_conditions_modal.dart';
import 'package:funmoments/view/utils/responsive.dart';

/// Test HTTP client that intercepts requests and routes them to a custom handler.
class FakeHttpClient extends http.BaseClient {
  final Future<http.StreamedResponse> Function(http.BaseRequest request) handler;

  FakeHttpClient(this.handler);

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) {
    return handler(request);
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    lnProvider = AppStringService();
    rtlProvider = RtlService();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('PonnamKarthik/fluttertoast'),
            (call) async => null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('dev.fluttercommunity.plus/connectivity'),
            (call) async => 'wifi');
  });

  group('TermsService Unit Tests', () {
    test('T-TERMS-01: Requests role=buyer and lang=en correctly', () async {
      late Uri capturedUri;
      late Map<String, String> capturedHeaders;

      final client = FakeHttpClient((request) async {
        capturedUri = request.url;
        capturedHeaders = request.headers;
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'data': {
              'document_id': 1,
              'version_id': 10,
              'version_number': 1,
              'role': 'buyer',
              'language': 'en',
              'title': 'Buyer Terms and Conditions',
              'content': '<p>Terms content</p>',
              'content_hash': 'abc123hash',
              'published_at': '2026-10-14T00:00:00Z',
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = TermsService();
      final terms = await service.fetchCurrentTerms(
        role: 'buyer',
        lang: 'en',
        client: client,
      );

      expect(capturedUri.queryParameters['role'], equals('buyer'));
      expect(capturedUri.queryParameters['lang'], equals('en'));
      expect(capturedHeaders['Accept'], equals('application/json'));
      expect(terms.documentId, equals(1));
      expect(terms.versionId, equals(10));
      expect(terms.versionNumber, equals(1));
      expect(terms.role, equals('buyer'));
      expect(terms.language, equals('en'));
      expect(terms.title, equals('Buyer Terms and Conditions'));
      expect(terms.content, equals('<p>Terms content</p>'));
      expect(terms.contentHash, equals('abc123hash'));
      expect(terms.publishedAt, equals('2026-10-14T00:00:00Z'));
    });

    test('T-TERMS-02: Requests role=seller and lang=ar correctly', () async {
      late Uri capturedUri;

      final client = FakeHttpClient((request) async {
        capturedUri = request.url;
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'data': {
              'document_id': 2,
              'version_id': 25,
              'version_number': 3,
              'role': 'seller',
              'language': 'ar',
              'title': 'شروط وأحكام مقدم الخدمة',
              'content': '<p>المحتوى العربي</p>',
              'content_hash': 'def456hash',
              'published_at': '2026-10-14T00:00:00Z',
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = TermsService();
      final terms = await service.fetchCurrentTerms(
        role: 'seller',
        lang: 'ar',
        client: client,
      );

      expect(capturedUri.queryParameters['role'], equals('seller'));
      expect(capturedUri.queryParameters['lang'], equals('ar'));
      expect(terms.versionId, equals(25));
      expect(terms.role, equals('seller'));
      expect(terms.language, equals('ar'));
      expect(terms.title, equals('شروط وأحكام مقدم الخدمة'));
    });

    test('T-TERMS-03: 404 response throws TermsUnavailableException without fabricated text', () async {
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'unavailable',
            'message': 'Terms & Conditions are not currently available for this role and language.',
          }))),
          404,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = TermsService();
      expect(
        () => service.fetchCurrentTerms(role: 'buyer', lang: 'ar', client: client),
        throwsA(isA<TermsUnavailableException>()),
      );
    });

    test('T-TERMS-04: Malformed payload missing version_id throws TermsException', () async {
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'data': {
              'document_id': 1,
              'role': 'buyer',
              // missing version_id and content
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = TermsService();
      expect(
        () => service.fetchCurrentTerms(role: 'buyer', lang: 'en', client: client),
        throwsA(isA<TermsException>()),
      );
    });

    test('T-TERMS-05: 500 server error throws TermsException', () async {
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'message': 'Internal server error',
          }))),
          500,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = TermsService();
      expect(
        () => service.fetchCurrentTerms(role: 'buyer', lang: 'en', client: client),
        throwsA(isA<TermsException>()),
      );
    });
  });

  group('Seller Registration Terms Integration', () {
    test('T-SELLER-01: Model defaults termsAgree to false and version metadata to null', () {
      final model = ProviderRegistrationModel();
      expect(model.termsAgree, isFalse);
      expect(model.termsVersionId, isNull);
      expect(model.termsLanguage, isNull);
    });

    test('T-SELLER-02: toFieldsMap() serializes terms_conditions, terms_version_id, and terms_language', () {
      final model = ProviderRegistrationModel(
        name: 'Ahmed',
        email: 'ahmed@example.com',
        username: 'ahmed123',
        phone: '0501234567',
        password: 'Password123!',
        termsAgree: true,
        termsVersionId: 42,
        termsLanguage: 'ar',
      );

      final fields = model.toFieldsMap();
      expect(fields['terms_conditions'], equals('1'));
      expect(fields['terms_version_id'], equals('42'));
      expect(fields['terms_language'], equals('ar'));
    });

    test('T-SELLER-03: validateClientSide blocks submission if termsAgree is false', () {
      final prs = ProviderRegistrationService();
      prs.model.name = 'Ahmed';
      prs.model.email = 'ahmed@example.com';
      prs.model.username = 'ahmed123';
      prs.model.phone = '0501234567';
      prs.model.password = 'Password123!';
      prs.model.repeatPassword = 'Password123!';
      prs.model.serviceCity = 1;
      prs.model.serviceArea = 1;
      prs.model.categoryIds = {1};
      prs.model.nationalIdNumber = '1023456789';
      prs.model.nationalIdDocumentPath = 'national.pdf';
      prs.model.termsAgree = false;

      final err = prs.validateClientSide();
      expect(err, equals('You must agree to the terms and conditions'));
      expect(prs.fieldErrors['terms_conditions'], isNotNull);
    });

    test('T-SELLER-04: validateClientSide blocks submission if termsVersionId is missing', () {
      final prs = ProviderRegistrationService();
      prs.model.name = 'Ahmed';
      prs.model.email = 'ahmed@example.com';
      prs.model.username = 'ahmed123';
      prs.model.phone = '0501234567';
      prs.model.password = 'Password123!';
      prs.model.repeatPassword = 'Password123!';
      prs.model.serviceCity = 1;
      prs.model.serviceArea = 1;
      prs.model.categoryIds = {1};
      prs.model.nationalIdNumber = '1023456789';
      prs.model.nationalIdDocumentPath = 'national.pdf';
      prs.model.termsAgree = true;
      prs.model.termsVersionId = null; // Missing version!

      final err = prs.validateClientSide();
      expect(err, equals('You must agree to the terms and conditions'));
    });

    test('T-SELLER-05: setTermsAcceptance sets model fields and clears field errors', () {
      final prs = ProviderRegistrationService();
      prs.fieldErrors['terms_conditions'] = 'Error';
      prs.setTermsAcceptance(true, versionId: 99, language: 'en');

      expect(prs.model.termsAgree, isTrue);
      expect(prs.model.termsVersionId, equals(99));
      expect(prs.model.termsLanguage, equals('en'));
      expect(prs.fieldErrors['terms_conditions'], isNull);
    });

    testWidgets('T-SELLER-06: Multipart request includes terms_version_id and terms_language', (tester) async {
      late Map<String, String> capturedFields;

      final client = FakeHttpClient((request) async {
        if (request is http.MultipartRequest) {
          capturedFields = request.fields;
        }
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'token': 'mock-seller-token',
            'users': {
              'id': 100,
              'state': 'Riyadh',
              'country_id': '166',
              'seller_type': 1,
            }
          }))),
          201,
          headers: {'content-type': 'application/json'},
        );
      });

      final prs = ProviderRegistrationService();
      prs.model.name = 'Seller Name';
      prs.model.email = 'seller@example.com';
      prs.model.username = 'selleruser';
      prs.model.phone = '0555555555';
      prs.model.password = 'Password123!';
      prs.model.repeatPassword = 'Password123!';
      prs.model.serviceCity = 1;
      prs.model.serviceArea = 1;
      prs.model.categoryIds = {1};
      prs.model.nationalIdNumber = '1000000001';
      prs.model.nationalIdDocumentPath = 'test.pdf';
      prs.setTermsAcceptance(true, versionId: 88, language: 'ar');

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: prs),
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
            ChangeNotifierProvider(create: (_) => EmailVerifyService()),
            ChangeNotifierProvider(create: (_) => ResetPasswordService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                return ElevatedButton(
                  onPressed: () {
                    prs.registerProvider(ctx, client: client);
                  },
                  child: const Text('Register Seller'),
                );
              },
            ),
          ),
        ),
      );

      await tester.tap(find.text('Register Seller'));
      await tester.pump();

      expect(capturedFields['terms_conditions'], equals('1'));
      expect(capturedFields['terms_version_id'], equals('88'));
      expect(capturedFields['terms_language'], equals('ar'));
    });

    testWidgets('T-SELLER-07: Stale version 422 triggers onStaleTerms callback', (tester) async {
      bool staleTermsCalled = false;

      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'message': 'The accepted Terms & Conditions version is outdated.',
            'errors': {
              'terms_version_id': ['The accepted Terms & Conditions version is outdated.'],
            }
          }))),
          422,
          headers: {'content-type': 'application/json'},
        );
      });

      final prs = ProviderRegistrationService();
      prs.model.name = 'Seller Name';
      prs.model.email = 'seller@example.com';
      prs.model.username = 'selleruser';
      prs.model.phone = '0555555555';
      prs.model.password = 'Password123!';
      prs.model.repeatPassword = 'Password123!';
      prs.model.serviceCity = 1;
      prs.model.serviceArea = 1;
      prs.model.categoryIds = {1};
      prs.model.nationalIdNumber = '1000000001';
      prs.model.nationalIdDocumentPath = 'test.pdf';
      prs.setTermsAcceptance(true, versionId: 88, language: 'ar');

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: prs),
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                return ElevatedButton(
                  onPressed: () {
                    prs.registerProvider(
                      ctx,
                      client: client,
                      onStaleTerms: () {
                        staleTermsCalled = true;
                      },
                    );
                  },
                  child: const Text('Register Seller Stale'),
                );
              },
            ),
          ),
        ),
      );

      await tester.tap(find.text('Register Seller Stale'));
      await tester.pump();

      expect(staleTermsCalled, isTrue);
    });
  });

  group('Buyer Registration Terms Integration', () {
    testWidgets('T-BUYER-01: SignupService sends terms_version_id and terms_language in JSON body', (tester) async {
      late Map<String, dynamic> capturedBody;

      final client = FakeHttpClient((request) async {
        if (request is http.Request) {
          capturedBody = jsonDecode(request.body);
        }
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'token': 'buyer-test-token',
            'users': {
              'id': 10,
              'state': '1',
              'country_id': '166',
            }
          }))),
          201,
          headers: {'content-type': 'application/json'},
        );
      });

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => SignupService()),
            ChangeNotifierProvider(create: (_) => StateDropdownService()),
            ChangeNotifierProvider(create: (_) => AreaDropdownService()),
            ChangeNotifierProvider(create: (_) => CountryDropdownService()),
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
            ChangeNotifierProvider(create: (_) => EmailVerifyService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                return ElevatedButton(
                  onPressed: () {
                    final signupService = Provider.of<SignupService>(ctx, listen: false);
                    signupService.signup(
                      'Buyer Test',
                      'buyer@example.com',
                      'buyeruser',
                      'Password123!',
                      ctx,
                      termsVersionId: 44,
                      termsLanguage: 'ar',
                      client: client,
                    );
                  },
                  child: const Text('Register'),
                );
              },
            ),
          ),
        ),
      );

      await tester.tap(find.text('Register'));
      await tester.pump();

      expect(capturedBody['terms_conditions'], equals(1));
      expect(capturedBody['terms_version_id'], equals(44));
      expect(capturedBody['terms_language'], equals('ar'));
    });

    testWidgets('T-BUYER-02: Stale version rejection invokes onStaleTerms callback', (tester) async {
      bool staleCalled = false;

      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'message': 'The accepted terms version is outdated.',
            'errors': {
              'terms_version_id': ['The accepted terms version is outdated.'],
            }
          }))),
          422,
          headers: {'content-type': 'application/json'},
        );
      });

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => SignupService()),
            ChangeNotifierProvider(create: (_) => StateDropdownService()),
            ChangeNotifierProvider(create: (_) => AreaDropdownService()),
            ChangeNotifierProvider(create: (_) => CountryDropdownService()),
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                return ElevatedButton(
                  onPressed: () {
                    final signupService = Provider.of<SignupService>(ctx, listen: false);
                    signupService.signup(
                      'Buyer Test',
                      'buyer@example.com',
                      'buyeruser',
                      'Password123!',
                      ctx,
                      termsVersionId: 44,
                      termsLanguage: 'ar',
                      onStaleTerms: () {
                        staleCalled = true;
                      },
                      client: client,
                    );
                  },
                  child: const Text('Register'),
                );
              },
            ),
          ),
        ),
      );

      await tester.tap(find.text('Register'));
      await tester.pump();

      expect(staleCalled, isTrue);
    });
  });

  group('Social Login Terms Integration', () {
    testWidgets('T-SOCIAL-01: Existing user login (200) succeeds without terms requirement', (tester) async {
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'token': 'existing-social-token',
            'users': {
              'id': 77,
              'username': 'socialuser',
              'email': 'social@example.com',
              'user_type': 1,
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final googleService = GoogleSignInService();
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
            ChangeNotifierProvider(create: (_) => ProfileService()),
            ChangeNotifierProvider(create: (_) => PushNotificationService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                return ElevatedButton(
                  onPressed: () async {
                    await googleService.socialLogin(
                      'social@example.com',
                      'Social User',
                      'google_123',
                      1,
                      ctx,
                      client: client,
                    );
                  },
                  child: const Text('Google Login'),
                );
              },
            ),
          ),
        ),
      );

      expect(googleService.isloading, isFalse);
    });

    testWidgets('T-SOCIAL-02: Retrying social login includes terms_version_id and terms_language in payload', (tester) async {
      late Map<String, dynamic> capturedBody;

      final client = FakeHttpClient((request) async {
        if (request is http.Request) {
          capturedBody = jsonDecode(request.body);
        }
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'token': 'new-user-token',
            'users': {
              'id': 88,
              'username': 'newuser',
              'email': 'new@example.com',
              'user_type': 1,
            }
          }))),
          201,
          headers: {'content-type': 'application/json'},
        );
      });

      final googleService = GoogleSignInService();
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
            ChangeNotifierProvider(create: (_) => ProfileService()),
            ChangeNotifierProvider(create: (_) => PushNotificationService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                return ElevatedButton(
                  onPressed: () async {
                    await googleService.socialLogin(
                      'new@example.com',
                      'New User',
                      'google_456',
                      1,
                      ctx,
                      termsVersionId: 77,
                      termsLanguage: 'ar',
                      client: client,
                    );
                  },
                  child: const Text('Google Login With Terms'),
                );
              },
            ),
          ),
        ),
      );

      await tester.tap(find.text('Google Login With Terms'));
      await tester.pump();

      expect(capturedBody['terms_version_id'], equals(77));
      expect(capturedBody['terms_language'], equals('ar'));
    });
  });

  group('TermsConditionsModal Widget Tests', () {
    testWidgets('T-MODAL-01: Displays document title, version, and content from TermsService', (tester) async {
      final mockService = TermsService();
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'data': {
              'document_id': 1,
              'version_id': 15,
              'version_number': 2,
              'role': 'buyer',
              'language': 'en',
              'title': 'Official Buyer Agreement',
              'content': '<p>This is the published agreement text.</p>',
              'content_hash': 'hash999',
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: TermsConditionsModal(
                role: 'buyer',
                termsService: _MockTermsServiceWithClient(mockService, client),
                langOverride: 'en',
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Official Buyer Agreement'), findsOneWidget);
      expect(find.textContaining('v2'), findsOneWidget);
      expect(find.byType(HtmlWidget), findsOneWidget);
      expect(find.text('I Agree'), findsOneWidget);
      expect(find.text('Close'), findsOneWidget);
    });

    testWidgets('T-MODAL-02: Arabic language renders in TextDirection.rtl', (tester) async {
      final mockService = TermsService();
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'data': {
              'document_id': 2,
              'version_id': 20,
              'version_number': 1,
              'role': 'seller',
              'language': 'ar',
              'title': 'شروط وأحكام البائع',
              'content': '<p>نص الشروط</p>',
              'content_hash': 'arhash',
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()..setLanguage('ar')),
            ChangeNotifierProvider(create: (_) => RtlService()..direction = 'rtl'),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: TermsConditionsModal(
                role: 'seller',
                termsService: _MockTermsServiceWithClient(mockService, client),
                langOverride: 'ar',
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      final rtlFinder = find.byWidgetPredicate(
        (w) => w is Directionality && w.textDirection == TextDirection.rtl,
      );
      expect(rtlFinder, findsOneWidget);
      expect(find.text('أوافق'), findsOneWidget);
    });

    testWidgets('T-MODAL-03: Unavailable terms (404) shows unavailable notice and disables I Agree', (tester) async {
      final mockService = TermsService();
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'unavailable',
            'message': 'Terms & Conditions are not currently available for this role and language.',
          }))),
          404,
          headers: {'content-type': 'application/json'},
        );
      });

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: TermsConditionsModal(
                role: 'buyer',
                termsService: _MockTermsServiceWithClient(mockService, client),
                langOverride: 'en',
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Terms & Conditions are not currently available.'), findsOneWidget);

      // Verify "I Agree" button is disabled
      final agreeButton = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
      expect(agreeButton.onPressed, isNull);
    });

    testWidgets('T-MODAL-04: Tapping Close returns null without agreement', (tester) async {
      PublishedTerms? result;
      final mockService = TermsService();
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'data': {
              'document_id': 1,
              'version_id': 10,
              'version_number': 1,
              'role': 'buyer',
              'language': 'en',
              'title': 'Terms Title',
              'content': '<p>Content</p>',
              'content_hash': 'hash',
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: Builder(
                builder: (ctx) {
                  return ElevatedButton(
                    onPressed: () async {
                      result = await showTermsConditionsModal(
                        ctx,
                        role: 'buyer',
                        termsService: _MockTermsServiceWithClient(mockService, client),
                        langOverride: 'en',
                      );
                    },
                    child: const Text('Open Modal'),
                  );
                },
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Open Modal'));
      await tester.pumpAndSettle();

      // Tap Close button
      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();

      expect(result, isNull);
    });

    testWidgets('T-MODAL-05: Tapping I Agree returns the accepted PublishedTerms', (tester) async {
      PublishedTerms? result;
      final mockService = TermsService();
      final client = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'status': 'success',
            'data': {
              'document_id': 1,
              'version_id': 77,
              'version_number': 5,
              'role': 'buyer',
              'language': 'en',
              'title': 'Terms Title',
              'content': '<p>Content</p>',
              'content_hash': 'hash77',
            }
          }))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppStringService()),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: Builder(
                builder: (ctx) {
                  return ElevatedButton(
                    onPressed: () async {
                      result = await showTermsConditionsModal(
                        ctx,
                        role: 'buyer',
                        termsService: _MockTermsServiceWithClient(mockService, client),
                        langOverride: 'en',
                      );
                    },
                    child: const Text('Open Modal'),
                  );
                },
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Open Modal'));
      await tester.pumpAndSettle();

      // Tap "I Agree" button
      await tester.tap(find.text('I Agree'));
      await tester.pumpAndSettle();

      expect(result, isNotNull);
      expect(result!.versionId, equals(77));
      expect(result!.versionNumber, equals(5));
      expect(result!.title, equals('Terms Title'));
    });
  });
}

/// Helper that wraps TermsService with an injected test client
class _MockTermsServiceWithClient extends TermsService {
  final TermsService _inner;
  final http.Client _client;

  _MockTermsServiceWithClient(this._inner, this._client);

  @override
  Future<PublishedTerms> fetchCurrentTerms({
    required String role,
    required String lang,
    http.Client? client,
    Duration timeout = const Duration(seconds: 15),
  }) {
    return _inner.fetchCurrentTerms(
      role: role,
      lang: lang,
      client: _client,
      timeout: timeout,
    );
  }
}
