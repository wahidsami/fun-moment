import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/filter_services_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/home_services/slider_service.dart';
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
import 'package:funmoments/view/music/music_marketplace_page.dart';
import 'package:funmoments/view/search/service_filter_molde.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
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
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      runAtHome(context);
    });
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
    final cc = ConstantColors();
    return Scaffold(
      backgroundColor: FMColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          color: FMColors.magenta,
          backgroundColor: FMColors.card,
          onRefresh: () async {
            await runAtHome(context, isRefresh: true);
          },
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
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
                Consumer<SliderService>(
                  builder: (context, provider, child) {
                    if (provider.isLoading && provider.sliderDetailsList.isEmpty) {
                      return const Padding(
                        padding: EdgeInsets.only(top: 20, bottom: 22),
                        child: _DarkLoadingCard(height: 180),
                      );
                    }
                    if (provider.sliderDetailsList.isEmpty) {
                      return const SizedBox(height: 16);
                    }
                    return Padding(
                      padding: const EdgeInsets.only(top: 20, bottom: 22),
                      child: SliderHome(
                        cc: cc,
                        sliderDetailsList: provider.sliderDetailsList,
                        sliderImageList: provider.sliderImageList,
                      ),
                    );
                  },
                ),
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
                        cc: cc,
                        asProvider: asProvider,
                      ),
                      const SizedBox(height: 20),
                      TopRatedServices(
                        cc: cc,
                        asProvider: asProvider,
                      ),
                      const SizedBox(height: 16),
                      GestureDetector(
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => const MusicMarketplacePage(),
                            ),
                          );
                        },
                        child: Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(FMRadii.lg),
                            gradient: const LinearGradient(
                              colors: [Color(0xFF1E1338), Color(0xFF101B34)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            border: Border.all(color: FMColors.cyan.withOpacity(0.4)),
                            boxShadow: const [
                              BoxShadow(
                                color: Color(0x332DE2FF),
                                blurRadius: 14,
                                offset: Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 48,
                                height: 48,
                                decoration: const BoxDecoration(
                                  shape: BoxShape.circle,
                                  gradient: FMGradients.funGradient,
                                ),
                                child: const Icon(
                                  Icons.music_note_rounded,
                                  color: Colors.white,
                                  size: 26,
                                ),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Text(
                                          asProvider.getString('Music Marketplace'),
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w800,
                                            fontSize: 15,
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: FMColors.magenta.withOpacity(0.25),
                                            borderRadius: BorderRadius.circular(4),
                                            border: Border.all(color: FMColors.magenta, width: 0.5),
                                          ),
                                          child: const Text(
                                            'NEW',
                                            style: TextStyle(
                                              color: FMColors.magentaLight,
                                              fontSize: 9,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      asProvider.getString('Stream and explore exclusive music tracks & releases'),
                                      style: const TextStyle(
                                        color: FMColors.textMuted,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const Icon(
                                Icons.arrow_forward_ios_rounded,
                                color: FMColors.cyan,
                                size: 14,
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      RecentServices(
                        cc: cc,
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
