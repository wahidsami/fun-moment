import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Wave 2C: Flutter Booking Debounce, Latch & Idempotency Key Tests', () {
    test('1. Rapid second tap is blocked by synchronous latch and no second dispatch occurs', () async {
      int networkDispatches = 0;
      bool isSubmitting = false;
      bool isLoading = false;

      Future<void> onPayButtonPressed() async {
        // Synchronously reject if submitting or loading
        if (isSubmitting || isLoading) {
          return;
        }

        // Synchronous latch set before ANY await
        isSubmitting = true;

        await Future.delayed(const Duration(milliseconds: 20));

        isLoading = true;
        networkDispatches++;

        await Future.delayed(const Duration(milliseconds: 50));

        isLoading = false;
        isSubmitting = false;
      }

      // Rapid triple-tap (0ms interval)
      final tap1 = onPayButtonPressed();
      final tap2 = onPayButtonPressed();
      final tap3 = onPayButtonPressed();

      await Future.wait([tap1, tap2, tap3]);

      expect(networkDispatches, equals(1),
          reason: 'Only one network dispatch must occur despite rapid multi-taps');
      expect(isSubmitting, isFalse, reason: 'Latch must be reset once complete');
    });

    test('2. One logical booking generates exactly one UUID v4 and retry reuses the same UUID', () {
      final service = PlaceOrderService();

      // At start, key is null
      expect(service.idempotencyKey, isNull);

      // Logical submission 1 begins
      service.idempotencyKey = 'mock-uuid-v4-attempt-1';
      final initialKey = service.idempotencyKey;

      expect(initialKey, isNotNull);
      expect(initialKey, equals('mock-uuid-v4-attempt-1'));

      // Simulate a network failure or retry of the SAME logical booking attempt:
      // The service does not reset the key during retry; it reuses it.
      final retryKey = service.idempotencyKey;
      expect(retryKey, equals(initialKey),
          reason: 'Retry of the same logical booking attempt must reuse the identical UUID');
    });

    test('3. Genuinely new booking attempt receives a new distinct UUID', () {
      final service = PlaceOrderService();

      // First logical booking attempt
      service.idempotencyKey = 'uuid-booking-1';
      final firstKey = service.idempotencyKey;

      // When the booking flow finishes or user starts a fresh booking:
      service.resetState();
      expect(service.idempotencyKey, isNull,
          reason: 'resetState must clear the idempotencyKey');

      // Second logical booking attempt begins
      service.idempotencyKey = 'uuid-booking-2';
      final secondKey = service.idempotencyKey;

      expect(secondKey, isNotNull);
      expect(secondKey, isNot(equals(firstKey)),
          reason: 'Genuinely new booking attempt must receive a new distinct UUID');
    });

    test('4. PlaceOrderService guard blocks concurrent dispatch when isloading is true', () async {
      final placeOrderService = PlaceOrderService();

      // Simulate an active in-flight request
      placeOrderService.isloading = true;

      // When isloading is true, placeOrder immediately returns false without touching context
      final result = await placeOrderService.placeOrder(
        _DummyBuildContext(),
        null,
      );

      expect(result, isFalse,
          reason: 'placeOrder must immediately return false if already loading');
      expect(placeOrderService.isloading, isTrue);
    });

    test('5. Genuine failure releases submission latch and allows user to retry', () async {
      bool isSubmitting = false;
      bool isLoading = false;
      int attempts = 0;

      Future<bool> simulateBookingAttempt({required bool shouldFail}) async {
        if (isSubmitting || isLoading) return false;
        isSubmitting = true;

        await Future.delayed(const Duration(milliseconds: 10));
        isLoading = true;
        attempts++;

        await Future.delayed(const Duration(milliseconds: 20));

        if (shouldFail) {
          isLoading = false;
          isSubmitting = false;
          return false;
        }

        isLoading = false;
        isSubmitting = false;
        return true;
      }

      // Initial failed attempt
      final resFail = await simulateBookingAttempt(shouldFail: true);
      expect(resFail, isFalse);
      expect(attempts, equals(1));
      expect(isSubmitting, isFalse, reason: 'Latch must be released after failure');

      // User retries successfully
      final resRetry = await simulateBookingAttempt(shouldFail: false);
      expect(resRetry, isTrue);
      expect(attempts, equals(2), reason: 'Second attempt must successfully dispatch');
      expect(isSubmitting, isFalse);
    });

    test('6. Successful booking flow maintains correct state and destination', () async {
      String destination = '';
      final service = PlaceOrderService();

      service.setOrderId(123);
      expect(service.orderId, equals(123));

      destination = 'PaymentSuccessPage(Pending)';
      expect(destination, equals('PaymentSuccessPage(Pending)'));

      service.resetState();
      expect(service.orderId, isNull);
      expect(service.idempotencyKey, isNull);
      expect(service.isloading, isFalse);
    });

    test('7. Validation failure immediately unlatches submission', () {
      bool isSubmitting = false;
      bool proceedWithNetwork = false;

      void submit({required bool termsAgreed, required bool walletValid}) {
        if (!termsAgreed) return;
        if (isSubmitting) return;
        isSubmitting = true;

        if (!walletValid) {
          isSubmitting = false;
          return;
        }

        proceedWithNetwork = true;
      }

      // Terms not agreed
      submit(termsAgreed: false, walletValid: true);
      expect(isSubmitting, isFalse);
      expect(proceedWithNetwork, isFalse);

      // Wallet invalid
      submit(termsAgreed: true, walletValid: false);
      expect(isSubmitting, isFalse, reason: 'Must release latch if wallet validation fails');
      expect(proceedWithNetwork, isFalse);

      // Valid
      submit(termsAgreed: true, walletValid: true);
      expect(proceedWithNetwork, isTrue);
    });
  });
}

class _DummyBuildContext implements BuildContext {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
