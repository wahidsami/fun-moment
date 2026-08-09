import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/all_services_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/home_services/recent_services_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/view/home/components/section_title.dart';
import 'package:funmoments/view/home/components/service_card.dart';
import 'package:funmoments/view/home/top_all_service_page.dart';
import 'package:funmoments/view/services/service_details_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class RecentServices extends StatelessWidget {
  const RecentServices({
    Key? key,
    required this.cc,
    required this.asProvider,
  }) : super(key: key);

  final cc;
  final asProvider;

  @override
  Widget build(BuildContext context) {
    return Consumer<RecentServicesService>(
      builder: (context, provider, child) {
        if (provider.hasService == false) {
          return const SizedBox.shrink();
        }

        if (provider.recentServiceMap.isEmpty) {
          return FMScreenState.empty(
            title: asProvider.getString('No service available in your area'),
            message: asProvider.getString(
                'The latest services will appear here once the backend has records.'),
          );
        }

        if (provider.recentServiceMap[0] == 'error') {
          return FMScreenState.error(
            title: asProvider.getString('Something went wrong'),
            message: asProvider.getString(
                'We could not load recent services right now. Please try again.'),
            actionLabel: asProvider.getString('Retry'),
            onAction: () => provider.fetchRecentService(),
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 26),
            Consumer<AllServicesService>(
              builder: (context, allServiceProvider, child) => SectionTitle(
                cc: cc,
                title: asProvider.getString('Recently listed'),
                pressed: () {
                  allServiceProvider.setSortbyValue('Latest Service');
                  allServiceProvider.setSelectedSortbyId('latest_service');
                  allServiceProvider.setEverythingToDefault();
                  Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (BuildContext context) => const AllServicePage(),
                    ),
                  );
                },
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 360,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                shrinkWrap: true,
                clipBehavior: Clip.none,
                itemCount: provider.recentServiceMap.length,
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
                        .fetchServiceDetails(
                            provider.recentServiceMap[i]['serviceId']);
                  },
                  child: ServiceCard(
                    cc: cc,
                    imageLink:
                        provider.recentServiceMap[i]['image'] ?? placeHolderUrl,
                    rating: twoDouble(provider.recentServiceMap[i]['rating']),
                    title: provider.recentServiceMap[i]['title'],
                    sellerName: provider.recentServiceMap[i]['sellerName'],
                    price: provider.recentServiceMap[i]['price'],
                    buttonText: 'Book Now',
                    width: MediaQuery.of(context).size.width - 96,
                    marginRight: 0.0,
                    pressed: () {
                      provider.saveOrUnsave(
                        provider.recentServiceMap[i]['serviceId'],
                        provider.recentServiceMap[i]['title'],
                        provider.recentServiceMap[i]['image'],
                        provider.recentServiceMap[i]['price'],
                        provider.recentServiceMap[i]['sellerName'],
                        twoDouble(provider.recentServiceMap[i]['rating']),
                        i,
                        context,
                        provider.recentServiceMap[i]['sellerId'],
                      );
                    },
                    isSaved:
                        provider.recentServiceMap[i]['isSaved'] == true ? true : false,
                    serviceId: provider.recentServiceMap[i]['serviceId'],
                    sellerId: provider.recentServiceMap[i]['sellerId'],
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
