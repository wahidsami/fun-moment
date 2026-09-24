// To parse this JSON data, do
//
//     final searchBarWithDropdownServiceModel = searchBarWithDropdownServiceModelFromJson(jsonString);

import 'dart:convert';

SearchBarWithDropdownServiceModel searchBarWithDropdownServiceModelFromJson(
        String str) =>
    SearchBarWithDropdownServiceModel.fromJson(json.decode(str));

String searchBarWithDropdownServiceModelToJson(
        SearchBarWithDropdownServiceModel data) =>
    json.encode(data.toJson());

class SearchBarWithDropdownServiceModel {
  SearchBarWithDropdownServiceModel({
    required this.services,
    required this.serviceImage,
  });

  List<Service> services;
  List<ServiceImage?> serviceImage;

  factory SearchBarWithDropdownServiceModel.fromJson(
          Map<String, dynamic> json) =>
      SearchBarWithDropdownServiceModel(
        services: json["services"] is List
            ? List<Service>.from((json["services"] as List).map((x) =>
                x is Map<String, dynamic>
                    ? Service.fromJson(x)
                    : Service.fromJson(Map<String, dynamic>.from(x is Map ? x : {}))))
            : [],
        serviceImage: json["service_image"] is List
            ? List<ServiceImage?>.from((json["service_image"] as List).map((x) {
                if (x is Map<String, dynamic>) {
                  return ServiceImage.fromJson(x);
                } else if (x is Map) {
                  return ServiceImage.fromJson(Map<String, dynamic>.from(x));
                } else {
                  return null;
                }
              }))
            : [],
      );

  Map<String, dynamic> toJson() => {
        "services": List<dynamic>.from(services.map((x) => x.toJson())),
        "service_image":
            List<dynamic>.from(serviceImage.map((x) => x?.toJson())),
      };
}

class ServiceImage {
  ServiceImage({
    this.imageId,
    this.path,
    this.imgUrl,
    this.imgAlt,
  });

  int? imageId;
  String? path;
  String? imgUrl;
  dynamic imgAlt;

  factory ServiceImage.fromJson(Map<String, dynamic>? json) => ServiceImage(
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

class Service {
  Service({
    this.id,
    this.categoryId,
    this.subcategoryId,
    this.sellerId,
    this.serviceCityId,
    this.title,
    this.slug,
    this.description,
    this.image,
    this.imageGallery,
    this.video,
    this.status,
    this.isServiceOn,
    this.price,
    this.onlineServicePrice,
    this.deliveryDays,
    this.revision,
    this.isServiceOnline,
    this.tax,
    this.view,
    this.soldCount,
    this.featured,
    required this.sellerForMobile,
    required this.reviewsForMobile,
    required this.serviceCity,
  });

  int? id;
  int? categoryId;
  int? subcategoryId;
  int? sellerId;
  int? serviceCityId;
  String? title;
  String? slug;
  String? description;
  String? image;
  String? imageGallery;
  String? video;
  int? status;
  int? isServiceOn;
  double? price;
  int? onlineServicePrice;
  int? deliveryDays;
  int? revision;
  int? isServiceOnline;
  double? tax;
  int? view;
  int? soldCount;
  int? featured;

  SellerForMobile sellerForMobile;
  List<ReviewsForMobile> reviewsForMobile;
  ServiceCityClass? serviceCity;

  factory Service.fromJson(Map<String, dynamic> json) => Service(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        categoryId: json["category_id"] is int ? json["category_id"] : int.tryParse(json["category_id"]?.toString() ?? ''),
        subcategoryId: json["subcategory_id"],
        sellerId: json["seller_id"] is int ? json["seller_id"] : int.tryParse(json["seller_id"]?.toString() ?? ''),
        serviceCityId: json["service_city_id"] is int ? json["service_city_id"] : int.tryParse(json["service_city_id"]?.toString() ?? ''),
        title: json["title"]?.toString(),
        slug: json["slug"]?.toString(),
        description: json["description"]?.toString(),
        image: json["image"],
        imageGallery: json["image_gallery"],
        video: json["video"],
        status: json["status"] is int ? json["status"] : int.tryParse(json["status"]?.toString() ?? ''),
        isServiceOn: json["is_service_on"] is int ? json["is_service_on"] : int.tryParse(json["is_service_on"]?.toString() ?? ''),
        price: json["price"] is num ? (json["price"] as num).toDouble() : double.tryParse(json["price"]?.toString() ?? ''),
        onlineServicePrice: json["online_service_price"] is int ? json["online_service_price"] : int.tryParse(json["online_service_price"]?.toString() ?? ''),
        deliveryDays: json["delivery_days"] is int ? json["delivery_days"] : int.tryParse(json["delivery_days"]?.toString() ?? ''),
        revision: json["revision"] is int ? json["revision"] : int.tryParse(json["revision"]?.toString() ?? ''),
        isServiceOnline: json["is_service_online"] is int ? json["is_service_online"] : int.tryParse(json["is_service_online"]?.toString() ?? ''),
        tax: json["tax"] is num ? (json["tax"] as num).toDouble() : double.tryParse(json["tax"]?.toString() ?? ''),
        view: json["view"] is int ? json["view"] : int.tryParse(json["view"]?.toString() ?? ''),
        soldCount: json["sold_count"] is int ? json["sold_count"] : int.tryParse(json["sold_count"]?.toString() ?? ''),
        featured: json["featured"] is int ? json["featured"] : int.tryParse(json["featured"]?.toString() ?? ''),
        sellerForMobile: json["seller_for_mobile"] is Map<String, dynamic>
            ? SellerForMobile.fromJson(json["seller_for_mobile"])
            : SellerForMobile(),
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
        "category_id": categoryId,
        "subcategory_id": subcategoryId,
        "seller_id": sellerId,
        "service_city_id": serviceCityId,
        "title": title,
        "slug": slug,
        "description": description,
        "image": image,
        "image_gallery": imageGallery,
        "video": video,
        "status": status,
        "is_service_on": isServiceOn,
        "price": price,
        "online_service_price": onlineServicePrice,
        "delivery_days": deliveryDays,
        "revision": revision,
        "is_service_online": isServiceOnline,
        "tax": tax,
        "view": view,
        "sold_count": soldCount,
        "featured": featured,
        "seller_for_mobile": sellerForMobile.toJson(),
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

class ServiceCityClass {
  ServiceCityClass({
    this.id,
    this.serviceCity,
    this.countryId,
    this.status,
  });

  int? id;
  String? serviceCity;
  int? countryId;
  int? status;

  factory ServiceCityClass.fromJson(Map<String, dynamic> json) =>
      ServiceCityClass(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        serviceCity: json["service_city"]?.toString(),
        countryId: json["country_id"] is int ? json["country_id"] : int.tryParse(json["country_id"]?.toString() ?? ''),
        status: json["status"] is int ? json["status"] : int.tryParse(json["status"]?.toString() ?? ''),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "service_city": serviceCity,
        "country_id": countryId,
        "status": status,
      };
}
