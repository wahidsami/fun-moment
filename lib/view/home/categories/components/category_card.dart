import 'package:auto_size_text/auto_size_text.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:provider/provider.dart';

import '../../../services/service_by_category_page.dart';

class CategoryCard extends StatelessWidget {
  const CategoryCard({
    Key? key,
    required this.name,
    this.nameAr,
    required this.id,
    required this.cc,
    required this.index,
    required this.marginRight,
    required this.imagelink,
  }) : super(key: key);

  final name;
  final nameAr;
  final id;
  final cc;
  final index;
  final imagelink;
  final double marginRight;

  @override
  Widget build(BuildContext context) {
    RtlService? rtl;
    try {
      rtl = Provider.of<RtlService>(context);
    } catch (_) {
      rtl = null;
    }

    final isArabic = rtl?.isArabic ?? false;
    final resolvedDisplayName = isArabic
        ? ((nameAr != null && nameAr.toString().trim().isNotEmpty) ? nameAr.toString() : (name?.toString() ?? ''))
        : (name?.toString() ?? '');

    return InkWell(
      splashColor: Colors.transparent,
      highlightColor: Colors.transparent,
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute<void>(
            builder: (BuildContext context) => ServicebyCategoryPage(
              categoryName: resolvedDisplayName,
              categoryId: id,
            ),
          ),
        );
      },
      child: Container(
        width: 136,
        height: 168,
        margin: EdgeInsets.only(right: marginRight),
        child: FMSurfaceCard(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
          borderRadius: BorderRadius.circular(20),
          borderColor: FMColors.border,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                height: 74,
                width: 74,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(18),
                  color: FMColors.surfaceElevated,
                  border: Border.all(
                    color: FMColors.magenta.withOpacity(0.35),
                    width: 1.2,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: FMColors.magenta.withOpacity(0.20),
                      blurRadius: 14,
                      offset: const Offset(0, 4),
                    ),
                    BoxShadow(
                      color: FMColors.cyan.withOpacity(0.10),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                padding: const EdgeInsets.all(4),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(14),
                  child: (imagelink != null &&
                          imagelink.toString().isNotEmpty &&
                          imagelink.toString() != placeHolderUrl)
                      ? CachedNetworkImage(
                          imageUrl: imagelink.toString(),
                          memCacheWidth: 150,
                          fit: BoxFit.contain,
                          errorWidget: (context, url, error) => Image.asset(
                            FMAssets.categoryFallbackForIndex(index),
                            fit: BoxFit.contain,
                          ),
                        )
                      : Image.asset(
                          FMAssets.categoryFallbackForIndex(index),
                          fit: BoxFit.contain,
                        ),
                ),
              ),
              const SizedBox(height: 10),
              Expanded(
                child: Center(
                  child: AutoSizeText(
                    resolvedDisplayName,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    minFontSize: 11,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontFamily: 'Cairo',
                      fontSize: 13.5,
                      fontWeight: FontWeight.w700,
                      color: FMColors.textPrimary,
                      height: 1.25,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
