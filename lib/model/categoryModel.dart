// To parse this JSON data, do
//
//     final categoryModel = categoryModelFromJson(jsonString);

import 'dart:convert';

CategoryModel categoryModelFromJson(String str) =>
    CategoryModel.fromJson(json.decode(str));

String categoryModelToJson(CategoryModel data) => json.encode(data.toJson());

class CategoryModel {
  CategoryModel({
    required this.category,
  });

  List<Category> category;

  factory CategoryModel.fromJson(Map json) => CategoryModel(
        category: List<Category>.from(
            json["category"].map((x) => Category.fromJson(x))),
      );

  Map<String, dynamic> toJson() => {
        "category": List<dynamic>.from(category.map((x) => x.toJson())),
      };
}

const Map<String, String> defaultCategoryTranslations = {
  'Music & DJ': 'الموسيقى والـ DJ',
  'Food & Hospitality': 'الأطعمة والضيافة',
  'Sound & Lighting': 'الصوت والإضاءة',
  'Event Setup & Equipment': 'تجهيز الفعاليات والمعدات',
  'Decor & Event Styling': 'الديكور وتنسيق المناسبات',
  'Photography & Video': 'التصوير والفيديو',
  'Entertainment Activities': 'الترفيه والأنشطة',
};

class Category {
  Category({
    this.id,
    this.name,
    this.nameAr,
    this.icon,
    this.mobileIcon,
  });

  dynamic id;
  String? name;
  String? nameAr;
  String? icon;
  String? mobileIcon;

  factory Category.fromJson(Map<String, dynamic> json) => Category(
        id: json["id"],
        name: json["name"],
        nameAr: json["name_ar"],
        icon: json["icon"],
        mobileIcon: json["mobile_icon"],
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "name": name,
        "name_ar": nameAr,
        "icon": icon,
        "mobile_icon": mobileIcon,
      };

  String displayName(bool isArabic) {
    if (isArabic) {
      if (nameAr != null && nameAr!.trim().isNotEmpty) {
        return nameAr!.trim();
      }
      if (name != null && defaultCategoryTranslations.containsKey(name!.trim())) {
        return defaultCategoryTranslations[name!.trim()]!;
      }
    }
    return name ?? '';
  }
}
