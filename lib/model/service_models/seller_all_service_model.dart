// To parse this JSON data, do
//
//     final sellerAllServiceModel = sellerAllServiceModelFromJson(jsonString);

import 'dart:convert';

SellerAllServiceModel sellerAllServiceModelFromJson(String str) =>
    SellerAllServiceModel.fromJson(json.decode(str));

String sellerAllServiceModelToJson(SellerAllServiceModel data) =>
    json.encode(data.toJson());

class SellerAllServiceModel {
  SellerAllServiceModel({
    required this.services,
  });

  Services services;

  factory SellerAllServiceModel.fromJson(Map<String, dynamic> json) =>
      SellerAllServiceModel(
        services: json["services"] is Map<String, dynamic>
            ? Services.fromJson(json["services"])
            : (json["services"] is Map
                ? Services.fromJson(Map<String, dynamic>.from(json["services"]))
                : Services(data: [], links: [])),
      );

  Map<String, dynamic> toJson() => {
        "services": services.toJson(),
      };
}

class Services {
  Services({
    this.currentPage,
    required this.data,
    this.firstPageUrl,
    this.from,
    this.lastPage,
    this.lastPageUrl,
    required this.links,
    this.nextPageUrl,
    this.path,
    this.perPage,
    this.prevPageUrl,
    this.to,
    this.total,
  });

  int? currentPage;
  List<Datum> data;
  String? firstPageUrl;
  int? from;
  int? lastPage;
  String? lastPageUrl;
  List<Link> links;
  String? nextPageUrl;
  String? path;
  int? perPage;
  dynamic prevPageUrl;
  int? to;
  int? total;

  factory Services.fromJson(Map<String, dynamic> json) => Services(
        currentPage: json["current_page"] is int ? json["current_page"] : int.tryParse(json["current_page"]?.toString() ?? ''),
        data: json["data"] is List
            ? List<Datum>.from((json["data"] as List).map((x) => x is Map<String, dynamic> ? Datum.fromJson(x) : Datum.fromJson(Map<String, dynamic>.from(x is Map ? x : {}))))
            : [],
        firstPageUrl: json["first_page_url"]?.toString(),
        from: json["from"] is int ? json["from"] : int.tryParse(json["from"]?.toString() ?? ''),
        lastPage: json["last_page"] is int ? json["last_page"] : int.tryParse(json["last_page"]?.toString() ?? ''),
        lastPageUrl: json["last_page_url"]?.toString(),
        links: json["links"] is List
            ? List<Link>.from((json["links"] as List).map((x) => x is Map<String, dynamic> ? Link.fromJson(x) : Link.fromJson(Map<String, dynamic>.from(x is Map ? x : {}))))
            : [],
        nextPageUrl: json["next_page_url"]?.toString(),
        path: json["path"]?.toString(),
        perPage: json["per_page"] is int ? json["per_page"] : int.tryParse(json["per_page"]?.toString() ?? ''),
        prevPageUrl: json["prev_page_url"],
        to: json["to"] is int ? json["to"] : int.tryParse(json["to"]?.toString() ?? ''),
        total: json["total"] is int ? json["total"] : int.tryParse(json["total"]?.toString() ?? ''),
      );

  Map<String, dynamic> toJson() => {
        "current_page": currentPage,
        "data": List<dynamic>.from(data.map((x) => x.toJson())),
        "first_page_url": firstPageUrl,
        "from": from,
        "last_page": lastPage,
        "last_page_url": lastPageUrl,
        "links": List<dynamic>.from(links.map((x) => x.toJson())),
        "next_page_url": nextPageUrl,
        "path": path,
        "per_page": perPage,
        "prev_page_url": prevPageUrl,
        "to": to,
        "total": total,
      };
}

class Datum {
  Datum({
    this.id,
    this.sellerId,
    this.sellerName,
    this.title,
    this.price,
    this.image,
    this.isServiceOnline,
    this.serviceCityId,
    this.imageUrl,
    this.sellerImageUrl,
    required this.sellerForMobile,
    required this.reviewsForMobile,
    required this.serviceCity,
  });

  int? id;
  int? sellerId;
  String? sellerName;
  String? title;
  double? price;
  String? image;
  int? isServiceOnline;
  int? serviceCityId;
  String? imageUrl;
  String? sellerImageUrl;
  SellerForMobile? sellerForMobile;
  List<ReviewsForMobile> reviewsForMobile;
  ServiceCityClass? serviceCity;

