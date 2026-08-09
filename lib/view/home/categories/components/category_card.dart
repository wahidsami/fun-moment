import 'package:auto_size_text/auto_size_text.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/others_helper.dart';

import '../../../services/service_by_category_page.dart';

class CategoryCard extends StatelessWidget {
  const CategoryCard({
    Key? key,
    required this.name,
    required this.id,
    required this.cc,
    required this.index,
    required this.marginRight,
    required this.imagelink,
  }) : super(key: key);

  final name;
  final id;
  final cc;
  final index;
  final imagelink;
  final double marginRight;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      splashColor: Colors.transparent,
      highlightColor: Colors.transparent,
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute<void>(
            builder: (BuildContext context) => ServicebyCategoryPage(
              categoryName: name,
              categoryId: id,
            ),
          ),
        );
      },
      child: Container(
        width: 104,
        margin: EdgeInsets.only(right: marginRight),
        child: FMSurfaceCard(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                height: 54,
                width: 54,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: FMGradients.funGradient,
                  boxShadow: FMShadows.subtleGlow,
                ),
                padding: const EdgeInsets.all(10),
                child: (imagelink != null &&
                        imagelink.toString().isNotEmpty &&
                        imagelink.toString() != placeHolderUrl)
                    ? CachedNetworkImage(
                        imageUrl: imagelink,
                        errorWidget: (context, url, error) => Image.asset(
                          FMAssets.categoryFallbackForIndex(index),
                          fit: BoxFit.contain,
                        ),
                        fit: BoxFit.contain,
                      )
                    : Image.asset(
                        FMAssets.categoryFallbackForIndex(index),
                        fit: BoxFit.contain,
                      ),
              ),
              const SizedBox(height: 10),
              AutoSizeText(
                name,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: FMColors.textPrimary,
                      fontWeight: FontWeight.w600,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
