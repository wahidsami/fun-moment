import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/utils/responsive.dart';

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
                                  promoTitles[itemIndex],
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
                                  promoSubtitles[itemIndex],
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
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: FMSurfaceCard(
              padding: EdgeInsets.zero,
              gradient: FMGradients.energyGradient,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  FMNetworkImageFrame(
                    imageUrl: sliderImageList[itemIndex],
                    overlay: true,
                    borderRadius: BorderRadius.circular(FMRadii.lg),
                  ),
                  Consumer<RtlService>(
                    builder: (context, rtlP, child) => Positioned(
                      left: rtlP.direction == 'ltr' ? 18 : 12,
                      right: rtlP.direction == 'ltr' ? 12 : 18,
                      bottom: 18,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            constraints: BoxConstraints(
                              maxWidth: MediaQuery.of(context).size.width * .58,
                            ),
                            child: Text(
                              sliderDetailsList[itemIndex]['title'],
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
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
                              maxWidth: MediaQuery.of(context).size.width * .56,
                            ),
                            child: Text(
                              sliderDetailsList[itemIndex]['subtitle'],
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(color: FMColors.magentaLight),
                            ),
                          ),
                        ],
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
}
