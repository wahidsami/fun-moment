// To parse this JSON data, do
//
//     final myordersListModel = profileModel(jsonString);

import 'dart:convert';

ProfileModel profileModel(String str) =>
    ProfileModel.fromJson(json.decode(str));

String myordersListModelToJson(ProfileModel data) => json.encode(data.toJson());

class ProfileModel {
  ProfileModel({
    required this.userDetails,
    this.pendingOrder,
    this.activeOrder,
    this.completeOrder,
    this.totalOrder,
  });

  UserDetails userDetails;
  int? pendingOrder;
  int? activeOrder;
  int? completeOrder;
  int? totalOrder;

  factory ProfileModel.fromJson(Map<String, dynamic> json) => ProfileModel(
        userDetails: json["user_details"] is Map<String, dynamic>
            ? UserDetails.fromJson(json["user_details"])
            : (json["user_details"] is Map
                ? UserDetails.fromJson(Map<String, dynamic>.from(json["user_details"]))
                : UserDetails(country: null, city: null, area: null)),
        pendingOrder: json["pending_order"] == null ? null : int.tryParse(json["pending_order"].toString()),
        activeOrder: json["active_order"] == null ? null : int.tryParse(json["active_order"].toString()),
        completeOrder: json["complete_order"] == null ? null : int.tryParse(json["complete_order"].toString()),
        totalOrder: json["total_order"] == null ? null : int.tryParse(json["total_order"].toString()),
      );

  Map<String, dynamic> toJson() => {
        "user_details": userDetails.toJson(),
        "pending_order": pendingOrder,
        "active_order": activeOrder,
        "complete_order": completeOrder,
        "total_order": totalOrder,
      };
}

class UserDetails {
  UserDetails({
    this.id,
    this.name,
    this.email,
    this.phone,
    this.address,
    this.about,
    this.googleId,
    this.facebookId,
    this.countryId,
    this.serviceCity,
    this.serviceArea,
    this.postCode,
    this.image,
    this.countryCode,
    this.userType,
    required this.country,
    required this.city,
    required this.area,
  });

  int? id;
  String? name;
  String? email;
  String? phone;
  String? address;
  dynamic about;
  int? countryId;
  dynamic googleId;
  dynamic facebookId;
  String? serviceCity;
  String? serviceArea;
  String? postCode;
  String? image;
  String? countryCode;
  int? userType;
  Country? country;
  City? city;
  Area? area;

  factory UserDetails.fromJson(Map<String, dynamic> json) => UserDetails(
        id: json["id"] == null ? null : int.tryParse(json["id"].toString()),
        name: json["name"]?.toString(),
        email: json["email"]?.toString(),
        phone: json["phone"]?.toString(),
        address: json["address"]?.toString(),
        about: json["about"],
        countryId: json["country_id"] == null ? null : int.tryParse(json["country_id"].toString()),
        serviceCity: json["service_city"]?.toString(),
        serviceArea: json["service_area"]?.toString(),
        googleId: json["google_id"],
        facebookId: json["facebook_id"],
        postCode: json["post_code"]?.toString(),
        image: json["image"]?.toString(),
        countryCode: json["country_code"]?.toString(),
        userType: json["user_type"] == null ? 1 : int.tryParse(json["user_type"].toString()),
        country: json["country"] is Map<String, dynamic> ? Country.fromJson(json["country"]) : null,
        city: json["city"] is Map<String, dynamic> ? City.fromJson(json["city"]) : null,
        area: json["area"] is Map<String, dynamic> ? Area.fromJson(json["area"]) : null,
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "name": name,
        "email": email,
        "phone": phone,
        "address": address,
        "about": about,
        "country_id": countryId,
        "service_city": serviceCity,
        "service_area": serviceArea,
        "post_code": postCode,
        "image": image,
        "country_code": countryCode,
        "country": country?.toJson(),
        "city": city?.toJson(),
        "area": area?.toJson(),
      };
}

class Area {
  Area({
    this.id,
    this.serviceArea,
    this.serviceCityId,
    this.countryId,
    this.status,
  });

  int? id;
  String? serviceArea;
  int? serviceCityId;
  int? countryId;
  int? status;

  factory Area.fromJson(Map<String?, dynamic>? json) => Area(
        id: json?["id"] == null ? null : int.tryParse(json!["id"].toString()),
        serviceArea: json?["service_area"]?.toString(),
        serviceCityId: json?["service_city_id"] == null ? null : int.tryParse(json!["service_city_id"].toString()),
        countryId: json?["country_id"] == null ? null : int.tryParse(json!["country_id"].toString()),
        status: json?["status"] == null ? null : int.tryParse(json!["status"].toString()),
      );

  Map<String?, dynamic>? toJson() => {
        "id": id,
        "service_area": serviceArea,
        "service_city_id": serviceCityId,
        "country_id": countryId,
        "status": status,
      };
}

class City {
  City({
    this.id,
    this.serviceCity,
    this.countryId,
    this.status,
  });

  int? id;
  String? serviceCity;
  int? countryId;
  int? status;

  factory City.fromJson(Map<String?, dynamic>? json) => City(
        id: json?["id"] == null ? null : int.tryParse(json!["id"].toString()),
        serviceCity: json?["service_city"]?.toString(),
        countryId: json?["country_id"] == null ? null : int.tryParse(json!["country_id"].toString()),
        status: json?["status"] == null ? null : int.tryParse(json!["status"].toString()),
      );

  Map<String?, dynamic>? toJson() => {
        "id": id,
        "service_city": serviceCity,
        "country_id": countryId,
        "status": status,
      };
}

class Country {
  Country({
    this.id,
    this.country,
    this.status,
  });

  int? id;
  String? country;
  int? status;

  factory Country.fromJson(Map<String?, dynamic>? json) => Country(
        id: json?["id"] == null ? null : int.tryParse(json!["id"].toString()),
        country: json?["country"]?.toString(),
        status: json?["status"] == null ? null : int.tryParse(json!["status"].toString()),
      );

  Map<String?, dynamic>? toJson() => {
        "id": id,
        "country": country,
        "status": status,
      };
}
