import 'package:auto_size_text/auto_size_text.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:page_transition/page_transition.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/booking_services/book_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/booking/service_personalization_page.dart';
import 'package:funmoments/view/utils/responsive.dart';

import '../../../service/booking_services/personalization_service.dart';
import '../../utils/constant_colors.dart';

class ServiceCard extends StatelessWidget {
  const ServiceCard({
    Key? key,
    required this.cc,
    required this.imageLink,
    required this.title,
    required this.sellerName,
    required this.buttonText,
    required this.rating,
    required this.price,
    required this.width,
    required this.marginRight,
    required this.pressed,
    required this.isSaved,
    required this.serviceId,
    required this.sellerId,
  }) : super(key: key);

  final ConstantColors cc;
  final serviceId;
  final imageLink;
  final title;
  final sellerName;
  final buttonText;
  final rating;
  final price;
  final width;
  final marginRight;
  final VoidCallback pressed;
  final bool isSaved;
  final sellerId;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) => Container(
        alignment: Alignment.center,
        width: width,
        margin: EdgeInsets.only(right: marginRight),
        child: FMSurfaceCard(
          padding: EdgeInsets.zero,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  SizedBox(
                    height: 210,
                    width: double.infinity,
                    child: FMNetworkImageFrame(
                      imageUrl: imageLink,
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(FMRadii.lg),
                      ),
                      overlay: true,
                    ),
                  ),
                  Positioned(
                    left: 14,
                    right: 14,
                    bottom: 14,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        if (rating != 0.0)
                          _RatingPill(rating: rating)
                        else
                          const SizedBox.shrink(),
                        _SaveButton(
                          isSaved: isSaved,
                          onTap: pressed,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            fontSize: 17,
                            height: 1.25,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      sellerName ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        Expanded(
                          child: Row(
                            children: [
                              AutoSizeText(
                                '${asProvider.getString('Starts from')}:',
                                maxLines: 1,
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              const SizedBox(width: 8),
                              Consumer<RtlService>(
                                builder: (context, rtlP, child) => Flexible(
                                  child: Text(
                                    rtlP.currencyDirection == 'left'
                                        ? '${rtlP.currency}$price'
                                        : '$price${rtlP.currency}',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineSmall
                                        ?.copyWith(
                                          color: FMColors.magentaLight,
                                          fontSize: 21,
                                        ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        SizedBox(
                          height: 44,
                          child: ElevatedButton(
                            onPressed: () {
                              Provider.of<BookService>(context, listen: false)
                                  .setData(serviceId, title, price, sellerId,
                                      image: imageLink);
                              Provider.of<PersonalizationService>(context,
                                      listen: false)
                                  .setDefaultPrice(Provider.of<BookService>(
                                          context,
                                          listen: false)
                                      .totalPrice);
                              Provider.of<PersonalizationService>(context,
                                      listen: false)
                                  .fetchServiceExtra(serviceId, context);
                              Navigator.push(
                                context,
                                PageTransition(
                                  type: PageTransitionType.rightToLeft,
                                  child: const ServicePersonalizationPage(),
                                ),
                              );
                            },
                            child: Text(
                              asProvider.getString(buttonText),
                              style: const TextStyle(fontSize: 13),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RatingPill extends StatelessWidget {
  const _RatingPill({required this.rating});

  final rating;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: FMColors.overlay,
        borderRadius: BorderRadius.circular(FMRadii.pill),
        border: Border.all(color: FMColors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.star_rounded, color: FMColors.warning, size: 16),
          const SizedBox(width: 4),
          Text(
            rating.toString(),
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: FMColors.textPrimary,
                ),
          ),
        ],
      ),
    );
  }
}

class _SaveButton extends StatelessWidget {
  const _SaveButton({required this.isSaved, required this.onTap});

  final bool isSaved;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(FMRadii.pill),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: FMColors.overlay,
          borderRadius: BorderRadius.circular(FMRadii.pill),
          border: Border.all(color: FMColors.border),
        ),
        child: SvgPicture.asset(
          isSaved ? 'assets/svg/saved-fill-icon.svg' : 'assets/svg/saved-icon.svg',
          color: isSaved ? FMColors.magenta : FMColors.textMuted,
          height: 18,
        ),
      ),
    );
  }
}
