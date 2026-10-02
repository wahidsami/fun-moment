import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/categoryModel.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/provider_registration_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class CategoryMultiSelect extends StatefulWidget {
  const CategoryMultiSelect({Key? key}) : super(key: key);

  @override
  State<CategoryMultiSelect> createState() => _CategoryMultiSelectState();
}

class _CategoryMultiSelectState extends State<CategoryMultiSelect> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final catService = Provider.of<CategoryService>(context, listen: false);
      if (catService.categories == null || catService.categories == 'error') {
        catService.fetchCategory();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final asProvider = Provider.of<AppStringService>(context);
    final rtl = Provider.of<RtlService>(context);
    final isArabic = rtl.direction == 'rtl';

    return Consumer<ProviderRegistrationService>(
      builder: (context, provider, child) {
        final error = provider.fieldErrors['category_ids'];

        return Consumer<CategoryService>(
          builder: (context, catService, child) {
            final categories = catService.categoriesDropdownList;
            final bool isLoading = catService.isFetching || (catService.categories == null && categories.isEmpty);

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      asProvider.getString("Offered Service Categories"),
                      style: const TextStyle(
                        color: FMColors.textPrimary,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(width: 4),
                    const Text(
                      '*',
                      style: TextStyle(color: FMColors.magenta, fontWeight: FontWeight.bold),
                    ),
                    const Spacer(),
                    if (provider.model.categoryIds.isNotEmpty)
                      Text(
                        '${provider.model.categoryIds.length} ${asProvider.getString("selected")}',
                        style: const TextStyle(color: FMColors.cyan, fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  asProvider.getString("Select all services you can provide"),
                  style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                ),
                const SizedBox(height: 10),
                if (isLoading) ...[
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Center(
                      child: CircularProgressIndicator(color: FMColors.cyan, strokeWidth: 2),
                    ),
                  ),
                ] else if (categories.isEmpty) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: FMColors.surface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: FMColors.border),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          asProvider.getString("Unable to load categories"),
                          style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                        ),
                        TextButton(
                          onPressed: () => catService.fetchCategory(isRefresh: true),
                          child: Text(asProvider.getString("Retry"), style: const TextStyle(color: FMColors.cyan)),
                        ),
                      ],
                    ),
                  ),
                ] else ...[
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: categories.map<Widget>((cat) {
                      final catId = cat.id is int ? cat.id : int.tryParse(cat.id?.toString() ?? '') ?? 0;
                      final isSelected = provider.model.categoryIds.contains(catId);
                      final catName = isArabic && (cat.nameAr != null && cat.nameAr!.isNotEmpty)
                          ? cat.nameAr!
                          : (cat.name ?? '');

                      return FilterChip(
                        selected: isSelected,
                        label: Text(
                          catName,
                          style: TextStyle(
                            color: isSelected ? Colors.white : FMColors.textPrimary,
                            fontSize: 12,
                            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                          ),
                        ),
                        selectedColor: FMColors.cyan,
                        backgroundColor: FMColors.surface,
                        checkmarkColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                          side: BorderSide(
                            color: isSelected ? FMColors.cyan : FMColors.border,
                            width: isSelected ? 1.5 : 1,
                          ),
                        ),
                        onSelected: (selected) {
                          provider.toggleCategoryId(catId);
                        },
                      );
                    }).toList(),
                  ),
                ],
                if (error != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    error,
                    style: const TextStyle(color: FMColors.magenta, fontSize: 11),
                  ),
                ],
              ],
            );
          },
        );
      },
    );
  }
}
