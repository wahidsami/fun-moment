// To parse this JSON data, do
//
//     final myordersListModel = myordersListModelFromJson(jsonString);

import 'dart:convert';

MyordersListModel myordersListModelFromJson(String str) =>
    MyordersListModel.fromJson(json.decode(str));

String myordersListModelToJson(MyordersListModel data) =>
    json.encode(data.toJson());

class MyordersListModel {
  MyordersListModel({
    required this.myOrders,
    required this.userId,
    this.nextPage,
  });

  List<MyOrder> myOrders;
  int userId;
  String? nextPage;

  factory MyordersListModel.fromJson(Map<String, dynamic> json) =>
      MyordersListModel(
          myOrders: json["my_orders"] is Map && json["my_orders"]['data'] is List
              ? List<MyOrder>.from(
                  (json["my_orders"]['data'] as List).map((x) => x is Map<String, dynamic> ? MyOrder.fromJson(x) : MyOrder.fromJson(Map<String, dynamic>.from(x is Map ? x : {}))))
              : [],
          userId: json["user_id"] is int ? json["user_id"] : int.tryParse(json["user_id"]?.toString() ?? '') ?? 0,
          nextPage: json['my_orders'] is Map ? json['my_orders']['next_page_url']?.toString() : null);

  Map<String, dynamic> toJson() => {
        "my_orders": List<dynamic>.from(myOrders.map((x) => x.toJson())),
        "user_id": userId,
      };
}

class MyOrder {
  MyOrder({
    this.id,
    this.serviceId,
    this.sellerId,
    this.buyerId,
    this.name,
    this.email,
    this.phone,
    this.postCode,
    this.address,
    this.city,
    this.area,
    this.country,
    this.date,
    this.schedule,
    this.packageFee,
    this.extraService,
    this.subTotal,
    this.tax,
    this.total,
    this.couponCode,
    this.couponType,
    this.couponAmount,
    this.commissionType,
    this.commissionCharge,
    this.commissionAmount,
    this.paymentGateway,
    this.paymentStatus,
    this.status,
    this.isOrderOnline,
    this.orderCompleteRequest,
    this.cancelOrderMoneyReturn,
    this.transactionId,
    this.orderNote,
    this.manualPaymentImage,
  });

  int? id;
  int? serviceId;
  int? sellerId;
  int? buyerId;
  String? name;
  String? email;
  String? phone;
  String? postCode;
  String? address;
  int? city;
  int? area;
  int? country;
  DateTime? date;
  String? schedule;
  var packageFee;
  var extraService;
  var subTotal;
  double? tax;
  double? total;
  dynamic couponCode;
  String? couponType;
  var couponAmount;
  String? commissionType;
  int? commissionCharge;
  var commissionAmount;
  String? paymentGateway;
  String? paymentStatus;
  int? status;
  int? isOrderOnline;
  int? orderCompleteRequest;
  int? cancelOrderMoneyReturn;
  dynamic transactionId;
  dynamic orderNote;

  dynamic manualPaymentImage;

  factory MyOrder.fromJson(Map<String, dynamic> json) => MyOrder(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        serviceId: json["service_id"] is int ? json["service_id"] : int.tryParse(json["service_id"]?.toString() ?? ''),
        sellerId: json["seller_id"] is int ? json["seller_id"] : int.tryParse(json["seller_id"]?.toString() ?? ''),
        buyerId: json["buyer_id"] is int ? json["buyer_id"] : int.tryParse(json["buyer_id"]?.toString() ?? ''),
        name: json["name"]?.toString(),
        email: json["email"]?.toString(),
        phone: json["phone"]?.toString(),
        postCode: json["post_code"]?.toString(),
        address: json["address"]?.toString(),
        city: json["city"] is int ? json["city"] : int.tryParse(json["city"]?.toString() ?? ''),
        area: json["area"] is int ? json["area"] : int.tryParse(json["area"]?.toString() ?? ''),
        country: json["country"] is int ? json["country"] : int.tryParse(json["country"]?.toString() ?? ''),
        date: () {
          if (json["date"] == null) return null;
          try {
            return DateTime.parse(json['date'].toString());
          } catch (_) {
            return null;
          }
        }(),
        schedule: json["schedule"]?.toString(),
        packageFee: json["package_fee"],
        extraService: json["extra_service"],
        subTotal: json["sub_total"],
        tax: json["tax"] is num ? (json["tax"] as num).toDouble() : (double.tryParse(json["tax"]?.toString() ?? '') ?? 0.0),
        total: json["total"] is num ? (json["total"] as num).toDouble() : (double.tryParse(json["total"]?.toString() ?? '') ?? 0.0),
        couponCode: json["coupon_code"],
        couponType: json["coupon_type"],
        couponAmount: json["coupon_amount"],
        commissionType: json["commission_type"],
        commissionCharge: json["commission_charge"],
        commissionAmount: json["commission_amount"],
        paymentGateway: json["payment_gateway"],
        paymentStatus: json["payment_status"],
        status: json["status"],
        isOrderOnline: json["is_order_online"],
        orderCompleteRequest: json["order_complete_request"],
        cancelOrderMoneyReturn: json["cancel_order_money_return"],
        transactionId: json["transaction_id"],
        orderNote: json["order_note"],
        manualPaymentImage: json["manual_payment_image"],
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "service_id": serviceId,
        "seller_id": sellerId,
        "buyer_id": buyerId,
        "name": name,
        "email": email,
        "phone": phone,
        "post_code": postCode,
        "address": address,
        "city": city,
        "area": area,
        "country": country,
        "date": date,
        "schedule": schedule,
        "package_fee": packageFee,
        "extra_service": extraService,
        "sub_total": subTotal,
        "tax": tax,
        "total": total,
        "coupon_code": couponCode,
        "coupon_type": couponType,
        "coupon_amount": couponAmount,
        "commission_type": commissionType,
        "commission_charge": commissionCharge,
        "commission_amount": commissionAmount,
        "payment_gateway": paymentGateway,
        "payment_status": paymentStatus,
        "status": status,
        "is_order_online": isOrderOnline,
        "order_complete_request": orderCompleteRequest,
        "cancel_order_money_return": cancelOrderMoneyReturn,
        "transaction_id": transactionId,
        "order_note": orderNote,
        "manual_payment_image": manualPaymentImage,
      };
}
