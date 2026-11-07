import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/auth_services/login_service.dart';
import 'package:funmoments/view/auth/signup/components/email_verify_page.dart';
import 'package:funmoments/view/utils/responsive.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    lnProvider = AppStringService();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('PonnamKarthik/fluttertoast'),
            (MethodCall methodCall) async => true);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('dev.fluttercommunity.plus/connectivity'),
            (MethodCall methodCall) async => 'wifi');
  });

  group('Phase D2 Flutter OTP Verification & Resend Flow Tests', () {
    test('D2-FLUTTER-01: sendOtpForEmailValidation transmits purpose: "email_verify" in payload', () async {
      var capturedPurpose = '';
      var capturedEmail = '';

      final mockClient = MockClient((request) async {
        expect(request.url.path, contains('/send-otp-in-mail'));
        final body = jsonDecode(request.body);
        capturedEmail = body['email'] ?? '';
        capturedPurpose = body['purpose'] ?? '';

        return http.Response(
          jsonEncode({
            'email': 'buyer@funmoments.sa',
            'message': 'Verification code sent successfully',
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = EmailVerifyService();
      final result = await service.sendOtpForEmailValidation(
        'buyer@funmoments.sa',
        null,
        null,
        purpose: 'email_verify',
        client: mockClient,
      );

      expect(result, isTrue);
      expect(capturedEmail, equals('buyer@funmoments.sa'));
      expect(capturedPurpose, equals('email_verify'));
      expect(service.lastOtpStatus, equals(OtpSendStatus.sent));
      expect(service.isSendingOtp, isFalse);
    });

    test('D2-FLUTTER-02: sendOtpForEmailValidation concurrency guard blocks duplicate taps', () async {
      int requestCount = 0;

      final mockClient = MockClient((request) async {
        requestCount++;
        // Simulate network latency
        await Future.delayed(const Duration(milliseconds: 50));
        return http.Response(
          jsonEncode({'message': 'OTP sent'}),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = EmailVerifyService();

      // Launch first request
      final future1 = service.sendOtpForEmailValidation(
        'seller@funmoments.sa',
        null,
        null,
        purpose: 'email_verify',
        client: mockClient,
      );

      // Attempt immediate concurrent duplicate request while first is in-flight
      final future2 = service.sendOtpForEmailValidation(
        'seller@funmoments.sa',
        null,
        null,
        purpose: 'email_verify',
        client: mockClient,
      );

      final results = await Future.wait([future1, future2]);

      expect(results[0], isTrue);
      expect(results[1], isFalse, reason: 'Duplicate concurrent request must be rejected by lock');
      expect(requestCount, equals(1), reason: 'Only exactly 1 HTTP request should be dispatched');
      expect(service.isSendingOtp, isFalse, reason: 'Lock must be released after completion');
    });

    test('D2-FLUTTER-03: verifyOtpAndLogin parses newly issued token from response and saves credentials', () async {
      final mockClient = MockClient((request) async {
        expect(request.url.path, contains('/verify-email-otp'));
        final body = jsonDecode(request.body);
        expect(body['email'], equals('verified_user@funmoments.sa'));
        expect(body['otp'], equals('5678'));

        return http.Response(
          jsonEncode({
            'status': 'success',
            'message': 'Email verified successfully',
            'token': 'sanctum_bearer_token_d2_verified_9999',
            'users': {
              'id': 77,
              'name': 'Verified User',
              'email': 'verified_user@funmoments.sa',
              'user_type': 1,
              'email_verified': 1,
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = EmailVerifyService();
      final result = await service.verifyOtpAndLogin(
        '5678', // enteredOtp
        null, // context
        'verified_user@funmoments.sa', // email
        null, // token
        77, // userId
        '2', // state
        '2', // countryId
        client: mockClient,
      );

      expect(result, isTrue);

      // Verify credentials were saved to SharedPreferences via LoginService
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('token'), equals('sanctum_bearer_token_d2_verified_9999'));
      expect(prefs.getInt('userId'), equals(77));
      expect(prefs.getString('email'), equals('verified_user@funmoments.sa'));
    });

    test('D2-FLUTTER-04: sendOtpForEmailValidation handles delivery failure cleanly and releases lock', () async {
      final mockClient = MockClient((request) async {
        return http.Response(
          jsonEncode({
            'status': 'error',
            'message': 'Unable to send verification email at this time. Please try again.',
          }),
          500,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = EmailVerifyService();
      final result = await service.sendOtpForEmailValidation(
        'fail@funmoments.sa',
        null,
        null,
        purpose: 'email_verify',
        client: mockClient,
      );

      expect(result, isFalse);
      expect(service.lastOtpStatus, equals(OtpSendStatus.serverError));
      expect(service.isSendingOtp, isFalse, reason: 'Lock must be released on failure');
      expect(service.lastOtpMessage, contains('Unable to send verification email'));
    });

    testWidgets('D2-FLUTTER-05: EmailVerifyPage displays 60s countdown timer when initialOtpFailed is false', (tester) async {
      final emailVerifyService = EmailVerifyService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AppStringService>.value(value: lnProvider),
            ChangeNotifierProvider<EmailVerifyService>.value(value: emailVerifyService),
          ],
          child: const MaterialApp(
            home: EmailVerifyPage(
              email: 'test@funmoments.sa',
              token: null,
              userId: 101,
              state: 2,
              countryId: 2,
              userType: 1,
              initialOtpFailed: false,
            ),
          ),
        ),
      );

      await tester.pump();

      // Check countdown text is rendered inside RichText (e.g. contains "60s")
      final countdownFinder = find.byWidgetPredicate(
        (widget) => widget is RichText && widget.text.toPlainText().contains('60s'),
      );
      expect(countdownFinder, findsOneWidget);

      // Verify the page title or instructions exist
      expect(find.byType(EmailVerifyPage), findsOneWidget);

      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('D2-FLUTTER-06: EmailVerifyPage displays immediate retry notice when initialOtpFailed is true', (tester) async {
      final emailVerifyService = EmailVerifyService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AppStringService>.value(value: lnProvider),
            ChangeNotifierProvider<EmailVerifyService>.value(value: emailVerifyService),
          ],
          child: const MaterialApp(
            home: EmailVerifyPage(
              email: 'test_fail@funmoments.sa',
              token: null,
              userId: 102,
              state: 2,
              countryId: 2,
              userType: 1,
              initialOtpFailed: true,
            ),
          ),
        ),
      );

      await tester.pump();

      // Should show the guidance notice explaining initial delivery failed
      expect(find.textContaining("couldn't deliver the code"), findsOneWidget);

      // Send again should be available immediately (0s cooldown) in RichText
      final sendAgainFinder = find.byWidgetPredicate(
        (widget) => widget is RichText && widget.text.toPlainText().contains('Did not receive?'),
      );
      expect(sendAgainFinder, findsOneWidget);

      await tester.pumpWidget(const SizedBox());
    });
  });
}
