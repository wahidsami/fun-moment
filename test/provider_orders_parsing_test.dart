import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/my_orders_list_model.dart';

void main() {
  test('MyordersListModel correctly parses backend seller orders JSON', () async {
    final file = File('backend/tests/seller_orders_sample.json');
    final jsonStr = await file.readAsString();
    final jsonMap = jsonDecode(jsonStr);

    final model = MyordersListModel.fromJson(jsonMap);

    expect(model.userId, 1);
    expect(model.myOrders.length, 2);

    final o1 = model.myOrders[0];
    expect(o1.id, 2);
    expect(o1.serviceId, 2);
    expect(o1.sellerId, 1);
    expect(o1.buyerId, 2);
    expect(o1.name, 'Buyer One');
    expect(o1.status, 0);
    expect(o1.paymentStatus, 'pending');
    expect(o1.total, 207.0);

    final o2 = model.myOrders[1];
    expect(o2.id, 1);
    expect(o2.status, 2);
    expect(o2.paymentStatus, 'complete');
    expect(o2.total, 287.5);
  });

  test('MyordersListModel handles empty orders list cleanly', () {
    final jsonMap = {
      "my_orders": {
        "current_page": 1,
        "data": [],
        "total": 0,
      },
      "user_id": 5
    };

    final model = MyordersListModel.fromJson(jsonMap);
    expect(model.userId, 5);
    expect(model.myOrders, isEmpty);
  });

  test('MyOrder handles string commissions, statuses, and null totals without throwing', () {
    final orderJson = {
      "id": "100",
      "seller_id": "5",
      "buyer_id": "4",
      "service_id": "10",
      "name": "Test Client",
      "commission_charge": "25",
      "status": "1",
      "is_order_online": "0",
      "order_complete_request": "0",
      "cancel_order_money_return": "0",
      "total": "150.75",
      "tax": "22.5",
      "date": "2026-10-01T12:00:00Z"
    };

    final order = MyOrder.fromJson(orderJson);
    expect(order.id, 100);
    expect(order.sellerId, 5);
    expect(order.commissionCharge, 25);
    expect(order.status, 1);
    expect(order.total, 150.75);
    expect(order.tax, 22.5);
    expect(order.date, isNotNull);
  });
}

