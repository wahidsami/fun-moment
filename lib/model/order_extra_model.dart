// To parse this JSON data, do
//
//     final orderExtraModel = orderExtraModelFromJson(jsonString);

import 'dart:convert';

OrderExtraModel orderExtraModelFromJson(String str) =>
    OrderExtraModel.fromJson(json.decode(str));

String orderExtraModelToJson(OrderExtraModel data) =>
    json.encode(data.toJson());

class OrderExtraModel {
  OrderExtraModel({
    required this.extraServiceList,
  });

  List<ExtraServiceList> extraServiceList;

  factory OrderExtraModel.fromJson(Map<String, dynamic> json) =>
      OrderExtraModel(
        extraServiceList: json["extra_service_list"] is List
            ? List<ExtraServiceList>.from((json["extra_service_list"] as List)
                .map((x) => x is Map<String, dynamic> ? ExtraServiceList.fromJson(x) : ExtraServiceList.fromJson(Map<String, dynamic>.from(x is Map ? x : {}))))
            : [],
      );

  Map<String, dynamic> toJson() => {
        "extra_service_list":
            List<dynamic>.from(extraServiceList.map((x) => x.toJson())),
      };
}

class ExtraServiceList {
  ExtraServiceList(
      {this.id,
      this.orderId,
      this.title,
      this.quantity,
      this.price,
      this.tax,
      this.subTotal,
      this.total,
      this.status});

  int? id;
  int? orderId;
  String? title;
  int? quantity;
  int? price;
  double? tax;
  int? subTotal;
  double? total;
  var status;

  factory ExtraServiceList.fromJson(Map<String, dynamic> json) =>
      ExtraServiceList(
          id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
          orderId: json["order_id"] is int ? json["order_id"] : int.tryParse(json["order_id"]?.toString() ?? ''),
          title: json["title"]?.toString(),
          quantity: json["quantity"] is int ? json["quantity"] : int.tryParse(json["quantity"]?.toString() ?? ''),
          price: json["price"] is int ? json["price"] : int.tryParse(json["price"]?.toString() ?? ''),
          tax: json["tax"] is num ? (json["tax"] as num).toDouble() : (double.tryParse(json["tax"]?.toString() ?? '') ?? 0.0),
          subTotal: json["sub_total"] is int ? json["sub_total"] : int.tryParse(json["sub_total"]?.toString() ?? ''),
          total: json["total"] is num ? (json["total"] as num).toDouble() : (double.tryParse(json["total"]?.toString() ?? '') ?? 0.0),
          status: json["status"]);

  Map<String, dynamic> toJson() => {
        "id": id,
        "order_id": orderId,
        "title": title,
        "quantity": quantity,
        "price": price,
        "tax": tax,
        "sub_total": subTotal,
        "total": total,
        "status": status
      };
}