  factory Datum.fromJson(Map<String, dynamic> json) => Datum(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        sellerId: json["seller_id"] is int ? json["seller_id"] : int.tryParse(json["seller_id"]?.toString() ?? ''),
        sellerName: json['seller_name'],
        title: json["title"],
        price: json["price"] is num ? (json["price"] as num).toDouble() : double.tryParse(json["price"]?.toString() ?? ''),
        image: json["image"],
        isServiceOnline: json["is_service_online"] is int ? json["is_service_online"] : int.tryParse(json["is_service_online"]?.toString() ?? ''),
        serviceCityId: json["service_city_id"] is int ? json["service_city_id"] : int.tryParse(json["service_city_id"]?.toString() ?? ''),
        imageUrl: json["image_url"],
        sellerImageUrl: json["seller_image_url"],
        sellerForMobile: json["seller_for_mobile"] == null || json["seller_for_mobile"] is! Map<String, dynamic>
            ? null
            : SellerForMobile.fromJson(json["seller_for_mobile"]),
        reviewsForMobile: json["reviews_for_mobile"] is List
            ? List<ReviewsForMobile>.from((json["reviews_for_mobile"] as List)
                .map((x) => ReviewsForMobile.fromJson(x)))
            : [],
        serviceCity: json["service_city"] == null || json["service_city"] is! Map<String, dynamic>
            ? null
            : ServiceCityClass.fromJson(json["service_city"]),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "seller_id": sellerId,
        "title": title,
        "price": price,
        "image": image,
        "is_service_online": isServiceOnline,
        "service_city_id": serviceCityId,
        "image_url": imageUrl,
        "seller_image_url": sellerImageUrl,
        "seller_for_mobile": sellerForMobile?.toJson(),
        "reviews_for_mobile":
            List<dynamic>.from(reviewsForMobile.map((x) => x.toJson())),
        "service_city": serviceCity?.toJson(),
      };
}

class ReviewsForMobile {
  ReviewsForMobile({
    this.id,
    this.serviceId,
    this.rating,
    this.message,
    this.buyerId,
  });

  int? id;
  int? serviceId;
  num? rating;
  String? message;
  int? buyerId;

  factory ReviewsForMobile.fromJson(Map<String, dynamic> json) =>
      ReviewsForMobile(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        serviceId: json["service_id"] is int ? json["service_id"] : int.tryParse(json["service_id"]?.toString() ?? ''),
        rating: json["rating"] is num ? json["rating"] : num.tryParse(json["rating"]?.toString() ?? ''),
        message: json["message"]?.toString(),
        buyerId: json["buyer_id"] is int ? json["buyer_id"] : int.tryParse(json["buyer_id"]?.toString() ?? ''),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "service_id": serviceId,
        "rating": rating,
        "message": message,
        "buyer_id": buyerId,
      };
}

class SellerForMobile {
  SellerForMobile({
    this.id,
    this.name,
    this.image,
    this.countryId,
  });

  int? id;
  String? name;
  String? image;
  int? countryId;

  factory SellerForMobile.fromJson(Map<String, dynamic> json) =>
      SellerForMobile(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        name: json['name']?.toString(),
        image: json["image"]?.toString(),
        countryId: json["country_id"] is int ? json["country_id"] : int.tryParse(json["country_id"]?.toString() ?? ''),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "name": name,
        "image": image,
        "country_id": countryId,
      };
}

class ServiceCityClass {
  ServiceCityClass({
    this.id,
    this.serviceCity,
    this.countryId,
    this.status,
    this.createdAt,
    this.updatedAt,
  });

  int? id;
  String? serviceCity;
  int? countryId;
  int? status;
  DateTime? createdAt;
  DateTime? updatedAt;

  factory ServiceCityClass.fromJson(Map<String, dynamic> json) {
    DateTime? cAt;
    DateTime? uAt;
    if (json["created_at"] != null) {
      try {
        cAt = DateTime.parse(json["created_at"].toString());
      } catch (_) {}
    }
    if (json["updated_at"] != null) {
      try {
        uAt = DateTime.parse(json["updated_at"].toString());
      } catch (_) {}
    }
    return ServiceCityClass(
      id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
      serviceCity: json['service_city']?.toString(),
      countryId: json["country_id"] is int ? json["country_id"] : int.tryParse(json["country_id"]?.toString() ?? ''),
      status: json["status"] is int ? json["status"] : int.tryParse(json["status"]?.toString() ?? ''),
      createdAt: cAt,
      updatedAt: uAt,
    );
  }

  Map<String, dynamic> toJson() => {
        "id": id,
        "service_city": serviceCity,
        "country_id": countryId,
        "status": status,
        "created_at": createdAt?.toIso8601String(),
        "updated_at": updatedAt?.toIso8601String(),
      };
}

class Link {
  Link({
    this.url,
    this.label,
    this.active,
  });

  String? url;
  String? label;
  bool? active;

  factory Link.fromJson(Map<String, dynamic> json) => Link(
        url: json["url"],
        label: json["label"],
        active: json["active"],
      );

  Map<String, dynamic> toJson() => {
        "url": url,
        "label": label,
        "active": active,
      };
}
