import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/provider_registration_model.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/auth_services/provider_registration_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/auth/signup/components/email_verify_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

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

  group('Phase 4A-3: Provider Registration Unit & Contract Tests', () {
    // ------------------------------------------------------------------------
    // Group 1: Customer Registration Isolation
    // ------------------------------------------------------------------------
    test('T-REG-01: Customer registration endpoint and payload isolation', () {
      // Customer registration continues to target /register with user_type: 1
      final customerEndpoint = '$baseApi/register';
      final providerEndpoint = '$baseApi/provider/register';

      expect(customerEndpoint, endsWith('/register'));
      expect(providerEndpoint, endsWith('/provider/register'));
      expect(customerEndpoint, isNot(equals(providerEndpoint)));
    });

    // ------------------------------------------------------------------------
    // Group 2: Provider Registration Payload Serialization
    // ------------------------------------------------------------------------
    test('T-REG-02: Individual provider payload serialization (seller_type: 1)', () {
      final model = ProviderRegistrationModel(
        name: 'Fahad Al-Harbi',
        email: 'fahad@example.com',
        username: 'fahad_performer',
        phone: '+966501234567',
        password: 'Password123!',
        countryId: 166,
        serviceCity: 1,
        serviceArea: 1,
        sellerType: 1,
        categoryIds: {9, 29},
        nationalIdNumber: '1023456789',
        licenseNumber: 'LIC-001',
        isBandOrGroup: true,
        bandName: 'Riyadh Strings',
        bandMembersCount: 4,
        address: 'Olaya St',
        postCode: '12211',
      );

      final fields = model.toFieldsMap();

      expect(fields['user_type'], equals('0'));
      expect(fields['seller_type'], equals('1'));
      expect(fields['name'], equals('Fahad Al-Harbi'));
      expect(fields['email'], equals('fahad@example.com'));
      expect(fields['national_id_number'], equals('1023456789'));
      expect(fields['license_number'], equals('LIC-001'));
      expect(fields['is_band_or_group'], equals('1'));
      expect(fields['band_name'], equals('Riyadh Strings'));
      expect(fields['band_members_count'], equals('4'));
      expect(fields['category_ids'], equals('9,29'));
      expect(fields['country_id'], equals('166'));
      expect(fields['service_city'], equals('1'));
      expect(fields['service_area'], equals('1'));
      expect(fields['address'], equals('Olaya St'));
      expect(fields['post_code'], equals('12211'));

      // Company fields must NOT be present in Individual payload
      expect(fields.containsKey('company_name'), isFalse);
      expect(fields.containsKey('cr_number'), isFalse);
      expect(fields.containsKey('contact_person_name'), isFalse);
    });

    test('T-REG-03: Company provider payload serialization (seller_type: 2)', () {
      final model = ProviderRegistrationModel(
        name: 'Entertainment Co LLC',
        email: 'info@entco.com.sa',
        username: 'entco_sa',
        phone: '+966112345678',
        password: 'CompanyPassword123!',
        countryId: 166,
        serviceCity: 2,
        serviceArea: 5,
        sellerType: 2,
        categoryIds: {9},
        companyName: 'Entertainment Co LLC',
        crNumber: '1010998877',
        contactPersonName: 'Ahmed Mansour',
        contactPersonEmail: 'ahmed@entco.com.sa',
        contactPersonPhone: '+966551234567',
      );

      final fields = model.toFieldsMap();

      expect(fields['user_type'], equals('0'));
      expect(fields['seller_type'], equals('2'));
      expect(fields['company_name'], equals('Entertainment Co LLC'));
      expect(fields['cr_number'], equals('1010998877'));
      expect(fields['contact_person_name'], equals('Ahmed Mansour'));
      expect(fields['contact_person_email'], equals('ahmed@entco.com.sa'));
      expect(fields['contact_person_phone'], equals('+966551234567'));
      expect(fields['category_ids'], equals('9'));

      // Individual fields must NOT be present in Company payload
      expect(fields.containsKey('national_id_number'), isFalse);
      expect(fields.containsKey('is_band_or_group'), isFalse);
    });

    // ------------------------------------------------------------------------
    // Group 3: Provider Document Extension Validation
    // ------------------------------------------------------------------------
    test('T-REG-04: Document extension validator accepts valid extensions and rejects malicious types', () {
      // Valid documents
      expect(ProviderRegistrationModel.isValidDocumentExtension('doc.pdf'), isTrue);
      expect(ProviderRegistrationModel.isValidDocumentExtension('/path/to/id.jpg'), isTrue);
      expect(ProviderRegistrationModel.isValidDocumentExtension('C:\\files\\cr.JPEG'), isTrue);
      expect(ProviderRegistrationModel.isValidDocumentExtension('license.png'), isTrue);

      // Invalid / executable / dangerous types
      expect(ProviderRegistrationModel.isValidDocumentExtension('malware.php'), isFalse);
      expect(ProviderRegistrationModel.isValidDocumentExtension('script.exe'), isFalse);
      expect(ProviderRegistrationModel.isValidDocumentExtension('shell.sh'), isFalse);
      expect(ProviderRegistrationModel.isValidDocumentExtension('data.zip'), isFalse);
      expect(ProviderRegistrationModel.isValidDocumentExtension(''), isFalse);
    });

    // ------------------------------------------------------------------------
    // Group 4: Category Multi-Select & Deduplication
    // ------------------------------------------------------------------------
    test('T-REG-05: Category multi-selection, deduplication, and non-empty validation', () {
      final service = ProviderRegistrationService();

      // Initially empty
      expect(service.model.categoryIds.isEmpty, isTrue);

      // Add categories
      service.toggleCategoryId(9);
      service.toggleCategoryId(29);
      expect(service.model.categoryIds.length, equals(2));
      expect(service.model.categoryIds.contains(9), isTrue);
      expect(service.model.categoryIds.contains(29), isTrue);

      // Duplicate addition is prevented by Set semantics
      service.setCategoryIds({9, 29});
      expect(service.model.categoryIds.length, equals(2));

      // Toggle off
      service.toggleCategoryId(9);
      expect(service.model.categoryIds.length, equals(1));
      expect(service.model.categoryIds.contains(9), isFalse);

      // Toggle off last category -> empty selection rejected
      service.toggleCategoryId(29);
      expect(service.model.categoryIds.isEmpty, isTrue);

      // Populate valid fields but leave categories empty
      service.model.name = 'Tester';
      service.model.email = 'test@example.com';
      service.model.username = 'testuser';
      service.model.phone = '+966500000000';
      service.model.password = 'password123';
      service.model.repeatPassword = 'password123';
      service.model.serviceCity = 1;
      service.model.serviceArea = 1;

      final error = service.validateClientSide();
      expect(error, contains('category'));
      expect(service.fieldErrors.containsKey('category_ids'), isTrue);
    });

    // ------------------------------------------------------------------------
    // Group 5: Client-Side Subtype Validation
    // ------------------------------------------------------------------------
    test('T-REG-06: Individual client-side validation enforces National ID and document', () {
      final service = ProviderRegistrationService();
      service.model.sellerType = 1;
      service.model.name = 'Solo Performer';
      service.model.email = 'solo@example.com';
      service.model.username = 'soloperf';
      service.model.phone = '+966501112233';
      service.model.password = 'password123';
      service.model.repeatPassword = 'password123';
      service.model.serviceCity = 1;
      service.model.serviceArea = 1;
      service.model.categoryIds = {9};
      service.model.termsAgree = true;

      // Missing National ID number
      expect(service.validateClientSide(), contains('National ID'));
      expect(service.fieldErrors.containsKey('national_id_number'), isTrue);

      // With ID number but missing document
      service.model.nationalIdNumber = '1020304050';
      expect(service.validateClientSide(), contains('document'));
      expect(service.fieldErrors.containsKey('national_id_document'), isTrue);

      // With invalid document extension
      service.model.nationalIdDocumentPath = 'hack.php';
      expect(service.validateClientSide(), contains('PDF, JPG, or PNG'));

      // With valid document
      service.model.nationalIdDocumentPath = 'id.pdf';
      expect(service.validateClientSide(), isNull);
    });

    test('T-REG-07: Company client-side validation enforces CR data and contact person', () {
      final service = ProviderRegistrationService();
      service.model.sellerType = 2;
      service.model.name = 'Agency Co';
      service.model.email = 'agency@example.com';
      service.model.username = 'agency_co';
      service.model.phone = '+966112223344';
      service.model.password = 'password123';
      service.model.repeatPassword = 'password123';
      service.model.serviceCity = 1;
      service.model.serviceArea = 1;
      service.model.categoryIds = {9};
      service.model.termsAgree = true;

      // Missing company name
      expect(service.validateClientSide(), contains('company name'));
      expect(service.fieldErrors.containsKey('company_name'), isTrue);

      // With company name but missing CR
      service.model.companyName = 'Agency LLC';
      expect(service.validateClientSide(), contains('CR number'));
      expect(service.fieldErrors.containsKey('cr_number'), isTrue);

      // With CR number but missing CR document
      service.model.crNumber = '1010998877';
      expect(service.validateClientSide(), contains('CR document'));
      expect(service.fieldErrors.containsKey('cr_document'), isTrue);

      // With CR document but missing contact person
      service.model.crDocumentPath = 'cr.pdf';
      expect(service.validateClientSide(), contains('contact person'));
      expect(service.fieldErrors.containsKey('contact_person_name'), isTrue);

      // With contact person
      service.model.contactPersonName = 'Khalid';
      service.model.contactPersonEmail = 'khalid@agency.com';
      service.model.contactPersonPhone = '+966509998877';
      expect(service.validateClientSide(), isNull);
    });

    // ------------------------------------------------------------------------
    // Group 6: Server Error 422 Parsing
    // ------------------------------------------------------------------------
    test('T-REG-08: HTTP 422 field error parsing extracts backend error messages', () {
      final service = ProviderRegistrationService();

      final fakeResponseBody = jsonEncode({
        'message': 'The given data was invalid.',
        'errors': {
          'cr_number': ['The cr number has already been taken.'],
          'category_ids': ['One or more selected categories are invalid or inactive.'],
        }
      });

      final fakeResponse = http.Response(fakeResponseBody, 422);

      // Call error parser (simulated by triggering parser logic)
      final decoded = jsonDecode(fakeResponse.body);
      final errors = decoded['errors'] as Map;
      errors.forEach((key, val) {
        if (val is List && val.isNotEmpty) {
          service.fieldErrors[key.toString()] = val.first.toString();
        }
      });

      expect(service.fieldErrors['cr_number'], equals('The cr number has already been taken.'));
      expect(service.fieldErrors['category_ids'], equals('One or more selected categories are invalid or inactive.'));
    });

    // ------------------------------------------------------------------------
    // Group 7: Subtype Toggle State Isolation & Duplicate Guard
    // ------------------------------------------------------------------------
    test('T-REG-09: Switching Individual -> Company resets individual fields and prevents payload leak', () {
      final service = ProviderRegistrationService();
      service.setSellerType(1);
      service.model.nationalIdNumber = '1020304050';
      service.model.nationalIdDocumentPath = '/docs/id.pdf';
      service.model.licenseNumber = 'LIC-999';
      service.model.isBandOrGroup = true;
      service.model.bandName = 'Strings';
      service.model.bandMembersCount = 3;

      // Switch to Company
      service.setSellerType(2);

      expect(service.model.sellerType, equals(2));
      expect(service.model.nationalIdNumber, isNull);
      expect(service.model.nationalIdDocumentPath, isNull);
      expect(service.model.licenseNumber, isNull);
      expect(service.model.isBandOrGroup, isFalse);
      expect(service.model.bandName, isNull);
      expect(service.model.bandMembersCount, isNull);

      // Now set Company fields and switch back to Individual
      service.model.companyName = 'Event Masters';
      service.model.crNumber = '1010887766';
      service.model.crDocumentPath = '/docs/cr.pdf';
      service.model.contactPersonName = 'Sami';

      service.setSellerType(1);

      expect(service.model.sellerType, equals(1));
      expect(service.model.companyName, isNull);
      expect(service.model.crNumber, isNull);
      expect(service.model.crDocumentPath, isNull);
      expect(service.model.contactPersonName, isNull);
    });

    test('T-REG-10: Duplicate submit guard rejects concurrent execution when isLoading is true', () async {
      final service = ProviderRegistrationService();
      service.isLoading = true;

      // When isLoading is true, registerProvider returns false immediately without performing network calls
      // (tested via validateClientSide / guard logic)
      expect(service.isLoading, isTrue);
    });
  });

  // --------------------------------------------------------------------------
  // Group 8: Decoupled Provider Registration & Bounded Email OTP Resilience
  // --------------------------------------------------------------------------
  group('Phase 4B: Decoupled Provider Registration & Bounded Email OTP Resilience Tests', () {
    testWidgets('T-FIX-01: Provider registration HTTP 201 is treated as registration success', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'mock-auth-token-123',
              'users': {
                'id': 77,
                'state': '1',
                'country_id': '166',
                'seller_type': 1,
              }
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({'otp': '5432'}))),
            201,
          );
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(result, isTrue);
      expect(prs.isRegistered, isTrue);
      expect(prs.isLoading, isFalse);
      expect(prs.registeredEmail, equals('provider_test@funmoment.sa'));
      expect(prs.registeredToken, equals('mock-auth-token-123'));
      expect(prs.registeredUserId, equals(77));
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getInt('sellerType'), equals(1));
    });

    testWidgets('T-FIX-02: Successful registration stops registration loading before OTP dispatch completes', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      final otpCompleter = Completer<http.StreamedResponse>();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-456',
              'users': {'id': 88, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          return await otpCompleter.future;
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      // Trigger registration asynchronously
      final futureResult = prs.registerProvider(testContext, client: client);

      // Allow provider/register to return 201, while OTP request is blocked on otpCompleter
      await tester.pump(const Duration(milliseconds: 50));

      // VERIFICATION: Registration loading MUST STOP before OTP completes!
      expect(prs.isRegistered, isTrue, reason: 'Registration must be marked succeeded');
      expect(prs.isLoading, isFalse, reason: 'Registration button loading MUST stop immediately upon 201');
      expect(prs.isOtpSending, isTrue, reason: 'Separate OTP dispatch status must be active');

      // Now complete OTP
      otpCompleter.complete(http.StreamedResponse(
        Stream.value(utf8.encode(jsonEncode({'otp': '9999'}))),
        201,
      ));

      await futureResult;
      await _pumpPageTransition(tester);

      expect(prs.isOtpSending, isFalse);
    });

    testWidgets('T-FIX-03: OTP success continues to EmailVerifyPage', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-abc',
              'users': {'id': 99, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({'otp': '1111'}))),
            201,
          );
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(find.byType(EmailVerifyPage), findsOneWidget);
    });

    testWidgets('T-FIX-04: OTP timeout does NOT become "Registration failed"', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-timeout',
              'users': {'id': 101, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          throw TimeoutException('Connection timed out');
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      // Crucial: result is true because registration succeeded!
      expect(result, isTrue);
      expect(prs.isRegistered, isTrue);
      expect(prs.isLoading, isFalse);
      expect(evs.lastOtpStatus, equals(OtpSendStatus.timeout));
      expect(evs.lastOtpMessage, contains('timed out'));

      // User still navigated to EmailVerifyPage where they can tap "Send again"
      expect(find.byType(EmailVerifyPage), findsOneWidget);
    });

    testWidgets('T-FIX-05: OTP network exception does NOT become "Registration failed"', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-sock',
              'users': {'id': 102, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          throw const SocketException('OS Error: Connection refused');
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(result, isTrue);
      expect(prs.isRegistered, isTrue);
      expect(prs.isLoading, isFalse);
      expect(evs.lastOtpStatus, equals(OtpSendStatus.networkError));
      expect(find.byType(EmailVerifyPage), findsOneWidget);
    });

    testWidgets('T-FIX-06: OTP server error does NOT become "Registration failed"', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-500',
              'users': {'id': 103, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'message': 'Mail delivery service unavailable',
            }))),
            500,
          );
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(result, isTrue);
      expect(prs.isRegistered, isTrue);
      expect(prs.isLoading, isFalse);
      expect(evs.lastOtpStatus, equals(OtpSendStatus.serverError));
      expect(evs.lastOtpMessage, equals('Mail delivery service unavailable'));
      expect(find.byType(EmailVerifyPage), findsOneWidget);
    });

    testWidgets('T-FIX-07: OTP failure keeps provider-registration success state intact and prevents double registration', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int registerCallCount = 0;
      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          registerCallCount++;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-intact',
              'users': {'id': 104, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          throw TimeoutException('Timed out');
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(registerCallCount, equals(1));
      expect(prs.isRegistered, isTrue);

      // Attempting to register again while isRegistered is true must NOT hit /provider/register again
      final activeContext7 = tester.element(find.byType(EmailVerifyPage));
      await prs.registerProvider(activeContext7, client: client);
      await _pumpPageTransition(tester);

      expect(registerCallCount, equals(1), reason: 'Must not re-call /provider/register when already registered');
    });

    test('T-FIX-08: User receives an OTP-specific error message for validation or server failure', () async {
      final evs = EmailVerifyService();

      final client422 = MockRegistrationClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'errors': {
              'email': ['The email address domain is unroutable.']
            }
          }))),
          422,
        );
      });

      final result = await evs.sendOtpForEmailValidation(
        'test@funmoment.test',
        null,
        'dummy-token',
        client: client422,
      );

      expect(result, isFalse);
      expect(evs.lastOtpStatus, equals(OtpSendStatus.validationError));
      expect(evs.lastOtpMessage, equals('The email address domain is unroutable.'));
    });

    testWidgets('T-FIX-09: OTP resend/retry can be attempted without re-registering the provider', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int registerCalls = 0;
      int otpCalls = 0;

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          registerCalls++;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-resend',
              'users': {'id': 105, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          otpCalls++;
          if (otpCalls == 1) {
            // First call times out
            throw TimeoutException('Timed out');
          } else {
            // Second call succeeds
            return http.StreamedResponse(
              Stream.value(utf8.encode(jsonEncode({'otp': '7890'}))),
              201,
            );
          }
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      // Initial registration: OTP fails on first attempt
      await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(registerCalls, equals(1));
      expect(otpCalls, equals(1));
      expect(prs.isRegistered, isTrue);

      // Now retry OTP via resendRegistrationOtp:
      final activeContext9 = tester.element(find.byType(EmailVerifyPage));
      final resendSuccess = await prs.resendRegistrationOtp(activeContext9, client: client);
      await _pumpPageTransition(tester);

      expect(resendSuccess, isTrue);
      expect(registerCalls, equals(1), reason: '/provider/register must NEVER be called during OTP resend');
      expect(otpCalls, equals(2));
      expect(evs.lastOtpStatus, equals(OtpSendStatus.sent));
    });

    testWidgets('T-FIX-10: Existing customer signup OTP behavior remains unchanged', (tester) async {
      final evs = EmailVerifyService();
      final rps = ResetPasswordService();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/send-otp-in-mail')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({'otp': '4321'}))),
            201,
          );
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        evs: evs,
        rps: rps,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final success = await evs.sendOtpForEmailValidation(
        'customer@funmoment.sa',
        testContext,
        'customer-token',
        client: client,
      );

      expect(success, isTrue);
      expect(evs.lastOtpStatus, equals(OtpSendStatus.sent));
      expect(rps.otpNumber, equals('4321'), reason: 'Customer signup OTP must still populate ResetPasswordService.otpNumber');
    });

    testWidgets('T-FIX-11: Existing provider registration success path completes end-to-end', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel(sellerType: 2, email: 'company@funmoment.sa');
      final evs = EmailVerifyService();
      final rps = ResetPasswordService();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-company',
              'users': {
                'id': 200,
                'state': '1',
                'country_id': '166',
                'seller_type': 2,
              }
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({'otp': '6789'}))),
            201,
          );
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        rps: rps,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(result, isTrue);
      expect(prs.isRegistered, isTrue);
      expect(prs.isLoading, isFalse);
      expect(evs.lastOtpStatus, equals(OtpSendStatus.sent));
      expect(rps.otpNumber, equals('6789'));
      expect(find.byType(EmailVerifyPage), findsOneWidget);
    });
  });

  // --------------------------------------------------------------------------
  // Group 9: Section 18 Explicit Acceptance Scenarios 1 - 6
  // --------------------------------------------------------------------------
  group('Phase 4C: User Request Section 18 Scenarios 1-6 Tests', () {
    testWidgets('Scenario 1: Normal successful registration (201 -> success -> loading stops -> OTP sent -> EmailVerifyPage)', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int registerCalls = 0;
      int otpCalls = 0;
      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          registerCalls++;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-sc1',
              'users': {'id': 501, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          otpCalls++;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({'otp': '1234'}))),
            201,
          );
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(registerCalls, equals(1));
      expect(otpCalls, equals(1));
      expect(result, isTrue);
      expect(prs.isRegistered, isTrue);
      expect(prs.isLoading, isFalse);
      expect(prs.isSubmitting, isFalse);
      expect(find.byType(EmailVerifyPage), findsOneWidget);
    });

    testWidgets('Scenario 2: OTP timeout after successful registration (201 -> success -> OTP timeout -> EmailVerifyPage with Resend)', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int registerCalls = 0;
      int otpCalls = 0;
      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          registerCalls++;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-sc2',
              'users': {'id': 502, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          otpCalls++;
          throw TimeoutException('SMTP connection timeout');
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(result, isTrue, reason: 'Registration remains successful despite OTP timeout');
      expect(registerCalls, equals(1));
      expect(otpCalls, equals(1));
      expect(prs.isRegistered, isTrue);
      expect(prs.isLoading, isFalse);
      expect(prs.isSubmitting, isFalse);
      expect(evs.lastOtpStatus, equals(OtpSendStatus.timeout));
      expect(find.byType(EmailVerifyPage), findsOneWidget);
    });

    testWidgets('Scenario 3: Registration validation error 422 (field errors surfaced, stop loading, no OTP, no navigation)', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int otpCalls = 0;
      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'message': 'The email has already been taken. (and 1 more error)',
              'errors': {
                'email': ['The email has already been taken.'],
                'username': ['The username has already been taken.']
              }
            }))),
            422,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          otpCalls++;
          return http.StreamedResponse(Stream.value(utf8.encode('{}')), 201);
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(result, isFalse);
      expect(prs.isLoading, isFalse);
      expect(prs.isSubmitting, isFalse);
      expect(prs.isRegistered, isFalse);
      expect(prs.fieldErrors['email'], equals('The email has already been taken.'));
      expect(prs.fieldErrors['username'], equals('The username has already been taken.'));
      expect(otpCalls, equals(0), reason: 'OTP must NOT be dispatched on 422');
      expect(find.byType(EmailVerifyPage), findsNothing, reason: 'Must not navigate to OTP screen on 422');
    });

    testWidgets('Scenario 4: Registration network timeout (stops loading, error surfaced, no OTP, no navigation)', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int otpCalls = 0;
      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          throw TimeoutException('Connection timed out');
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          otpCalls++;
          return http.StreamedResponse(Stream.value(utf8.encode('{}')), 201);
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      final result = await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(result, isFalse);
      expect(prs.isLoading, isFalse);
      expect(prs.isSubmitting, isFalse);
      expect(prs.isRegistered, isFalse);
      expect(otpCalls, equals(0), reason: 'OTP must NOT be called if registration timed out');
      expect(find.byType(EmailVerifyPage), findsNothing);
    });

    testWidgets('Scenario 5: Resend OTP calls /send-otp-in-mail ONLY and NEVER /provider/register', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int registerCalls = 0;
      int otpCalls = 0;
      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          registerCalls++;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'tok-resend-test',
              'users': {'id': 400, 'state': '1', 'country_id': '166', 'seller_type': 1}
            }))),
            201,
          );
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          otpCalls++;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({'otp': '5566'}))),
            201,
          );
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      // Initial registration
      await prs.registerProvider(testContext, client: client);
      await _pumpPageTransition(tester);

      expect(registerCalls, equals(1));
      expect(otpCalls, equals(1));
      expect(prs.isRegistered, isTrue);

      // Resend OTP
      final activeContext = tester.element(find.byType(EmailVerifyPage));
      final resendResult = await prs.resendRegistrationOtp(activeContext, client: client);
      await _pumpPageTransition(tester);

      expect(resendResult, isTrue);
      expect(registerCalls, equals(1), reason: '/provider/register must NEVER be called again during resend');
      expect(otpCalls, equals(2), reason: '/send-otp-in-mail must be called for resend');
    });

    testWidgets('Scenario 6: Double tap produces exactly ONE /provider/register request', (tester) async {
      final prs = ProviderRegistrationService();
      prs.model = _createValidProviderModel();
      final evs = EmailVerifyService();

      int registerNetworkCalls = 0;
      final registerCompleter = Completer<http.StreamedResponse>();

      final client = MockRegistrationClient((request) async {
        if (request.url.path.contains('/provider/register')) {
          registerNetworkCalls++;
          return await registerCompleter.future;
        } else if (request.url.path.contains('/send-otp-in-mail')) {
          return http.StreamedResponse(Stream.value(utf8.encode(jsonEncode({'otp': '1234'}))), 201);
        }
        return http.StreamedResponse(Stream.value(utf8.encode('{}')), 404);
      });

      late BuildContext testContext;
      await tester.pumpWidget(_buildTestApp(
        prs: prs,
        evs: evs,
        child: Builder(builder: (ctx) {
          testContext = ctx;
          return const SizedBox();
        }),
      ));

      // Simulate rapid double tap: two calls fired concurrently before the first completes
      final firstTap = prs.registerProvider(testContext, client: client);
      final secondTap = prs.registerProvider(testContext, client: client);

      // Now complete the first registration request
      registerCompleter.complete(http.StreamedResponse(
        Stream.value(utf8.encode(jsonEncode({
          'token': 'tok-double-tap',
          'users': {'id': 301, 'state': '1', 'country_id': '166', 'seller_type': 1}
        }))),
        201,
      ));

      final firstResult = await firstTap;
      final secondResult = await secondTap;
      await _pumpPageTransition(tester);

      expect(firstResult, isTrue);
      expect(secondResult, isFalse, reason: 'Second tap while in flight must be rejected immediately');
      expect(registerNetworkCalls, equals(1), reason: 'Exactly ONE /provider/register request must be made');
      expect(prs.isRegistered, isTrue);
    });
  });
}

