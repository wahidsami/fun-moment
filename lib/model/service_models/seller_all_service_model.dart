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
        services: Services.fromJson(json["services"]),
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
        currentPage: json["current_page"],
        data: List<Datum>.from(json["data"].map((x) => Datum.fromJson(x))),
        firstPageUrl: json["first_page_url"],
        from: json["from"],
        lastPage: json["last_page"],
        lastPageUrl: json["last_page_url"],
        links: List<Link>.from(json["links"].map((x) => Link.fromJson(x))),
        nextPageUrl: json["next_page_url"],
        path: json["path"],
        perPage: json["per_page"],
        prevPageUrl: json["prev_page_url"],
        to: json["to"],
        total: json["total"],
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
        id: json["id"],
        sellerId: json["seller_id"],
        sellerName: json['seller_name'],
        title: json["title"],
        price: json["price"].toDouble(),
        image: json["image"],
        isServiceOnline: json["is_service_online"],
        serviceCityId: json["service_city_id"],
        imageUrl: json["image_url"],
        sellerImageUrl: json["seller_image_url"],
        sellerForMobile: json["seller_for_mobile"] == null
            ? null
            : SellerForMobile.fromJson(json["seller_for_mobile"]),
        reviewsForMobile: json["reviews_for_mobile"] is List
            ? []
            : List<ReviewsForMobile>.from(json["reviews_for_mobile"]
                .map((x) => ReviewsForMobile.fromJson(x))),
        serviceCity: json["service_city"] == null
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
  int? rating;
  String? message;
  int? buyerId;

  factory ReviewsForMobile.fromJson(Map<String, dynamic> json) =>
      ReviewsForMobile(
        id: json["id"],
        serviceId: json["service_id"],
        rating: json["rating"],
        message: json["message"],
        buyerId: json["buyer_id"],
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
        id: json["id"],
        name: json['name'],
        image: json["image"],
        countryId: json["country_id"],
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

  factory ServiceCityClass.fromJson(Map<String, dynamic> json) =>
      ServiceCityClass(
        id: json["id"],
        serviceCity: json['service_city'],
        countryId: json["country_id"],
        status: json["status"],
        createdAt: DateTime.parse(json["created_at"]),
        updatedAt: DateTime.parse(json["updated_at"]),
      );

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
