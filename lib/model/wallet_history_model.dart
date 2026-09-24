// To parse this JSON data, do
//
//     final walletHistoryModel = walletHistoryModelFromJson(jsonString);

import 'dart:convert';

WalletHistoryModel walletHistoryModelFromJson(String str) =>
    WalletHistoryModel.fromJson(json.decode(str));

String walletHistoryModelToJson(WalletHistoryModel data) =>
    json.encode(data.toJson());

class WalletHistoryModel {
  WalletHistoryModel({
    required this.history,
  });

  List<History> history;

  factory WalletHistoryModel.fromJson(Map<String, dynamic> json) =>
      WalletHistoryModel(
        history: json["history"] is List
            ? List<History>.from((json["history"] as List)
                .whereType<Map<String, dynamic>>()
                .map((x) => History.fromJson(x)))
            : [],
      );

  Map<String, dynamic> toJson() => {
        "history": List<dynamic>.from(history.map((x) => x.toJson())),
      };
}

class History {
  History({
    this.id,
    this.buyerId,
    this.paymentGateway,
    this.paymentStatus,
    this.amount,
  });

  int? id;
  int? buyerId;
  String? paymentGateway;
  String? paymentStatus;
  double? amount;

  factory History.fromJson(Map<String, dynamic> json) => History(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        buyerId: json["buyer_id"] is int ? json["buyer_id"] : int.tryParse(json["buyer_id"]?.toString() ?? ''),
        paymentGateway: json["payment_gateway"]?.toString(),
        paymentStatus: json["payment_status"]?.toString(),
        amount: json["amount"] is num ? (json["amount"] as num).toDouble() : double.tryParse(json["amount"]?.toString() ?? ''),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "buyer_id": buyerId,
        "payment_gateway": paymentGateway,
        "payment_status": paymentStatus,
        "amount": amount,
      };
}
