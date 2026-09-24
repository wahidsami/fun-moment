// To parse this JSON data, do
//
//     final topServiceModel = topServiceModelFromJson(jsonString);

import 'dart:convert';

TopServiceModel topServiceModelFromJson(String str) =>
    TopServiceModel.fromJson(json.decode(str));

String topServiceModelToJson(TopServiceModel data) =>
    json.encode(data.toJson());

class TopServiceModel {
  TopServiceModel({
    required this.topServices,
    required this.serviceImage,
    required this.reviewerImage,
  });

  List<TopService> topServices;
  List<Image?> serviceImage;
  List<dynamic> reviewerImage;

  factory TopServiceModel.fromJson(Map<String, dynamic> json) =>
      TopServiceModel(
        topServices: json["top_services"] is List
            ? List<TopService>.from((json["top_services"] as List)
                .map((x) => x is Map<String, dynamic> ? TopService.fromJson(x) : TopService.fromJson(Map<String, dynamic>.from(x is Map ? x : {}))))
            : [],
        serviceImage: json["service_image"] is List
            ? List<Image?>.from((json["service_image"] as List).map((x) {
                if (x is Map<String, dynamic>) {
                  return Image.fromJson(x);
                } else if (x is Map) {
                  return Image.fromJson(Map<String, dynamic>.from(x));
                } else {
                  return null;
                }
              }))
            : [],
        reviewerImage: json["reviewer_image"] is List ? List<dynamic>.from(json["reviewer_image"].map((x) => x)) : [],
      );

  Map<String, dynamic> toJson() => {
        "top_services": List<dynamic>.from(topServices.map((x) => x.toJson())),
        "service_image":
            List<dynamic>.from(serviceImage.map((x) => x?.toJson())),
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

  factory Image.fromJson(Map<String, dynamic>? json) => Image(
        imageId: json?["image_id"] == null ? null : int.tryParse(json!["image_id"].toString()),
        path: json?["path"]?.toString(),
        imgUrl: json?["img_url"]?.toString(),
        imgAlt: json?["img_alt"],
      );

  Map<String, dynamic> toJson() => {
        "image_id": imageId,
        "path": path,
        "img_url": imgUrl,
        "img_alt": imgAlt,
      };
}

class TopService {
  TopService({
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

  factory TopService.fromJson(Map<String, dynamic> json) => TopService(
        id: json["id"] == null ? null : int.tryParse(json["id"].toString()),
        title: json["title"]?.toString(),
        image: json["image"]?.toString(),
        price: json["price"],
        sellerId: json["seller_id"] == null ? null : int.tryParse(json["seller_id"].toString()),
        reviewsForMobile: json["reviews_for_mobile"] is List
            ? List<ReviewsForMobile>.from(json["reviews_for_mobile"]
                .map((x) => x is Map<String, dynamic> ? ReviewsForMobile.fromJson(x) : ReviewsForMobile()))
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
    this.buyerForMobile,
  });

  int? id;
  int? serviceId;
  num? rating;
  String? message;
  int? buyerId;
  BuyerForMobile? buyerForMobile;

  factory ReviewsForMobile.fromJson(Map<String, dynamic>? json) =>
      ReviewsForMobile(
        id: json?["id"] == null ? null : int.tryParse(json!["id"].toString()),
        serviceId: json?["service_id"] == null ? null : int.tryParse(json!["service_id"].toString()),
        rating: json?["rating"] == null ? null : num.tryParse(json!["rating"].toString()),
        message: json?["message"]?.toString(),
        buyerId: json?["buyer_id"] == null ? null : int.tryParse(json!["buyer_id"].toString()),
        buyerForMobile: json?["buyer_for_mobile"] is Map<String, dynamic>
            ? BuyerForMobile.fromJson(json!["buyer_for_mobile"])
            : null,
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

  factory BuyerForMobile.fromJson(Map<String, dynamic>? json) => BuyerForMobile(
        id: json?["id"] == null ? null : int.tryParse(json!["id"].toString()),
        image: json?["image"]?.toString(),
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
        id: json["id"] == null ? null : int.tryParse(json["id"].toString()),
        name: json["name"]?.toString(),
        image: json["image"]?.toString(),
        countryId: json["country_id"] == null ? null : int.tryParse(json["country_id"].toString()),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "name": name,
        "image": image,
        "country_id": countryId,
      };
}
