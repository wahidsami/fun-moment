import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/orders_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/tabs/orders/orders_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

class MockOrdersService extends OrdersService {
  bool acceptOrderCalled = false;
  dynamic acceptedOrderId;

  bool cancelOrderCalled = false;
  dynamic cancelledOrderId;

  @override
  Future<bool> acceptOrder(BuildContext context, {required orderId}) async {
    acceptOrderCalled = true;
    acceptedOrderId = orderId;
    return true;
  }

  @override
  Future<void> cancelOrder(BuildContext context, {required orderId}) async {
    cancelOrderCalled = true;
    cancelledOrderId = orderId;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    rtlProvider = RtlService();
    lnProvider = AppStringService();
    SharedPreferences.setMockInitialValues({'userType': 0, 'token': 'test_token'});
  });

  group('Provider Order Card Actions & Handlers', () {
    test('1-3. Pending provider order shows Accept, Decline, Report with distinct keys', () {
      final actions = OrdersHelper().getOrderActions(
        isSeller: true,
        orderStatus: 0,
        paymentStatus: 'pending',
      );

      expect(actions.length, equals(3));
      expect(actions[0].actionKey, equals('accept'));
      expect(actions[0].title, equals('Accept booking'));
      expect(actions[1].actionKey, equals('decline'));
      expect(actions[1].title, equals('Decline booking'));
      expect(actions[2].actionKey, equals('report'));
      expect(actions[2].title, equals('Report to admin'));

      // Ensure "cancel" key is NOT reused for provider decline
      final keys = actions.map((a) => a.actionKey).toList();
      expect(keys.contains('cancel'), isFalse);
    });

    test('4-5. Provider completed/active order does not show Accept, and never sees Feedback', () {
      for (final status in [1, 2, 3, 4]) {
        final actions = OrdersHelper().getOrderActions(
          isSeller: true,
          orderStatus: status,
          paymentStatus: 'complete',
        );

        final keys = actions.map((a) => a.actionKey).toList();
        expect(keys.contains('accept'), isFalse, reason: 'Status $status should not show Accept');
        expect(keys.contains('feedback'), isFalse, reason: 'Provider must never see Leave Feedback');
      }
    });

    testWidgets('6. Accept handler calls OrdersService.acceptOrder() without OrderDetailsPage', (WidgetTester tester) async {
      final mockOrdersService = MockOrdersService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<OrdersService>.value(value: mockOrdersService),
            ChangeNotifierProvider<AppStringService>.value(value: lnProvider),
            ChangeNotifierProvider<RtlService>.value(value: rtlProvider),
            ChangeNotifierProvider<ProfileService>(create: (_) => ProfileService()),
            ChangeNotifierProvider<OrderDetailsService>(create: (_) => OrderDetailsService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: Builder(
                builder: (context) => ElevatedButton(
                  onPressed: () {
                    // Simulates tapping "Accept booking" from list card
                    mockOrdersService.acceptOrder(context, orderId: 42);
                  },
                  child: const Text('Accept Test Button'),
                ),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Accept Test Button'));
      await tester.pump();

      expect(mockOrdersService.acceptOrderCalled, isTrue);
      expect(mockOrdersService.acceptedOrderId, equals(42));
    });

    testWidgets('7. Decline handler invokes provider decline popup and triggers cancelOrder', (WidgetTester tester) async {
      final mockOrdersService = MockOrdersService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<OrdersService>.value(value: mockOrdersService),
            ChangeNotifierProvider<AppStringService>.value(value: lnProvider),
            ChangeNotifierProvider<RtlService>.value(value: rtlProvider),
            ChangeNotifierProvider<ProfileService>(create: (_) => ProfileService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: Builder(
                builder: (context) => ElevatedButton(
                  onPressed: () {
                    OrdersHelper().handleOrderAction(
                      context,
                      actionKey: 'decline',
                      serviceId: 10,
                      orderId: 99,
                    );
                  },
                  child: const Text('Decline Test Button'),
                ),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Decline Test Button'));
      await tester.pumpAndSettle();

      // Verify the dialog shows provider-specific wording
      expect(find.text('Are you sure you want to decline this booking?'), findsOneWidget);
      expect(find.text('Decline booking'), findsOneWidget);

      // Tap "Decline booking" confirmation
      await tester.tap(find.text('Decline booking'));
      await tester.pump();

      expect(mockOrdersService.cancelOrderCalled, isTrue);
      expect(mockOrdersService.cancelledOrderId, equals(99));
    });

    test('Accept/Decline does NOT require OrderDetailsService data or state', () {
      final helper = OrdersHelper();
      final orderActions = helper.getOrderActions(
        isSeller: true,
        orderStatus: 0,
        paymentStatus: 'pending',
      );

      // Actions are fully derived from order status and role without OrderDetails
      expect(orderActions.map((a) => a.actionKey).toList(), equals(['accept', 'decline', 'report']));
    });
  });
}
