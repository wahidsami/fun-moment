import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

import 'package:funmoments/theme/fun_moment_theme.dart';

class ImageBig extends StatelessWidget {
  const ImageBig({Key? key, required this.serviceName, required this.imageLink})
      : super(key: key);
  final dynamic serviceName;
  final dynamic imageLink;

  @override
  Widget build(BuildContext context) {
    final rawUrl = imageLink?.toString().trim();
    final hasValidRemoteUrl = rawUrl != null &&
        rawUrl.isNotEmpty &&
        rawUrl.startsWith('http') &&
        rawUrl != placeHolderUrl &&
        !rawUrl.contains('i.postimg.cc/rpsKNndW/New-Project.png');

    final Widget fallbackWidget = Image.asset(
      FMAssets.djPerformance,
      fit: BoxFit.cover,
      width: double.infinity,
      height: 295,
    );

    return Stack(
      children: [
        SizedBox(
            height: 295,
            width: double.infinity,
            child: hasValidRemoteUrl
                ? CachedNetworkImage(
                    imageUrl: rawUrl,
                    memCacheWidth: 800,
                    maxWidthDiskCache: 1200,
                    errorWidget: (context, url, error) => fallbackWidget,
                    placeholder: (context, url) => fallbackWidget,
                    fit: BoxFit.cover,
                  )
                : fallbackWidget),
        Container(
          height: 295,
          decoration: BoxDecoration(
            gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Colors.black.withOpacity(.7),
                  Colors.black.withOpacity(.1)
                ]),
          ),
        ),
        Consumer<RtlService>(
          builder: (context, rtlP, child) => Positioned(
              left: 10,
              top: 30,
              right: rtlP.direction == 'ltr' ? 30 : 10,
              child: Row(
                children: [
                  IconButton(
                    onPressed: () {
                      Navigator.pop(context);
                    },
                    icon: const Icon(Icons.arrow_back_ios_rounded),
                    color: Colors.white,
                    iconSize: 19,
                  ),
                  Container(
                    margin: EdgeInsets.only(
                        left: MediaQuery.of(context).size.width / 4),
                    child: Text(
                      serviceName,
                      textAlign: TextAlign.start,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              )),
        )
      ],
    );
  }
}
