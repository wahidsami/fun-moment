import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/view/services/components/image_big.dart';
import 'package:funmoments/view/services/service_details_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';

void main() {
  group('Phase 1 & 2: URL Sanitization & Fallback Tests', () {
    test('sanitizeImageUrl returns placeholder on null', () {
      expect(sanitizeImageUrl(null), equals(placeHolderUrl));
    });

    test('sanitizeImageUrl returns placeholder on empty string', () {
      expect(sanitizeImageUrl(''), equals(placeHolderUrl));
    });

    test('sanitizeImageUrl returns placeholder on whitespace string', () {
      expect(sanitizeImageUrl('   \t\n  '), equals(placeHolderUrl));
    });

    test('sanitizeImageUrl returns placeholder on malformed or non-http string', () {
      expect(sanitizeImageUrl('ftp://invalid-url.com'), equals(placeHolderUrl));
      expect(sanitizeImageUrl('not_a_url'), equals(placeHolderUrl));
      expect(sanitizeImageUrl('javascript:void(0)'), equals(placeHolderUrl));
    });

    test('sanitizeImageUrl returns original url when valid http/https', () {
      const validHttp = 'http://example.com/image.jpg';
      const validHttps = 'https://example.com/image.jpg';
      expect(sanitizeImageUrl(validHttp), equals(validHttp));
      expect(sanitizeImageUrl(validHttps), equals(validHttps));
    });

    test('sanitizeImageUrl respects custom fallback', () {
      const customFallback = 'https://example.com/custom.png';
      expect(sanitizeImageUrl('', fallback: customFallback), equals(customFallback));
      expect(sanitizeImageUrl(null, fallback: customFallback), equals(customFallback));
    });
  });

  group('Phase 1 & 2: ImageBig Resilience Tests', () {
    testWidgets('ImageBig does NOT throw exception with empty imageLink', (tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: ImageBig(
                serviceName: 'Test Service',
                imageLink: '',
              ),
            ),
          ),
        ),
      );

      await tester.pump();
      final exception = tester.takeException();
      expect(exception, isNull, reason: 'ImageBig should safely fall back to placeholder without throwing No host specified in URI');
    });

    testWidgets('ImageBig does NOT throw exception with null imageLink', (tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: ImageBig(
                serviceName: 'Test Service',
                imageLink: null,
              ),
            ),
          ),
        ),
      );

      await tester.pump();
      final exception = tester.takeException();
      expect(exception, isNull);
    });

    testWidgets('ImageBig does NOT throw exception with whitespace imageLink', (tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: ImageBig(
                serviceName: 'Test Service',
                imageLink: '   ',
              ),
            ),
          ),
        ),
      );

      await tester.pump();
      final exception = tester.takeException();
      expect(exception, isNull);
    });
  });

  group('Phase 3: ServiceDetailsService State & Retry Tests', () {
    test('Initial state is not loading, no error', () {
      final service = ServiceDetailsService();
      expect(service.isloading, isFalse);
      expect(service.hasError, isFalse);
      expect(service.errorMessage, isNull);
      expect(service.serviceAllDetails, isNull);
    });

    test('Calling setLoadingTrue updates loading state and clears error', () {
      final service = ServiceDetailsService();
      service.hasError = true;
      service.errorMessage = 'Old error';
      service.setLoadingTrue();
      expect(service.isloading, isTrue);
      expect(service.hasError, isFalse);
      expect(service.errorMessage, isNull);
    });

    test('Calling setLoadingFalse marks loading false', () {
      final service = ServiceDetailsService();
      service.setLoadingTrue();
      service.setLoadingFalse();
      expect(service.isloading, isFalse);
    });

    test('fetchServiceDetails gracefully ignores null or invalid IDs', () async {
      final service = ServiceDetailsService();
      await service.fetchServiceDetails(null);
      expect(service.isloading, isFalse);
      expect(service.currentServiceId, isNull);

      await service.fetchServiceDetails('invalid_id');
      expect(service.isloading, isFalse);
      expect(service.currentServiceId, isNull);
    });
  });

  group('Phase 3 & 7: ServiceDetailsPage Error & Retry UI', () {
    testWidgets('Renders interactive Retry button when service has error', (tester) async {
      final serviceDetails = ServiceDetailsService();
      serviceDetails.hasError = true;
      serviceDetails.serviceAllDetails = 'error';
      serviceDetails.errorMessage = 'Request timed out. Please try again.';
      serviceDetails.currentServiceId = 5;

      final appString = AppStringService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: serviceDetails),
            ChangeNotifierProvider.value(value: appString),
            ChangeNotifierProvider(create: (_) => RtlService()),
          ],
          child: const MaterialApp(
            home: ServiceDetailsPage(),
          ),
        ),
      );

      await tester.pump();

      // Verify the error text and retry button appear
      expect(find.text('Request timed out. Please try again.'), findsOneWidget);
      expect(find.text('Retry'), findsOneWidget);
      expect(find.byIcon(Icons.refresh_rounded), findsOneWidget);
    });
  });
}
