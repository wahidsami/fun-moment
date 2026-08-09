import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/home/categories/components/category_card.dart';

class Categories extends StatelessWidget {
  const Categories({
    Key? key,
    required this.cc,
    required this.asProvider,
  }) : super(key: key);

  final cc;
  final asProvider;

  @override
  Widget build(BuildContext context) {
    return Consumer<CategoryService>(
      builder: (context, provider, child) {
        if (provider.categories == null) {
          return const _CategorySkeletonRow();
        }

        if (provider.categories == 'error') {
          return FMScreenState.error(
            title: asProvider.getString('Something went wrong'),
            message: asProvider.getString(
                'We could not load categories right now. Please try again.'),
            actionLabel: asProvider.getString('Retry'),
            onAction: () => provider.fetchCategory(),
          );
        }

        if (provider.categories.category.isEmpty) {
          return FMScreenState.empty(
            title: asProvider.getString('No categories yet'),
            message: asProvider.getString(
                'Categories will appear here when the backend has records.'),
          );
        }

        return SizedBox(
          height: 124,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            shrinkWrap: true,
            clipBehavior: Clip.none,
            itemCount: provider.categories.category.length,
            separatorBuilder: (_, __) => const SizedBox(width: 14),
            itemBuilder: (context, i) => CategoryCard(
              name: provider.categories.category[i].name,
              id: provider.categories.category[i].id,
              cc: cc,
              index: i,
              marginRight: 0.0,
              imagelink: provider.categories.category[i].mobileIcon,
            ),
          ),
        );
      },
    );
  }
}

class _CategorySkeletonRow extends StatelessWidget {
  const _CategorySkeletonRow();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 124,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: 4,
        separatorBuilder: (_, __) => const SizedBox(width: 14),
        itemBuilder: (_, __) => FMSurfaceCard(
          padding: const EdgeInsets.all(14),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: const [
              FMSkeletonBlock(width: 54, height: 54, radius: 27),
              SizedBox(height: 12),
              FMSkeletonBlock(width: 58, height: 12),
            ],
          ),
        ),
      ),
    );
  }
}
