import 'package:auto_size_text/auto_size_text.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

import '../../service/book_steps_service.dart';

class CommonHelper {
  ConstantColors cc = ConstantColors();
  //common appbar
  appbarCommon(String title, BuildContext context, VoidCallback pressed,
      {actions}) {
    return AppBar(
      centerTitle: true,
      surfaceTintColor: FMColors.background,
      iconTheme: const IconThemeData(color: FMColors.textPrimary),
      systemOverlayStyle: SystemUiOverlayStyle.light,
      title: Consumer<AppStringService>(
        builder: (context, asProvider, child) => Text(
          asProvider.getString(title),
          style: const TextStyle(
              color: FMColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w700),
        ),
      ),
      backgroundColor: FMColors.background,
      elevation: 0,
      leading: InkWell(
        onTap: pressed,
        child: Icon(
          Directionality.of(context) == TextDirection.rtl
              ? Icons.arrow_forward_ios_rounded
              : Icons.arrow_back_ios_new_rounded,
        ),
      ),
      actions: actions,
    );
  }

  appbarForBookingPages(String title, BuildContext context,
      {bool isPersonalizatioPage = false, VoidCallback? extraFunction}) {
    return AppBar(
      centerTitle: true,
      iconTheme: IconThemeData(color: cc.greyPrimary),
      title: Consumer<AppStringService>(
        builder: (context, asProvider, child) => Text(
          asProvider.getString(title),
          style: TextStyle(
              color: cc.greyPrimary, fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),
      backgroundColor: Colors.transparent,
      elevation: 0,
      leading: InkWell(
        onTap: () {
          if (isPersonalizatioPage != true) {
            BookStepsService().decreaseStep(context);
          } else {
            //if its personalization page then decrease step to 1
            Provider.of<BookStepsService>(context, listen: false)
                .setStepsToDefault();
          }
          Navigator.pop(context);
          if (extraFunction != null) {
            extraFunction.call();
          }
        },
        child: const Icon(
          Icons.arrow_back_ios,
          size: 18,
        ),
      ),
    );
  }

  //common orange button =======>
  buttonOrange(String title, VoidCallback pressed,
      {isloading = false, bgColor, double paddingVerticle = 18}) {
    return InkWell(
      onTap: pressed,
      child: Container(
          width: double.infinity,
          alignment: Alignment.center,
          padding: EdgeInsets.symmetric(vertical: paddingVerticle),
          decoration: BoxDecoration(
              color: bgColor ?? FMColors.magenta,
              borderRadius: BorderRadius.circular(FMRadii.md)),
          child: isloading == false
              ? Text(
                  lnProvider.getString(title),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                )
              : OthersHelper().showLoading(Colors.white)),
    );
  }

  borderButtonOrange(String title, VoidCallback pressed,
      {bgColor, double paddingVerticle = 17}) {
    return InkWell(
      onTap: pressed,
      child: Container(
          width: double.infinity,
          alignment: Alignment.center,
          padding: EdgeInsets.symmetric(vertical: paddingVerticle),
          decoration: BoxDecoration(
              border: Border.all(color: bgColor ?? FMColors.magenta),
              borderRadius: BorderRadius.circular(FMRadii.md)),
          child: Text(
            title,
            style: TextStyle(
              color: bgColor ?? FMColors.magenta,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          )),
    );
  }

  labelCommon(String title, {margin}) {
    return Container(
      margin: margin ?? const EdgeInsets.only(bottom: 15),
      child: Text(
        lnProvider.getString(title),
        style: const TextStyle(
          color: FMColors.textPrimary,
          fontSize: 14,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  paragraphCommon(
    String title, {
    double fontsize = 14,
    color,
    textAlign = TextAlign.left,
  }) {
    return AutoSizeText(
      title,
      textAlign: textAlign,
      style: TextStyle(
        color: color ?? FMColors.textSecondary,
        height: 1.4,
        fontSize: fontsize,
        fontWeight: FontWeight.w400,
      ),
    );
  }

  titleCommon(String title,
      {double fontsize = 18, color, lineheight = 1.3, maxLines, textAlign}) {
    return Text(
      lnProvider.getString(title),
      maxLines: maxLines,
      textAlign: textAlign,
      style: TextStyle(
          color: color ?? FMColors.textPrimary,
          fontSize: fontsize,
          height: lineheight,
          fontWeight: FontWeight.bold),
    );
  }

  dividerCommon() {
    return const Divider(
      thickness: 1,
      height: 2,
      color: FMColors.border,
    );
  }

  checkCircle() {
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: const BoxDecoration(shape: BoxShape.circle, color: FMColors.magenta),
      child: const Icon(
        Icons.check,
        size: 13,
        color: Colors.white,
      ),
    );
  }

  profileImage(dynamic imageLink, double height, double width) {
    final validUrl = sanitizeImageUrl(imageLink, fallback: userPlaceHolderUrl);
    final cacheW = (width * 2).toInt().clamp(40, 500);
    final cacheH = (height * 2).toInt().clamp(40, 500);
    return ClipRRect(
      borderRadius: BorderRadius.circular(6),
      child: CachedNetworkImage(
        imageUrl: validUrl,
        memCacheWidth: cacheW,
        memCacheHeight: cacheH,
        placeholder: (context, url) {
          return Image.asset('assets/images/loading_image.png');
        },
        errorWidget: (_, string, obj) {
          return Container(
            margin: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
                image: DecorationImage(
                    image: AssetImage(
                      "assets/images/app_icon.png",
                    ),
                    opacity: .5)),
          );
        },
        height: height,
        width: width,
        fit: BoxFit.cover,
      ),
    );
  }

  //no order found
  nothingfound(BuildContext context, String title) {
    return Container(
        height: MediaQuery.of(context).size.height - 120,
        alignment: Alignment.center,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.hourglass_empty_rounded,
              size: 28,
              color: FMColors.textMuted,
            ),
            const SizedBox(
              height: 10,
            ),
            Text(
              lnProvider.getString(title),
              style: const TextStyle(color: FMColors.textMuted, fontSize: 14),
            ),
          ],
        ));
  }
}
