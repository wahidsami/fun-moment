// To parse this JSON data, do
//
//     final servicebyCategoryModel = servicebyCategoryModelFromJson(jsonString);

import 'dart:convert';

ServicebyCategoryModel servicebyCategoryModelFromJson(String str) =>
    ServicebyCategoryModel.fromJson(json.decode(str));

String servicebyCategoryModelToJson(ServicebyCategoryModel data) =>
    json.encode(data.toJson());

class ServicebyCategoryModel {
  ServicebyCategoryModel({
    required this.allServices,
    required this.serviceImage,
  });

  AllServices allServices;
  List<ServiceImage?> serviceImage;

  factory ServicebyCategoryModel.fromJson(Map<String, dynamic> json) =>
      ServicebyCategoryModel(
        allServices: json["all_services"] is Map<String, dynamic>
            ? AllServices.fromJson(json["all_services"])
            : (json["all_services"] is Map
                ? AllServices.fromJson(Map<String, dynamic>.from(json["all_services"]))
                : AllServices(data: [], links: [])),
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
        "all_services": allServices.toJson(),
        "service_image":
            List<dynamic>.from(serviceImage.map((x) => x?.toJson())),
      };
}

class AllServices {
  AllServices({
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
  dynamic nextPageUrl;
  String? path;
  int? perPage;
  dynamic prevPageUrl;
  int? to;
  int? total;

  factory AllServices.fromJson(Map<String, dynamic> json) => AllServices(
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
        nextPageUrl: json["next_page_url"],
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
    this.title,
    this.price,
    this.image,
    this.isServiceOnline,
    this.serviceCityId,
    required this.sellerForMobile,
    required this.reviewsForMobile,
    required this.serviceCity,
  });

  int? id;
  int? sellerId;
  String? title;
  var price;
  String? image;
  int? isServiceOnline;
  int? serviceCityId;
  SellerForMobile sellerForMobile;
  List<ReviewsForMobile> reviewsForMobile;
  ServiceCity serviceCity;

  factory Datum.fromJson(Map<String, dynamic> json) => Datum(
        id: json["id"] is int ? json["id"] : int.tryParse(json["id"]?.toString() ?? ''),
        sellerId: json["seller_id"] is int ? json["seller_id"] : int.tryParse(json["seller_id"]?.toString() ?? ''),
        title: json["title"],
        price: json["price"],
        image: json["image"],
        isServiceOnline: json["is_service_online"] is int ? json["is_service_online"] : int.tryParse(json["is_service_online"]?.toString() ?? ''),
        serviceCityId: json["service_city_id"] is int ? json["service_city_id"] : int.tryParse(json["service_city_id"]?.toString() ?? ''),
        sellerForMobile: json["seller_for_mobile"] is Map<String, dynamic>
            ? SellerForMobile.fromJson(json["seller_for_mobile"])
            : SellerForMobile(),
        reviewsForMobile: json["reviews_for_mobile"] is List
            ? List<ReviewsForMobile>.from((json["reviews_for_mobile"] as List)
                .map((x) => ReviewsForMobile.fromJson(x)))
            : [],
        serviceCity: json["service_city"] == null || json["service_city"] is! Map<String, dynamic>
            ? ServiceCity()
            : ServiceCity.fromJson(json["service_city"]),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "seller_id": sellerId,
        "title": title,
        "price": price,
        "image": image,
        "is_service_online": isServiceOnline,
        "service_city_id": serviceCityId,
        "seller_for_mobile": sellerForMobile.toJson(),
        "reviews_for_mobile":
            List<dynamic>.from(reviewsForMobile.map((x) => x.toJson())),
        "service_city": serviceCity.toJson(),
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
        id: json["id"],
        name: json["name"],
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

class ServiceCity {
  ServiceCity({
    this.id,
    this.serviceCity,
    this.countryId,
    this.status,
    this.countryy,
  });

  int? id;
  String? serviceCity;
  int? countryId;
  int? status;

  Countryy? countryy;

  factory ServiceCity.fromJson(Map<String, dynamic> json) => ServiceCity(
        id: json["id"],
        serviceCity: json["service_city"],
        countryId: json["country_id"],
        status: json["status"],
        countryy: json["countryy"] == null || json["countryy"] is! Map<String, dynamic>
            ? null
            : Countryy.fromJson(json["countryy"]),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "service_city": serviceCity,
        "country_id": countryId,
        "status": status,
        "countryy": countryy?.toJson(),
      };
}

class Countryy {
  Countryy({
    this.id,
    this.country,
    this.status,
  });

  int? id;
  String? country;
  int? status;

  factory Countryy.fromJson(Map<String, dynamic> json) => Countryy(
        id: json["id"],
        country: json["country"],
        status: json["status"],
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "country": country,
        "status": status,
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
