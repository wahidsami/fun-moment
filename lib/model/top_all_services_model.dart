// To parse this JSON data, do
//
//     final topAllServicesModel = topAllServicesModelFromJson(jsonString);

import 'dart:convert';

TopAllServicesModel topAllServicesModelFromJson(String str) =>
    TopAllServicesModel.fromJson(json.decode(str));

String topAllServicesModelToJson(TopAllServicesModel data) =>
    json.encode(data.toJson());

class TopAllServicesModel {
  TopAllServicesModel({
    required this.topServices,
    required this.serviceImage,
    required this.reviewerImage,
  });

  TopServices topServices;
  List<Image> serviceImage;

  List<dynamic> reviewerImage;

  factory TopAllServicesModel.fromJson(Map<String, dynamic> json) =>
      TopAllServicesModel(
        topServices: json["top_services"] is Map<String, dynamic>
            ? TopServices.fromJson(json["top_services"])
            : (json["top_services"] is Map
                ? TopServices.fromJson(Map<String, dynamic>.from(json["top_services"]))
                : TopServices(data: [])),
        serviceImage: json["service_image"] is List
            ? List<Image>.from((json["service_image"] as List).map((x) {
                if (x is Map<String, dynamic>) {
                  return Image.fromJson(x);
                } else if (x is Map) {
                  return Image.fromJson(Map<String, dynamic>.from(x));
                }
                return Image();
              }))
            : [],
        reviewerImage: json["reviewer_image"] is List ? List<dynamic>.from(json["reviewer_image"].map((x) => x)) : [],
      );

  Map<String, dynamic> toJson() => {
        "top_services": topServices.toJson(),
        "service_image":
            List<dynamic>.from(serviceImage.map((x) => x.toJson())),
        "reviewer_image": List<dynamic>.from(reviewerImage.map((x) => x)),
      };
}

class Image {
  Image({
    this.imageId,
    this.path,
    this.imgUrl,
    this.imgAlt,
  });

  int? imageId;
  String? path;
  String? imgUrl;
  dynamic imgAlt;

  factory Image.fromJson(Map<String, dynamic> json) => Image(
        imageId: json["image_id"],
        path: json["path"],
        imgUrl: json["img_url"],
        imgAlt: json["img_alt"],
      );

  Map<String, dynamic> toJson() => {
        "image_id": imageId,
        "path": path,
        "img_url": imgUrl,
        "img_alt": imgAlt,
      };
}

class TopServices {
  TopServices({
    this.currentPage,
    required this.data,
    this.firstPageUrl,
    this.from,
    this.lastPage,
    this.lastPageUrl,
    this.nextPageUrl,
    this.path,
    this.prevPageUrl,
    this.to,
    this.total,
  });

  int? currentPage;
  List<Datum> data;
  String? firstPageUrl;
  dynamic from;
  dynamic lastPage;
  String? lastPageUrl;
  String? nextPageUrl;
  String? path;
  dynamic prevPageUrl;
  dynamic to;
  int? total;

  factory TopServices.fromJson(Map<String, dynamic> json) => TopServices(
        currentPage: json["current_page"] is int ? json["current_page"] : int.tryParse(json["current_page"]?.toString() ?? ''),
        data: json["data"] is List
            ? List<Datum>.from((json["data"] as List).map((x) => x is Map<String, dynamic> ? Datum.fromJson(x) : Datum.fromJson(Map<String, dynamic>.from(x is Map ? x : {}))))
            : [],
        firstPageUrl: json["first_page_url"]?.toString(),
        from: json["from"],
        lastPage: json["last_page"],
        lastPageUrl: json["last_page_url"]?.toString(),
        nextPageUrl: json["next_page_url"]?.toString(),
        path: json["path"]?.toString(),
        prevPageUrl: json["prev_page_url"]?.toString(),
        to: json["to"],
        total: json["total"] is int ? json["total"] : int.tryParse(json["total"]?.toString() ?? ''),
      );

  Map<String, dynamic> toJson() => {
        "current_page": currentPage,
        "data": List<dynamic>.from(data.map((x) => x.toJson())),
        "first_page_url": firstPageUrl,
        "from": from,
        "last_page": lastPage,
        "last_page_url": lastPageUrl,
        "next_page_url": nextPageUrl,
        "path": path,
        "prev_page_url": prevPageUrl,
        "to": to,
        "total": total,
      };
}

class Datum {
  Datum({
    this.id,
    this.title,
    this.image,
    this.price,
    this.sellerId,
    required this.reviewsForMobile,
    required this.sellerForMobile,
  });

  int? id;
  String? title;
  String? image;
  var price;
  int? sellerId;
  List<ReviewsForMobile> reviewsForMobile;
  SellerForMobile sellerForMobile;

  factory Datum.fromJson(Map<String, dynamic> json) => Datum(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        title: json["title"],
        image: json["image"],
        price: json["price"] is num ? (json["price"] as num).toDouble() : double.tryParse(json["price"]?.toString() ?? ''),
        sellerId: json["seller_id"] is int ? json["seller_id"] : int.tryParse(json["seller_id"]?.toString() ?? ''),
        reviewsForMobile: json["reviews_for_mobile"] is List
            ? List<ReviewsForMobile>.from((json["reviews_for_mobile"] as List)
                .map((x) => ReviewsForMobile.fromJson(x)))
            : [],
        sellerForMobile: json["seller_for_mobile"] is Map<String, dynamic>
            ? SellerForMobile.fromJson(json["seller_for_mobile"])
            : SellerForMobile(),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "title": title,
        "image": image,
        "price": price,
        "seller_id": sellerId,
        "reviews_for_mobile":
            List<dynamic>.from(reviewsForMobile.map((x) => x.toJson())),
        "seller_for_mobile": sellerForMobile.toJson(),
      };
}

class ReviewsForMobile {
  ReviewsForMobile({
    this.id,
    this.serviceId,
    this.rating,
    this.message,
    this.buyerId,
    required this.buyerForMobile,
  });

  int? id;
  int? serviceId;
  num? rating;
  String? message;
  int? buyerId;
  BuyerForMobile? buyerForMobile;

  factory ReviewsForMobile.fromJson(Map<String, dynamic> json) =>
      ReviewsForMobile(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        serviceId: json["service_id"] is int ? json["service_id"] : int.tryParse(json["service_id"]?.toString() ?? ''),
        rating: json["rating"] is num ? json["rating"] : num.tryParse(json["rating"]?.toString() ?? ''),
        message: json["message"]?.toString(),
        buyerId: json["buyer_id"] is int ? json["buyer_id"] : int.tryParse(json["buyer_id"]?.toString() ?? ''),
        buyerForMobile: json["buyer_for_mobile"] == null || json["buyer_for_mobile"] is! Map<String, dynamic>
            ? null
            : BuyerForMobile.fromJson(json["buyer_for_mobile"]),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "service_id": serviceId,
        "rating": rating,
        "message": message,
        "buyer_id": buyerId,
        "buyer_for_mobile": buyerForMobile?.toJson(),
      };
}

class BuyerForMobile {
  BuyerForMobile({
    this.id,
    this.image,
  });

  int? id;
  String? image;

  factory BuyerForMobile.fromJson(Map<String, dynamic> json) => BuyerForMobile(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        image: json["image"]?.toString(),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "image": image,
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
        name: json["name"]?.toString(),
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
