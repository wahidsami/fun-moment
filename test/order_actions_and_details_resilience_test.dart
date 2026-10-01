import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/view/tabs/orders/orders_helper.dart';
import 'package:funmoments/service/order_details_service.dart';

void main() {
  group('OrdersHelper getOrderActions Role-Based Filtering', () {
    test('Customer on pending unpaid order sees Cancel and Report, but NOT Feedback', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: false,
        orderStatus: 0,
        paymentStatus: 'pending',
      );

      final keys = actions.map((a) => a.actionKey).toList();
      expect(keys.contains('cancel'), isTrue);
      expect(keys.contains('report'), isTrue);
      expect(keys.contains('feedback'), isFalse);
    });

    test('Customer on pending paid order CANNOT cancel directly (Financial Safety)', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: false,
        orderStatus: 0,
        paymentStatus: 'complete',
      );

      final keys = actions.map((a) => a.actionKey).toList();
      expect(keys.contains('cancel'), isFalse);
      expect(keys.contains('report'), isTrue);
      expect(keys.contains('feedback'), isFalse);
    });

    test('Customer on completed order (status 2) sees Feedback and Report, but NOT Cancel', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: false,
        orderStatus: 2,
        paymentStatus: 'complete',
      );

      final keys = actions.map((a) => a.actionKey).toList();
      expect(keys.contains('feedback'), isTrue);
      expect(keys.contains('report'), isTrue);
      expect(keys.contains('cancel'), isFalse);
    });

    test('Customer on cancelled order (status 4) sees no active actions', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: false,
        orderStatus: 4,
        paymentStatus: 'pending',
      );

      final keys = actions.map((a) => a.actionKey).toList();
      expect(keys.isEmpty, isTrue);
    });

    test('Provider NEVER sees Leave Feedback on any status', () {
      for (final status in [0, 1, 2, 3, 4]) {
        final actions = OrdersHelper().getOrderActions(
          isSeller: true,
          orderStatus: status,
          paymentStatus: 'complete',
        );
        final keys = actions.map((a) => a.actionKey).toList();
        expect(keys.contains('feedback'), isFalse, reason: 'Provider must not see feedback on status $status');
      }
    });

    test('Provider on pending order (status 0) sees Accept booking, Decline booking, and Report to admin', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: true,
        orderStatus: 0,
        paymentStatus: 'pending',
      );

      final keys = actions.map((a) => a.actionKey).toList();
      // Test 1: Pending provider order shows Accept
      expect(keys.contains('accept'), isTrue);
      expect(actions.firstWhere((a) => a.actionKey == 'accept').title, equals('Accept booking'));

      // Test 2: Pending provider order shows Decline
      expect(keys.contains('decline'), isTrue);
      expect(actions.firstWhere((a) => a.actionKey == 'decline').title, equals('Decline booking'));

      // Test 3: Pending provider order shows Report
      expect(keys.contains('report'), isTrue);
      expect(actions.firstWhere((a) => a.actionKey == 'report').title, equals('Report to admin'));

      // Must NOT reuse "cancel" key for provider decline
      expect(keys.contains('cancel'), isFalse);

      // Verify exact count and order of actions
      expect(actions.length, equals(3));
    });

    test('Provider on pending paid order also sees Accept, Decline, Report', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: true,
        orderStatus: 0,
        paymentStatus: 'complete',
      );

      final keys = actions.map((a) => a.actionKey).toList();
      expect(keys, equals(['accept', 'decline', 'report']));
    });

    test('Provider completed/active order does NOT show Accept or Decline', () {
      for (final status in [1, 2, 3]) {
        final actions = OrdersHelper().getOrderActions(
          isSeller: true,
          orderStatus: status,
          paymentStatus: 'complete',
        );
        final keys = actions.map((a) => a.actionKey).toList();
        // Test 4: Provider completed/active order does not show Accept
        expect(keys.contains('accept'), isFalse, reason: 'Status $status must not show Accept');
        expect(keys.contains('decline'), isFalse, reason: 'Status $status must not show Decline');
        expect(keys.contains('report'), isTrue, reason: 'Status $status can Report to admin');
      }
    });

    test('Provider cancelled order (status 4) shows no active actions', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: true,
        orderStatus: 4,
        paymentStatus: 'complete',
      );

      expect(actions.isEmpty, isTrue);
    });

    test('OrderDetailsService initial state and resetState exits loading', () {
      final service = OrderDetailsService();
      expect(service.isLoading, isTrue);
      service.setLoadingStatus(false);
      expect(service.isLoading, isFalse);

      service.resetState();
      expect(service.orderDetails, isNull);
      expect(service.orderStatus, isNull);
      expect(service.orderExtra, isEmpty);
      expect(service.isLoading, isTrue);
    });
  });
}
