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
    this.backgroundImage,
    this.title,
    this.subTitle,
  });

  String? backgroundImage;
  String? title;
  String? subTitle;

  factory SliderDetail.fromJson(Map<String, dynamic> json) => SliderDetail(
        backgroundImage: json["background_image"]?.toString(),
        title: json["title"]?.toString(),
        subTitle: json["sub_title"]?.toString(),
      );

  Map<String, dynamic> toJson() => {
        "background_image": backgroundImage,
        "title": title,
        "sub_title": subTitle,
      };
}
