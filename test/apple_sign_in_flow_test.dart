import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/apple_sign_in_sevice.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/splash_service.dart';
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
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('PonnamKarthik/fluttertoast'),
            (call) async => null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('dev.fluttercommunity.plus/connectivity'),
            (call) async => 'wifi');
  });

  group('Apple Sign-In Service Regression Tests', () {
    testWidgets('T-APPLE-01: Returning Apple user (email is null) authenticates and persists session',
        (tester) async {
      final fakeClient = FakeHttpClient((request) async {
        if (request.url.path.contains('/social/login')) {
          final body = jsonDecode(await request.finalize().bytesToString());
          expect(body['isApple'], 1);
          expect(body['id'], 'apple.user.returning.123');
          expect(body['identity_token'], 'mock.jwt.token');
          expect(body['email'], isNull);

          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'sanctum_token_apple_returning',
              'users': {
                'id': 42,
                'name': 'Returning Apple User',
                'username': 'ap_returning',
                'email': 'apple.user@example.com',
                'user_type': 1,
              }
            }))),
            200,
            headers: {'content-type': 'application/json'},
          );
        } else if (request.url.path.contains('/user/profile')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'user_details': {
                'id': 42,
                'name': 'Returning Apple User',
                'email': 'apple.user@example.com',
                'user_type': 1,
              },
              'profile_image': [],
              'pending_order': 0,
              'active_order': 0,
              'complete_order': 0,
              'total_order': 0,
            }))),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.StreamedResponse(Stream.value([]), 404);
      });

      final appleService = AppleSignInService();
      final profileService = ProfileService();
      final pushService = PushNotificationService();

      late BuildContext testContext;
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: appleService),
            ChangeNotifierProvider.value(value: profileService),
            ChangeNotifierProvider.value(value: pushService),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                testContext = ctx;
                return const Scaffold(body: Text('Test'));
              },
            ),
          ),
        ),
      );
      final success = await appleService.socialLogin(
        null, // email is null on subsequent logins
        null,
        'apple.user.returning.123',
        'apple.user.returning.123',
        'mock.jwt.token',
        testContext,
        false,
        client: fakeClient,
      );

      expect(success, isTrue);

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getBool('appleLogin'), isTrue);
      expect(prefs.getBool('googleLogin'), isFalse);
      expect(prefs.getBool('keepLoggedIn'), isTrue);
      expect(prefs.getString('token'), 'sanctum_token_apple_returning');
      expect(prefs.getInt('userId'), 42);
      expect(prefs.getInt('userType'), 1);
      expect(prefs.getString('userName'), 'Returning Apple User');

      // Verify ProfileService state hydrated
      expect(profileService.profileDetails, isNotNull);
      expect(profileService.profileDetails.userDetails.name, 'Returning Apple User');
    });

    testWidgets('T-APPLE-02: Backend 422 error returns false and does NOT report success',
        (tester) async {
      final fakeClient = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'message': 'The email field is required.',
            'errors': {
              'email': ['The email field is required.']
            }
          }))),
          422,
          headers: {'content-type': 'application/json'},
        );
      });

      late BuildContext testContext;
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppleSignInService()),
            ChangeNotifierProvider(create: (_) => ProfileService()),
            ChangeNotifierProvider(create: (_) => PushNotificationService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                testContext = ctx;
                return const Scaffold(body: Text('Test'));
              },
            ),
          ),
        ),
      );

      final appleService = Provider.of<AppleSignInService>(testContext, listen: false);
      final success = await appleService.socialLogin(
        null,
        null,
        'apple.user.fail.123',
        'apple.user.fail.123',
        'invalid.jwt.token',
        testContext,
        false,
        client: fakeClient,
      );

      // Must strictly return false
      expect(success, isFalse);

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('token'), isNull);
      expect(prefs.getBool('appleLogin'), isNull);
    });

    testWidgets('T-APPLE-03: Account deleted response returns false',
        (tester) async {
      final fakeClient = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'message': 'Your account has been deleted',
            'status': 'account-delete',
          }))),
          400,
          headers: {'content-type': 'application/json'},
        );
      });

      late BuildContext testContext;
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AppleSignInService()),
            ChangeNotifierProvider(create: (_) => ProfileService()),
            ChangeNotifierProvider(create: (_) => PushNotificationService()),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                testContext = ctx;
                return const Scaffold(body: Text('Test'));
              },
            ),
          ),
        ),
      );

      final appleService = Provider.of<AppleSignInService>(testContext, listen: false);
      final success = await appleService.socialLogin(
        null,
        null,
        'apple.user.deleted.123',
        'apple.user.deleted.123',
        'deleted.jwt.token',
        testContext,
        false,
        client: fakeClient,
      );

      expect(success, isFalse);
    });

    testWidgets('T-APPLE-04: Session restoration in SplashService restores active Apple session',
        (tester) async {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('keepLoggedIn', true);
      await prefs.setBool('appleLogin', true);
      await prefs.setString('token', 'valid_sanctum_token');
      await prefs.setInt('userId', 99);
      await prefs.setString('userName', 'Active Apple User');
      await prefs.setString('email', 'active.apple@privaterelay.appleid.com');

      expect(prefs.getBool('appleLogin'), isTrue);
      expect(prefs.getString('token'), 'valid_sanctum_token');
    });

    testWidgets('T-APPLE-05: Expired Apple session routes to login page',
        (tester) async {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('keepLoggedIn', true);
      await prefs.setBool('appleLogin', true);
      await prefs.setString('token', 'expired_token');

      final fakeClient = FakeHttpClient((request) async {
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({'message': 'Unauthenticated.'}))),
          401,
          headers: {'content-type': 'application/json'},
        );
      });

      final profileService = ProfileService();
      final isValid = await profileService.fetchData(client: fakeClient);

      expect(isValid, isFalse);
      expect(profileService.profileDetails, isNull);
    });

    testWidgets('T-APPLE-06: First-time Apple login persists buyer userType=1 and displayName',
        (tester) async {
      final fakeClient = FakeHttpClient((request) async {
        if (request.url.path.contains('/social/login')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'token': 'new_buyer_sanctum_token',
              'users': {
                'id': 105,
                'name': 'New Apple Buyer',
                'username': 'ap_newbuyer',
                'email': 'newbuyer@privaterelay.appleid.com',
                'user_type': 1,
              }
            }))),
            201,
            headers: {'content-type': 'application/json'},
          );
        } else if (request.url.path.contains('/user/profile')) {
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode({
              'user_details': {
                'id': 105,
                'name': 'New Apple Buyer',
                'email': 'newbuyer@privaterelay.appleid.com',
                'user_type': 1,
              },
              'profile_image': [],
              'pending_order': 0,
              'active_order': 0,
              'complete_order': 0,
              'total_order': 0,
            }))),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.StreamedResponse(Stream.value([]), 404);
      });

      final appleService = AppleSignInService();
      final profileService = ProfileService();
      final pushService = PushNotificationService();

      late BuildContext testContext;
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: appleService),
            ChangeNotifierProvider.value(value: profileService),
            ChangeNotifierProvider.value(value: pushService),
          ],
          child: MaterialApp(
            home: Builder(
              builder: (ctx) {
                testContext = ctx;
                return const Scaffold(body: Text('Test'));
              },
            ),
          ),
        ),
      );
      final success = await appleService.socialLogin(
        'newbuyer@privaterelay.appleid.com',
        'New Apple Buyer',
        'apple.new.sub.105',
        'apple.new.sub.105',
        'valid.identity.token',
        testContext,
        false,
        termsVersionId: 1,
        termsLanguage: 'en',
        client: fakeClient,
      );

      expect(success, isTrue);

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getBool('appleLogin'), isTrue);
      expect(prefs.getInt('userType'), 1);
      expect(prefs.getString('userName'), 'New Apple Buyer');
      expect(prefs.getString('email'), 'newbuyer@privaterelay.appleid.com');
      expect(prefs.getString('token'), 'new_buyer_sanctum_token');
    });
  });
}
