import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/service/auth_services/reset_pass_otp_service.dart';
import 'package:flutter/services.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    lnProvider = AppStringService();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('PonnamKarthik/fluttertoast'),
            (MethodCall methodCall) async => true);
  });

  group('Phase 1 Authentication & OTP Lifecycle Client Tests', () {
    test('T-OTP-01: sendOtpForEmailValidation handles server response without expecting plain OTP', () async {
      final mockClient = MockClient((request) async {
        expect(request.url.path, contains('/send-otp-in-mail'));
        final body = jsonDecode(request.body);
        expect(body['email'], equals('buyer@example.com'));

        // Server does NOT include OTP in response
        return http.Response(
          jsonEncode({
            'email': 'buyer@example.com',
            'message': 'Verification code sent successfully',
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = EmailVerifyService();
      final result = await service.sendOtpForEmailValidation(
        'buyer@example.com',
        null,
        'mock_token',
        client: mockClient,
      );

      expect(result, isTrue);
      expect(service.lastOtpStatus, equals(OtpSendStatus.sent));
      expect(service.lastOtpCode, isNull); // Plaintext code is never stored in client RAM
    });

    test('T-OTP-02: sendOtp for password reset sends purpose and handles response without plain OTP', () async {
      final mockClient = MockClient((request) async {
        expect(request.url.path, contains('/send-otp-in-mail'));
        final body = jsonDecode(request.body);
        expect(body['email'], equals('buyer@example.com'));
        expect(body['purpose'], equals('password_reset'));

        return http.Response(
          jsonEncode({
            'email': 'buyer@example.com',
            'message': 'Verification code sent successfully',
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = ResetPasswordService();
      // We pass isFromOtpPage: true to avoid context navigation in headless test
      final result = await service.sendOtp(
        'buyer@example.com',
        null as dynamic,
        isFromOtpPage: true,
        client: mockClient,
      );

      expect(result, isTrue);
      expect(service.otpNumber, isNull); // Plaintext OTP not expected in service state
    });

    test('T-OTP-03: resetPassword requires matching passwords locally before making network call', () async {
      final service = ResetPasswordService();
      final result = await service.resetPassword(
        'NewPassword123!',
        'DifferentPassword123!',
        'buyer@example.com',
        null as dynamic,
        resetToken: 'pwd_reset_test_token',
      );

      expect(result, isFalse);
    });

    test('T-OTP-04: resetPassword transmits reset_token to backend endpoint', () async {
      var requestReceived = false;
      final mockClient = MockClient((request) async {
        expect(request.url.path, contains('/reset-password'));
        final body = jsonDecode(request.body);
        expect(body['email'], equals('buyer@example.com'));
        expect(body['password'], equals('NewValidPassword123!'));
        expect(body['reset_token'], equals('pwd_reset_secure_token_12345'));
        requestReceived = true;

        return http.Response(
          jsonEncode({
            'message': 'Password updated successfully',
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = ResetPasswordService();
      // Test the HTTP execution; in headless test without navigation context,
      // it verifies payload transmission
      try {
        await service.resetPassword(
          'NewValidPassword123!',
          'NewValidPassword123!',
          'buyer@example.com',
          null,
          resetToken: 'pwd_reset_secure_token_12345',
          client: mockClient,
        );
      } catch (_) {
        // Null context navigation is expected in headless test
      }

      expect(requestReceived, isTrue);
    });

    test('T-OTP-05: ResetPasswordOtpService transmits email and otp to /verify-reset-otp', () async {
      var requestReceived = false;
      final mockClient = MockClient((request) async {
        expect(request.url.path, contains('/verify-reset-otp'));
        final body = jsonDecode(request.body);
        expect(body['email'], equals('buyer@example.com'));
        expect(body['otp'], equals('5678'));
        requestReceived = true;

        return http.Response(
          jsonEncode({
            'status': 'success',
            'reset_token': 'pwd_reset_token_verified_99999',
            'message': 'Verification successful',
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final otpService = ResetPasswordOtpService();
      try {
        await otpService.checkOtp(
          '5678',
          'buyer@example.com',
          null as dynamic,
          client: mockClient,
        );
      } catch (_) {
        // Null context navigation is expected in headless test
      }

      expect(requestReceived, isTrue);
    });

    test('T-OTP-06: EmailVerifyService sends email and otp to /verify-email-otp', () async {
      var requestReceived = false;
      final mockClient = MockClient((request) async {
        expect(request.url.path, contains('/verify-email-otp'));
        final body = jsonDecode(request.body);
        expect(body['email'], equals('buyer@example.com'));
        expect(body['otp'], equals('1234'));
        requestReceived = true;

        return http.Response(
          jsonEncode({
            'status': 'success',
            'message': 'Email verified successfully',
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final emailVerifyService = EmailVerifyService();
      try {
        await emailVerifyService.verifyOtpAndLogin(
          '1234',
          null as dynamic,
          'buyer@example.com',
          'mock_token_abc',
          42,
          'Riyadh',
          1,
          client: mockClient,
        );
      } catch (_) {
        // Null context navigation is expected in headless test
      }

      expect(requestReceived, isTrue);
    });
  });
}
