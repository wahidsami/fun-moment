// To parse this JSON data, do
//
//     final countryDropdownModel = countryDropdownModelFromJson(jsondynamic);

import 'dart:convert';

CountryDropdownModel countryDropdownModelFromJson(dynamic str) =>
    CountryDropdownModel.fromJson(json.decode(str));

dynamic countryDropdownModelToJson(CountryDropdownModel data) =>
    json.encode(data.toJson());

class CountryDropdownModel {
  CountryDropdownModel({
    required this.countries,
  });

  Countries countries;

  factory CountryDropdownModel.fromJson(Map<dynamic, dynamic> json) =>
      CountryDropdownModel(
        countries: Countries.fromJson(json['countries']),
      );

  Map<dynamic, dynamic> toJson() => {
        "countries": countries.toJson(),
      };
}

class Countries {
  Countries({
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

  dynamic currentPage;
  List<Datum> data;
  dynamic firstPageUrl;
  dynamic from;
  dynamic lastPage;
  dynamic lastPageUrl;
  List<Link> links;
  dynamic nextPageUrl;
  dynamic path;
  dynamic perPage;
  dynamic prevPageUrl;
  dynamic to;
  dynamic total;

  factory Countries.fromJson(Map<dynamic, dynamic> json) => Countries(
        currentPage: json["current_page"],
        data: List<Datum>.from(json["data"].map((x) => Datum.fromJson(x))),
        firstPageUrl: json["first_page_url"],
        from: json["from"],
        lastPage: json["last_page"],
        lastPageUrl: json["last_page_url"],
        links: [],
        // List<Link>.from(json["links"].map((x) => Link.fromJson(x))),
        nextPageUrl: json["next_page_url"],
        path: json["path"],
        perPage: json["per_page"],
        prevPageUrl: json["prev_page_url"],
        to: json["to"],
        total: json["total"],
      );

  Map<dynamic, dynamic> toJson() => {
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
    this.country,
  });

  dynamic id;
  dynamic country;

  factory Datum.fromJson(Map<dynamic, dynamic> json) => Datum(
        id: json["id"],
        country: json["country"],
      );

  Map<dynamic, dynamic> toJson() => {
        "id": id,
        "country": country,
      };
}

class Link {
  Link({
    this.url,
    this.label,
    this.active,
  });

  dynamic url;
  dynamic label;
  bool? active;

  factory Link.fromJson(Map<dynamic, dynamic> json) => Link(
        url: json["url"],
        label: json["label"],
        active: json["active"],
      );

  Map<dynamic, dynamic> toJson() => {
        "url": url,
        "label": label,
        "active": active,
      };
}
