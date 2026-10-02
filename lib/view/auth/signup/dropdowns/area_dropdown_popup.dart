import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:pull_to_refresh/pull_to_refresh.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class AreaDropdownPopup extends StatefulWidget {
  const AreaDropdownPopup({Key? key}) : super(key: key);

  @override
  State<AreaDropdownPopup> createState() => _AreaDropdownPopupState();
}

class _AreaDropdownPopupState extends State<AreaDropdownPopup> {
  late final RefreshController _refreshController;
  late final TextEditingController _searchController;

  @override
  void initState() {
    super.initState();
    _refreshController = RefreshController(initialRefresh: false);
    _searchController = TextEditingController();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final p = Provider.of<AreaDropdownService>(context, listen: false);
      if (p.areaDropdownList.isEmpty) {
        p.fetchArea(context, isrefresh: true);
      }
    });
  }

  @override
  void dispose() {
    _refreshController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        title: Text(lnProvider.getString('Choose area')),
      ),
      body: SmartRefresher(
        controller: _refreshController,
        enablePullUp: true,
        enablePullDown: context.watch<AreaDropdownService>().currentPage > 1
            ? false
            : true,
        onRefresh: () async {
          final result =
              await Provider.of<AreaDropdownService>(context, listen: false)
                  .fetchArea(context, isrefresh: true);
          if (result) {
            _refreshController.refreshCompleted();
          } else {
            _refreshController.refreshFailed();
          }
        },
        onLoading: () async {
          final result =
              await Provider.of<AreaDropdownService>(context, listen: false)
                  .fetchArea(context);
          if (result) {
            _refreshController.loadComplete();
          } else {
            _refreshController.loadNoData();
            Future.delayed(const Duration(seconds: 1), () {
              if (mounted) {
                _refreshController.resetNoData();
              }
            });
          }
        },
        child: SingleChildScrollView(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Consumer<AreaDropdownService>(
              builder: (context, p, child) => Column(
                children: [
                  FMTextField(
                    controller: _searchController,
                    label: lnProvider.getString('Search area'),
                    hintText: lnProvider.getString('Search area'),
                    prefixIcon: const Icon(Icons.search_rounded),
                    onChanged: (v) => p.searchArea(context, v, isSearching: true),
                  ),
                  const SizedBox(height: 14),
                  if (p.isLoading && p.areaDropdownList.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 32),
                      child: Center(
                        child: CircularProgressIndicator(
                          strokeWidth: 2.2,
                          valueColor:
                              AlwaysStoppedAnimation<Color>(FMColors.magenta),
                        ),
                      ),
                    )
                  else if (p.hasError && p.areaDropdownList.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      child: FMScreenState.error(
                        title: lnProvider.getString('Failed to load areas'),
                        message: lnProvider.getString('Please check your connection and try again.'),
                        actionLabel: lnProvider.getString('Retry'),
                        onAction: () => p.fetchArea(context, isrefresh: true),
                      ),
                    )
                  else if (p.areaDropdownList.isNotEmpty &&
                      p.areaDropdownList[0] != 'Select Area')
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: p.areaDropdownList.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, i) {
                        final isSelected = p.selectedArea == p.areaDropdownList[i];
                        return InkWell(
                          onTap: () {
                            final chosenArea = p.areaDropdownList[i];
                            final chosenAreaId = p.areaDropdownIndexList[
                                p.areaDropdownList.indexOf(chosenArea)];

                            p.setAreaValue(chosenArea);
                            p.setSelectedAreaId(chosenAreaId);
                            Navigator.pop(context);
                          },
                          child: FMSurfaceCard(
                            borderColor: isSelected ? FMColors.magenta : FMColors.border,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 14,
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  lnProvider.getString(p.areaDropdownList[i]),
                                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                        fontWeight: isSelected ? FontWeight.w700 : FontWeight.normal,
                                        color: isSelected ? FMColors.magentaLight : FMColors.textPrimary,
                                      ),
                                ),
                                if (isSelected)
                                  const Icon(
                                    Icons.check_circle_rounded,
                                    size: 18,
                                    color: FMColors.magenta,
                                  ),
                              ],
                            ),
                          ),
                        );
                      },
                    )
                  else
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      child: FMScreenState.empty(
                        title: lnProvider.getString('No area found'),
                        message: lnProvider.getString('No operating areas are registered for this city yet.'),
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
