// To parse this JSON data, do
//
//     final subcategoryModel = subcategoryModelFromJson(jsonString);

import 'dart:convert';

SubcategoryModel subcategoryModelFromJson(String str) =>
    SubcategoryModel.fromJson(json.decode(str));

String subcategoryModelToJson(SubcategoryModel data) =>
    json.encode(data.toJson());

class SubcategoryModel {
  SubcategoryModel({
    required this.subCategories,
  });

  List<SubCategory> subCategories;

  factory SubcategoryModel.fromJson(Map json) => SubcategoryModel(
        subCategories: List<SubCategory>.from(
            json["sub_categories"].map((x) => SubCategory.fromJson(x))),
      );

  Map<String, dynamic> toJson() => {
        "sub_categories":
            List<dynamic>.from(subCategories.map((x) => x.toJson())),
      };
}

class SubCategory {
  SubCategory({
    this.id,
    this.name,
    this.nameAr,
  });

  dynamic id;
  String? name;
  String? nameAr;

  factory SubCategory.fromJson(Map<String, dynamic> json) => SubCategory(
        id: json["id"],
        name: json["name"],
        nameAr: json["name_ar"],
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "name": name,
        "name_ar": nameAr,
      };

  String displayName(bool isArabic) {
    if (isArabic) {
      return (nameAr != null && nameAr!.trim().isNotEmpty) ? nameAr! : (name ?? '');
    }
    return name ?? '';
  }
}
