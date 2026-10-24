import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/view/services/service_details_page.dart';
import 'package:funmoments/view/services/service_by_category_page.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:url_launcher/url_launcher.dart';

class SliderHome extends StatelessWidget {
  const SliderHome({
    Key? key,
    this.cc,
    this.sliderDetailsList,
    this.sliderImageList,
  }) : super(key: key);

  final cc;
  final sliderDetailsList;
  final sliderImageList;

  /// Safely routes slide tap to service, category, or validated external URL
  static void handleSlideAction(BuildContext context, dynamic slide) {
    if (slide == null || slide is! Map) return;

    final actionType = slide['action_type']?.toString().toLowerCase().trim() ?? 'none';
    final actionTarget = slide['action_target']?.toString().trim();

    if (actionType == 'service') {
      final serviceId = int.tryParse(actionTarget ?? '') ??
          (slide['service_id'] is int
              ? slide['service_id'] as int
              : int.tryParse(slide['service_id']?.toString() ?? ''));
      if (serviceId != null && serviceId > 0) {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => const ServiceDetailsPage(),
          ),
        );
        try {
          Provider.of<ServiceDetailsService>(context, listen: false)
              .fetchServiceDetails(serviceId);
        } catch (_) {}
      }
    } else if (actionType == 'category') {
      final categoryId = int.tryParse(actionTarget ?? '');
      if (categoryId != null && categoryId > 0) {
        final categoryTitle = slide['title']?.toString() ?? '';
        Navigator.push(
          context,
          MaterialPageRoute<void>(
            builder: (BuildContext context) => ServicebyCategoryPage(
              categoryName: categoryTitle,
              categoryId: categoryId,
            ),
          ),
        );
      }
    } else if (actionType == 'url') {
      if (actionTarget != null && actionTarget.isNotEmpty) {
        final uri = Uri.tryParse(actionTarget);
        if (uri != null && (uri.scheme == 'http' || uri.scheme == 'https')) {
          launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (sliderDetailsList == null ||
        sliderImageList == null ||
        sliderDetailsList.isEmpty ||
        sliderImageList.isEmpty) {
      return SizedBox(
        height: 220,
        width: double.infinity,
        child: CarouselSlider.builder(
          itemCount: FMAssets.homePromoAssets.length,
          options: CarouselOptions(
            autoPlay: true,
            enlargeCenterPage: false,
            viewportFraction: 0.92,
            aspectRatio: 16 / 9,
            initialPage: 0,
          ),
          itemBuilder: (BuildContext context, int itemIndex, int pageViewIndex) {
            final promoTitles = [
              lnProvider.getString('Book a DJ'),
              lnProvider.getString('DJ booking'),
              lnProvider.getString('Rent Party Equipment'),
              lnProvider.getString('Riyadh nights'),
              lnProvider.getString('Discover your next event'),
              lnProvider.getString('Premium entertainment for Riyadh and Saudi Arabia'),
            ];
            final promoSubtitles = [
              lnProvider.getString(
                'Premium entertainment for Riyadh and Saudi Arabia',
              ),
              lnProvider.getString(
                'Everything you need for music and events',
              ),
              lnProvider.getString(
                'Everything you need for music and events',
              ),
              lnProvider.getString(
                'Premium entertainment for Riyadh and Saudi Arabia',
              ),
              lnProvider.getString('Everything you need for music and events'),
              lnProvider.getString('Everything you need for music and events'),
            ];
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: FMSurfaceCard(
                padding: EdgeInsets.zero,
                gradient: FMGradients.energyGradient,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    FMAssetImageFrame(
                      assetPath: FMAssets.homePromoAssets[itemIndex],
                      overlay: true,
                      borderRadius: BorderRadius.circular(FMRadii.lg),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(18),
                      child: Consumer<RtlService>(
                        builder: (context, rtlP, child) => Align(
                          alignment: rtlP.direction == 'ltr'
                              ? Alignment.bottomLeft
                              : Alignment.bottomRight,
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.end,
                            crossAxisAlignment: rtlP.direction == 'ltr'
                                ? CrossAxisAlignment.start
                                : CrossAxisAlignment.end,
                            children: [
                              Container(
                                constraints: BoxConstraints(
                                  maxWidth:
                                      MediaQuery.of(context).size.width * .62,
                                ),
                                child: Text(
                                  promoTitles[itemIndex % promoTitles.length],
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  textAlign: rtlP.direction == 'ltr'
                                      ? TextAlign.left
                                      : TextAlign.right,
                                  style: Theme.of(context)
                                      .textTheme
                                      .headlineSmall
                                      ?.copyWith(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w800,
                                      ),
                                ),
                              ),
                              const SizedBox(height: 6),
                              Container(
                                constraints: BoxConstraints(
                                  maxWidth:
                                      MediaQuery.of(context).size.width * .58,
                                ),
                                child: Text(
                                  promoSubtitles[itemIndex % promoSubtitles.length],
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  textAlign: rtlP.direction == 'ltr'
                                      ? TextAlign.left
                                      : TextAlign.right,
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(
                                        color: FMColors.magentaLight,
                                        fontWeight: FontWeight.w500,
                                      ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      );
    }

    return SizedBox(
      height: 220,
      width: double.infinity,
      child: CarouselSlider.builder(
        itemCount: sliderDetailsList.length,
        options: CarouselOptions(
          autoPlay: true,
          enlargeCenterPage: false,
          viewportFraction: 0.92,
          aspectRatio: 16 / 9,
          initialPage: 0,
        ),
        itemBuilder: (BuildContext context, int itemIndex, int pageViewIndex) {
          final slide = (itemIndex < sliderDetailsList.length && sliderDetailsList[itemIndex] is Map)
              ? sliderDetailsList[itemIndex]
              : null;
          final btnText = slide?['btn_text']?.toString().trim();
          final actionType = slide?['action_type']?.toString().toLowerCase().trim();
          final hasAction = actionType != null && actionType != 'none' && actionType.isNotEmpty;

          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: InkWell(
              borderRadius: BorderRadius.circular(FMRadii.lg),
              onTap: hasAction ? () => handleSlideAction(context, slide) : null,
              child: FMSurfaceCard(
                padding: EdgeInsets.zero,
                gradient: FMGradients.energyGradient,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    FMNetworkImageFrame(
                      imageUrl: (itemIndex < sliderImageList.length)
                          ? (sliderImageList[itemIndex]?.toString() ?? '')
                          : '',
                      overlay: true,
                      borderRadius: BorderRadius.circular(FMRadii.lg),
                    ),
                    Consumer<RtlService>(
                      builder: (context, rtlP, child) => Positioned(
                        left: rtlP.direction == 'ltr' ? 18 : 12,
                        right: rtlP.direction == 'ltr' ? 12 : 18,
                        bottom: 18,
                        child: Column(
                          crossAxisAlignment: rtlP.direction == 'ltr'
                              ? CrossAxisAlignment.start
                              : CrossAxisAlignment.end,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              constraints: BoxConstraints(
                                maxWidth: MediaQuery.of(context).size.width * .62,
                              ),
                              child: Text(
                                slide?['title']?.toString() ?? '',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                textAlign: rtlP.direction == 'ltr'
                                    ? TextAlign.left
                                    : TextAlign.right,
                                style: Theme.of(context)
                                    .textTheme
                                    .headlineSmall
                                    ?.copyWith(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                    ),
                              ),
                            ),
                            const SizedBox(height: 6),
                            Container(
                              constraints: BoxConstraints(
                                maxWidth: MediaQuery.of(context).size.width * .58,
                              ),
                              child: Text(
                                slide?['subtitle']?.toString() ?? '',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                textAlign: rtlP.direction == 'ltr'
                                    ? TextAlign.left
                                    : TextAlign.right,
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(color: FMColors.magentaLight),
                              ),
                            ),
                            if (btnText != null && btnText.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  gradient: FMGradients.funGradient,
                                  borderRadius: BorderRadius.circular(FMRadii.sm),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withOpacity(0.3),
                                      blurRadius: 4,
                                      offset: const Offset(0, 2),
                                    ),
                                  ],
                                ),
                                child: Text(
                                  btnText,
                                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w800,
                                      ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