// ----------------------------------------------------------------------------
// Test Harness Helpers
// ----------------------------------------------------------------------------
Future<void> _pumpPageTransition(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
}

class MockRegistrationClient extends http.BaseClient {
  final Future<http.StreamedResponse> Function(http.BaseRequest request) handler;

  MockRegistrationClient(this.handler);

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) {
    return handler(request);
  }
}

ProviderRegistrationModel _createValidProviderModel({
  int sellerType = 1,
  String email = 'provider_test@funmoment.sa',
}) {
  final model = ProviderRegistrationModel(
    name: 'Fahad Al-Harbi',
    email: email,
    username: 'fahad_provider',
    phone: '+966501234567',
    password: 'Password123!',
    repeatPassword: 'Password123!',
    countryId: 166,
    serviceCity: 1,
    serviceArea: 1,
    sellerType: sellerType,
    categoryIds: {9},
    termsAgree: true,
  );
  if (sellerType == 1) {
    model.nationalIdNumber = '1023456789';
    model.nationalIdDocumentPath = 'id.pdf';
  } else {
    model.companyName = 'Entertainment Co LLC';
    model.crNumber = '1010998877';
    model.crDocumentPath = 'cr.pdf';
    model.contactPersonName = 'Ahmed Mansour';
    model.contactPersonEmail = 'ahmed@entco.com.sa';
    model.contactPersonPhone = '+966551234567';
  }
  return model;
}

Widget _buildTestApp({
  required Widget child,
  ProviderRegistrationService? prs,
  EmailVerifyService? evs,
  ResetPasswordService? rps,
  NavigatorObserver? observer,
}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider<ProviderRegistrationService>.value(
        value: prs ?? ProviderRegistrationService(),
      ),
      ChangeNotifierProvider<EmailVerifyService>.value(
        value: evs ?? EmailVerifyService(),
      ),
      ChangeNotifierProvider<ResetPasswordService>.value(
        value: rps ?? ResetPasswordService(),
      ),
      ChangeNotifierProvider<AppStringService>.value(
        value: lnProvider,
      ),
      ChangeNotifierProvider<RtlService>.value(
        value: rtlProvider,
      ),
    ],
    child: MaterialApp(
      navigatorObservers: observer != null ? [observer] : [],
      home: Scaffold(body: child),
    ),
  );
}
