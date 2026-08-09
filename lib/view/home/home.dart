import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/filter_services_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/home_services/slider_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/home_services/recent_services_service.dart';
import 'package:funmoments/service/home_services/top_rated_services_service.dart';
import 'package:funmoments/service/jobs_service/recent_jobs_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/home/components/categories.dart';
import 'package:funmoments/view/home/components/home_app_bar.dart';
import 'package:funmoments/view/home/components/recent_jobs.dart';
import 'package:funmoments/view/home/components/recent_services.dart';
import 'package:funmoments/view/home/components/slider_home.dart';
import 'package:funmoments/view/home/components/top_rated_services.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/search/service_filter_molde.dart';
import 'package:funmoments/view/utils/constant_styles.dart';
import 'package:funmoments/view/utils/responsive.dart';

class Homepage extends StatefulWidget {
  const Homepage({Key? key}) : super(key: key);

  @override
  State<Homepage> createState() => _HomepageState();
}

class _HomepageState extends State<Homepage> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    setChatSellerId(null);
    runAtHome(context);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _goToDiscover() {
    HomepageHelper.tabIndex.value = 1;
    Provider.of<FilterServicesService>(context, listen: false)
        .resetFilters(st: _searchController.text.trim());
    ServiceFilterViewModel.instance.searchTextController.text =
        _searchController.text.trim();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FMColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          physics: physicsCommon,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const HomeAppBar(),
                const SizedBox(height: 20),
                FMSurfaceCard(
                  gradient: LinearGradient(
                    colors: [
                      FMColors.surfaceElevated,
                      FMColors.surface.withOpacity(.85),
                    ],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  child: Stack(
                    children: [
                      Positioned.fill(
                        child: FMAssetImageFrame(
                          assetPath: FMAssets.homeHero,
                          overlay: true,
                          borderRadius: BorderRadius.circular(FMRadii.lg),
                        ),
                      ),
                      Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(FMRadii.lg),
                          gradient: LinearGradient(
                            colors: [
                              FMColors.overlay.withOpacity(.2),
                              FMColors.background.withOpacity(.82),
                            ],
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                          ),
                        ),
                      ),
                      Consumer<AppStringService>(
                        builder: (context, asProvider, child) => Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              asProvider.getString('Discover your next moment'),
                              style: Theme.of(context)
                                  .textTheme
                                  .headlineMedium
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                  ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              asProvider.getString(
                                  'Search premium services, providers, and bookings'),
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: FMColors.magentaLight,
                                  ),
                            ),
                            const SizedBox(height: 16),
                            TextField(
                              controller: _searchController,
                              textInputAction: TextInputAction.search,
                              onSubmitted: (_) => _goToDiscover(),
                              decoration: InputDecoration(
                                hintText: asProvider.getString('Search services'),
                                prefixIcon: const Icon(Icons.search_rounded),
                                suffixIcon: InkWell(
                                  borderRadius: BorderRadius.circular(999),
                                  onTap: _goToDiscover,
                                  child: const Icon(Icons.arrow_forward_rounded),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                Consumer<SliderService>(
                  builder: (context, provider, child) =>
                      provider.sliderImageList.isNotEmpty
                          ? SliderHome(
                              cc: null,
                              sliderDetailsList: provider.sliderDetailsList,
                              sliderImageList: provider.sliderImageList,
                            )
                          : const _DarkLoadingCard(height: 180),
                ),
                const SizedBox(height: 22),
                Consumer<AppStringService>(
                  builder: (context, asProvider, child) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      FMAppSectionHeader(
                        title: asProvider.getString('Browse categories'),
                        subtitle: asProvider.getString(
                            'Explore the most requested moments first'),
                      ),
                      const SizedBox(height: 14),
                      Categories(
                        cc: null,
                        asProvider: asProvider,
                      ),
                      const SizedBox(height: 20),
                      TopRatedServices(
                        cc: null,
                        asProvider: asProvider,
                      ),
                      RecentServices(
                        cc: null,
                        asProvider: asProvider,
                      ),
                      const SizedBox(height: 12),
                      const RecentJobs(),
                      const SizedBox(height: 24),
                      Consumer<ProfileService>(
                        builder: (context, profileProvider, child) {
                          final name =
                              profileProvider.profileDetails?.userDetails.name;
                          if (name == null) {
                            return const SizedBox.shrink();
                          }
                          return FMSurfaceCard(
                            gradient: FMGradients.funGradient,
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.bolt_rounded,
                                  color: Colors.white,
                                  size: 26,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Text(
                                    asProvider.getString(
                                        'Active booking and updates will appear here'),
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyLarge
                                        ?.copyWith(color: Colors.white),
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DarkLoadingCard extends StatelessWidget {
  const _DarkLoadingCard({required this.height});

  final double height;

  @override
  Widget build(BuildContext context) {
    return FMSurfaceCard(
      padding: EdgeInsets.zero,
      child: SizedBox(
        height: height,
        child: const Center(
          child: SizedBox(
            height: 22,
            width: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              valueColor: AlwaysStoppedAnimation<Color>(FMColors.magenta),
            ),
          ),
        ),
      ),
    );
  }
}
