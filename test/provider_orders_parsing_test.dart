import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/my_orders_list_model.dart';

void main() {
  test('MyordersListModel correctly parses backend seller orders JSON', () async {
    final file = File('backend/tests/seller_orders_sample.json');
    final String jsonStr;
    if (await file.exists()) {
      jsonStr = await file.readAsString();
    } else {
      jsonStr = '{"my_orders":{"current_page":1,"data":[{"id":2,"service_id":2,"seller_id":1,"buyer_id":2,"name":"Buyer One","email":"buyer1@funmoments.local","phone":"500000002","post_code":"12211","address":"Olaya Street, Riyadh","city":2,"area":2,"country":2,"date":"2026-05-19T00:00:00+00:00","schedule":"14:00","package_fee":"180","extra_service":"0","sub_total":"180","tax":"27","total":"207","coupon_code":null,"coupon_type":null,"coupon_amount":"0","commission_type":"percentage","commission_charge":"10","commission_amount":"18","payment_gateway":"manual_payment","payment_status":"pending","status":0,"transaction_id":"DEMO-ORDER-002","order_note":"Demo pending order","created_at":"2026-05-19T01:46:15.000000Z","updated_at":"2026-05-19T01:46:15.000000Z","manual_payment_image":null,"order_complete_request":0,"cancel_order_money_return":0,"is_order_online":0,"order_from_job":null,"job_post_id":null,"invoice":null,"payment_verified_at":null,"payment_details":null},{"id":1,"service_id":1,"seller_id":1,"buyer_id":2,"name":"Buyer One","email":"buyer1@funmoments.local","phone":"500000002","post_code":"12211","address":"Olaya Street, Riyadh","city":2,"area":2,"country":2,"date":"2026-05-19T00:00:00+00:00","schedule":"10:00","package_fee":"250","extra_service":"0","sub_total":"250","tax":"37.5","total":"287.5","coupon_code":null,"coupon_type":null,"coupon_amount":"0","commission_type":"percentage","commission_charge":"10","commission_amount":"25","payment_gateway":"manual_payment","payment_status":"complete","status":2,"transaction_id":"DEMO-ORDER-001","order_note":"Demo completed order","created_at":"2026-05-19T01:46:15.000000Z","updated_at":"2026-05-19T01:46:15.000000Z","manual_payment_image":null,"order_complete_request":0,"cancel_order_money_return":0,"is_order_online":0,"order_from_job":null,"job_post_id":null,"invoice":null,"payment_verified_at":null,"payment_details":null}],"first_page_url":"http://127.0.0.1:8000?page=1","from":1,"last_page":1,"last_page_url":"http://127.0.0.1:8000?page=1","links":[],"next_page_url":null,"path":"http://127.0.0.1:8000","per_page":10,"prev_page_url":null,"to":2,"total":2},"user_id":1}';
    }
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

