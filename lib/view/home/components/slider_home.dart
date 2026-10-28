import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/view/services/service_details_page.dart';
import 'package:funmoments/view/services/service_by_category_page.dart';
import 'package:funmoments/view/music/music_marketplace_page.dart';
import 'package:funmoments/view/home/categories/all_categories_page.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/wallet/wallet_page.dart';
import 'package:funmoments/view/home/top_all_service_page.dart';
import 'package:url_launcher/url_launcher.dart';

class SliderHome extends StatelessWidget {
  const SliderHome({
    Key? key,
    this.cc,
    this.sliderDetailsList,
    this.sliderImageList,
  }) : super(key: key);

  final dynamic cc;
  final List<dynamic>? sliderDetailsList;
  final List<dynamic>? sliderImageList;

  /// Safely routes slide tap to service, category, app section, or validated external URL
  static void handleSlideAction(BuildContext context, dynamic slide) {
    if (slide == null || slide is! Map) return;

    final actionType =
        slide['action_type']?.toString().toLowerCase().trim() ?? 'none';
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
    } else if (actionType == 'section') {
      handleSectionNavigation(context, actionTarget);
    } else if (actionType == 'url') {
      if (actionTarget != null && actionTarget.isNotEmpty) {
        final uri = Uri.tryParse(actionTarget);
        if (uri != null && (uri.scheme == 'http' || uri.scheme == 'https')) {
          launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }
    }
  }

  /// Safely routes slide tap to canonical main application section
  static void handleSectionNavigation(BuildContext context, String? sectionKey) {
    if (sectionKey == null || sectionKey.trim().isEmpty) return;

    final key = sectionKey.toLowerCase().trim();
    switch (key) {
      case 'music_marketplace':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => const MusicMarketplacePage(),
          ),
        );
        break;
      case 'all_categories':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => const AllCategoriesPage(),
          ),
        );
        break;
      case 'bookings':
        HomepageHelper.tabIndex.value = 2;
        break;
      case 'discover':
        HomepageHelper.tabIndex.value = 1;
        break;
      case 'saved':
        HomepageHelper.tabIndex.value = 3;
        break;
      case 'wallet':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => const WalletPage(),
          ),
        );
        break;
      case 'top_services':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => const TopAllServicePage(),
          ),
        );
        break;
      default:
        debugPrint('[SliderHome] Unrecognized section key "$key"; safe no-op');
        break;
    }
  }

  Widget _buildSlideCard({
    required BuildContext context,
    required Map slide,
    required String imgUrl,
    required String? btnText,
    required bool hasAction,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(FMRadii.lg),
      onTap: hasAction ? () => handleSlideAction(context, slide) : null,
      child: FMSurfaceCard(
        padding: EdgeInsets.zero,
        gradient: FMGradients.energyGradient,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (imgUrl.isNotEmpty)
              FMNetworkImageFrame(
                imageUrl: imgUrl,
                overlay: true,
                borderRadius: BorderRadius.circular(FMRadii.lg),
              )
            else
              Container(
                decoration: BoxDecoration(
                  gradient: FMGradients.energyGradient,
                  borderRadius: BorderRadius.circular(FMRadii.lg),
                ),
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
                        slide['title']?.toString() ?? '',
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
                        slide['subtitle']?.toString() ?? '',
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
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 4),
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
                          style:
                              Theme.of(context).textTheme.labelSmall?.copyWith(
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
    );
  }

  @override
  Widget build(BuildContext context) {
    // Phase 3: If no slides exist (0 slides returned, error, offline), hide carousel completely.
    // Hardcoded promotional banners are removed from the home carousel path.
    if (sliderDetailsList == null || sliderDetailsList!.isEmpty) {
      return const SizedBox.shrink();
    }

    final totalCount = sliderDetailsList!.length;

    // Phase 2: If exactly 1 slide, render exactly 1 card.
    // No CarouselSlider infinite looping, no peeking duplicate copy, no autoplay transitions.
    if (totalCount == 1) {
      final slide =
          sliderDetailsList![0] is Map ? sliderDetailsList![0] as Map : {};
      final btnText = slide['btn_text']?.toString().trim();
      final actionType = slide['action_type']?.toString().toLowerCase().trim();
      final hasAction =
          actionType != null && actionType != 'none' && actionType.isNotEmpty;
      final imgUrl = (sliderImageList != null && sliderImageList!.isNotEmpty)
          ? (sliderImageList![0]?.toString() ?? '')
          : (slide['img_url']?.toString() ?? '');

      return SizedBox(
        height: 220,
        width: double.infinity,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: _buildSlideCard(
            context: context,
            slide: slide,
            imgUrl: imgUrl,
            btnText: btnText,
            hasAction: hasAction,
          ),
        ),
      );
    }

    // Multiple slides: Use CarouselSlider with exact itemCount and infinite scroll enabled
    return SizedBox(
      height: 220,
      width: double.infinity,
      child: CarouselSlider.builder(
        itemCount: totalCount,
        options: CarouselOptions(
          autoPlay: true,
          autoPlayInterval: const Duration(seconds: 4),
          autoPlayAnimationDuration: const Duration(milliseconds: 800),
          autoPlayCurve: Curves.fastOutSlowIn,
          enlargeCenterPage: false,
          viewportFraction: 0.92,
          aspectRatio: 16 / 9,
          initialPage: 0,
          enableInfiniteScroll: true,
        ),
        itemBuilder: (BuildContext context, int itemIndex, int pageViewIndex) {
          final slide = (itemIndex < sliderDetailsList!.length &&
                  sliderDetailsList![itemIndex] is Map)
              ? sliderDetailsList![itemIndex] as Map
              : {};
          final btnText = slide['btn_text']?.toString().trim();
          final actionType =
              slide['action_type']?.toString().toLowerCase().trim();
          final hasAction = actionType != null &&
              actionType != 'none' &&
              actionType.isNotEmpty;
          final imgUrl = (sliderImageList != null &&
                  itemIndex < sliderImageList!.length)
              ? (sliderImageList![itemIndex]?.toString() ?? '')
              : (slide['img_url']?.toString() ?? '');

          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: _buildSlideCard(
              context: context,
              slide: slide,
              imgUrl: imgUrl,
              btnText: btnText,
              hasAction: hasAction,
            ),
          );
        },
      ),
    );
  }
}
