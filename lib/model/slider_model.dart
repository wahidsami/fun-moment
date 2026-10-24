// To parse this JSON data, do
//
//     final sliderModel = sliderModelFromJson(jsonString);

import 'dart:convert';

SliderModel sliderModelFromJson(String str) =>
    SliderModel.fromJson(json.decode(str));

String sliderModelToJson(SliderModel data) => json.encode(data.toJson());

class SliderModel {
  SliderModel({
    required this.sliderDetails,
    required this.imageUrl,
  });

  List<SliderDetail> sliderDetails;
  List<ImageUrl> imageUrl;

  factory SliderModel.fromJson(Map<String, dynamic> json) => SliderModel(
        sliderDetails: json["slider-details"] is List
            ? (json["slider-details"] as List).map((x) {
                if (x is Map<String, dynamic>) {
                  return SliderDetail.fromJson(x);
                } else if (x is Map) {
                  return SliderDetail.fromJson(Map<String, dynamic>.from(x));
                }
                return SliderDetail();
              }).toList()
            : [],
        imageUrl: json["image_url"] is List
            ? (json["image_url"] as List).map((x) {
                if (x is Map<String, dynamic>) {
                  return ImageUrl.fromJson(x);
                } else if (x is Map) {
                  return ImageUrl.fromJson(Map<String, dynamic>.from(x));
                }
                return ImageUrl();
              }).toList()
            : [],
      );

  Map<String, dynamic> toJson() => {
        "slider-details":
            List<dynamic>.from(sliderDetails.map((x) => x.toJson())),
        "image_url": List<dynamic>.from(imageUrl.map((x) => x.toJson())),
      };
}

class ImageUrl {
  ImageUrl({
    this.imageId,
    this.path,
    this.imgUrl,
    this.imgAlt,
  });

  int? imageId;
  String? path;
  String? imgUrl;
  dynamic imgAlt;

  factory ImageUrl.fromJson(Map<String, dynamic> json) => ImageUrl(
        imageId: json["image_id"] == null ? null : int.tryParse(json["image_id"].toString()),
        path: json["path"]?.toString(),
        imgUrl: json["img_url"]?.toString(),
        imgAlt: json["img_alt"],
      );

  Map<String, dynamic> toJson() => {
        "image_id": imageId,
        "path": path,
        "img_url": imgUrl,
        "img_alt": imgAlt,
      };
}

class SliderDetail {
  SliderDetail({
    this.id,
    this.backgroundImage,
    this.title,
    this.subTitle,
    this.titleEn,
    this.titleAr,
    this.subTitleEn,
    this.subTitleAr,
    this.btnText,
    this.btnTextEn,
    this.btnTextAr,
    this.serviceId,
    this.actionType = 'none',
    this.actionTarget,
    this.order = 0,
    this.imgUrl,
  });

  int? id;
  String? backgroundImage;
  String? title;
  String? subTitle;
  String? titleEn;
  String? titleAr;
  String? subTitleEn;
  String? subTitleAr;
  String? btnText;
  String? btnTextEn;
  String? btnTextAr;
  int? serviceId;
  String? actionType;
  String? actionTarget;
  int? order;
  String? imgUrl;

  factory SliderDetail.fromJson(Map<String, dynamic> json) => SliderDetail(
        id: json["id"] == null ? null : int.tryParse(json["id"].toString()),
        backgroundImage: json["background_image"]?.toString(),
        title: json["title"]?.toString(),
        subTitle: json["sub_title"]?.toString(),
        titleEn: json["title_en"]?.toString(),
        titleAr: json["title_ar"]?.toString(),
        subTitleEn: json["sub_title_en"]?.toString(),
        subTitleAr: json["sub_title_ar"]?.toString(),
        btnText: json["btn_text"]?.toString(),
        btnTextEn: json["btn_text_en"]?.toString(),
        btnTextAr: json["btn_text_ar"]?.toString(),
        serviceId: json["service_id"] == null
            ? null
            : int.tryParse(json["service_id"].toString()),
        actionType: json["action_type"]?.toString() ?? 'none',
        actionTarget: json["action_target"]?.toString(),
        order: json["order"] == null
            ? 0
            : (int.tryParse(json["order"].toString()) ?? 0),
        imgUrl: json["img_url"]?.toString(),
      );

  Map<String, dynamic> toJson() => {
        "id": id,
        "background_image": backgroundImage,
        "title": title,
        "sub_title": subTitle,
        "title_en": titleEn,
        "title_ar": titleAr,
        "sub_title_en": subTitleEn,
        "sub_title_ar": subTitleAr,
        "btn_text": btnText,
        "btn_text_en": btnTextEn,
        "btn_text_ar": btnTextAr,
        "service_id": serviceId,
        "action_type": actionType,
        "action_target": actionTarget,
        "order": order,
        "img_url": imgUrl,
      };
}
