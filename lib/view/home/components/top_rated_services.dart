import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/home_services/top_rated_services_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/view/home/components/section_title.dart';
import 'package:funmoments/view/home/components/service_card.dart';
import 'package:funmoments/view/home/top_all_service_page.dart';
import 'package:funmoments/view/services/service_details_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class TopRatedServices extends StatelessWidget {
  const TopRatedServices({
    Key? key,
    required this.cc,
    required this.asProvider,
  }) : super(key: key);

  final cc;
  final asProvider;

  @override
  Widget build(BuildContext context) {
    return Consumer<TopRatedServicesSerivce>(
      builder: (context, provider, child) {
        if (provider.topServiceMap.isEmpty) {
          return const SizedBox.shrink();
        }

        if (provider.topServiceMap[0] == 'error') {
          return FMScreenState.error(
            title: asProvider.getString('Something went wrong'),
            message: asProvider.getString(
                'We could not load top services right now. Please try again.'),
            actionLabel: asProvider.getString('Retry'),
            onAction: () => provider.fetchTopService(),
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 26),
            SectionTitle(
              cc: cc,
              title: asProvider.getString('Top booked services'),
              pressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (BuildContext context) =>
                        const TopAllServicePage(),
                  ),
                );
              },
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 360,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                shrinkWrap: true,
                clipBehavior: Clip.none,
                itemCount: provider.topServiceMap.length,
                separatorBuilder: (_, __) => const SizedBox(width: 16),
                itemBuilder: (context, i) => InkWell(
                  splashColor: Colors.transparent,
                  highlightColor: Colors.transparent,
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (BuildContext context) =>
                            const ServiceDetailsPage(),
                      ),
                    );
                    Provider.of<ServiceDetailsService>(context, listen: false)
                        .fetchServiceDetails(provider.topServiceMap[i]['serviceId']);
                  },
                  child: ServiceCard(
                    cc: cc,
                    imageLink: provider.topServiceMap[i]['image'] ?? placeHolderUrl,
                    rating: twoDouble(provider.topServiceMap[i]['rating']),
                    title: provider.topServiceMap[i]['title'],
                    sellerName: provider.topServiceMap[i]['sellerName'],
                    price: provider.topServiceMap[i]['price'],
                    buttonText: 'Book Now',
                    width: MediaQuery.of(context).size.width - 96,
                    marginRight: 0.0,
                    pressed: () {
                      provider.saveOrUnsave(
                        provider.topServiceMap[i]['serviceId'],
                        provider.topServiceMap[i]['title'],
                        provider.topServiceMap[i]['image'],
                        provider.topServiceMap[i]['price'],
                        provider.topServiceMap[i]['sellerName'],
                        twoDouble(provider.topServiceMap[i]['rating']),
                        i,
                        context,
                        provider.topServiceMap[i]['sellerId'],
                      );
                    },
                    isSaved:
                        provider.topServiceMap[i]['isSaved'] == true ? true : false,
                    serviceId: provider.topServiceMap[i]['serviceId'],
                    sellerId: provider.topServiceMap[i]['sellerId'],
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}
