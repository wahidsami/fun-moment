import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:pull_to_refresh/pull_to_refresh.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/searchbar_with_dropdown_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class StateDropdownPopup extends StatefulWidget {
  const StateDropdownPopup({Key? key}) : super(key: key);

  @override
  State<StateDropdownPopup> createState() => _StateDropdownPopupState();
}

class _StateDropdownPopupState extends State<StateDropdownPopup> {
  late final RefreshController _refreshController;
  late final TextEditingController _searchController;

  @override
  void initState() {
    super.initState();
    _refreshController = RefreshController(initialRefresh: false);
    _searchController = TextEditingController();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final p = Provider.of<StateDropdownService>(context, listen: false);
      if (p.statesDropdownList.isEmpty || p.statesDropdownList.length <= 1) {
        p.fetchStates(context, isrefresh: true);
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
        title: Text(lnProvider.getString('Choose city')),
      ),
      body: SmartRefresher(
        controller: _refreshController,
        enablePullUp: true,
        enablePullDown: context.watch<StateDropdownService>().currentPage > 1
            ? false
            : true,
        onRefresh: () async {
          final result =
              await Provider.of<StateDropdownService>(context, listen: false)
                  .fetchStates(context, isrefresh: true);
          if (result) {
            _refreshController.refreshCompleted();
          } else {
            _refreshController.refreshFailed();
          }
        },
        onLoading: () async {
          final result =
              await Provider.of<StateDropdownService>(context, listen: false)
                  .fetchStates(context);
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
            child: Consumer<StateDropdownService>(
              builder: (context, p, child) => Column(
                children: [
                  FMTextField(
                    controller: _searchController,
                    label: lnProvider.getString('Search city'),
                    hintText: lnProvider.getString('Search city'),
                    prefixIcon: const Icon(Icons.search_rounded),
                    onChanged: (v) => p.searchState(context, v, isSearching: true),
                  ),
                  const SizedBox(height: 14),
                  if (p.isLoading && p.statesDropdownList.isEmpty)
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
                  else if (p.hasError && p.statesDropdownList.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      child: FMScreenState.error(
                        title: lnProvider.getString('Failed to load cities'),
                        message: lnProvider.getString('Please check your connection and try again.'),
                        actionLabel: lnProvider.getString('Retry'),
                        onAction: () => p.fetchStates(context, isrefresh: true),
                      ),
                    )
                  else if (p.statesDropdownList.isNotEmpty &&
                      p.statesDropdownList[0] != 'Select City')
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: p.statesDropdownList.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, i) {
                        final isSelected = p.selectedState == p.statesDropdownList[i];
                        return InkWell(
                          onTap: () {
                            final chosenCity = p.statesDropdownList[i];
                            final chosenCityId = p.statesDropdownIndexList[
                                p.statesDropdownList.indexOf(chosenCity)];

                            p.setStatesValue(chosenCity);
                            p.setSelectedStatesId(chosenCityId);
                            Navigator.pop(context);

                            // Cascade to Area: clear previous area and load areas for new city
                            final areaProv = Provider.of<AreaDropdownService>(context,
                                listen: false);
                            areaProv.clearArea();
                            areaProv.fetchArea(context, isrefresh: true);

                            try {
                              final sProvider = Provider.of<
                                      SearchBarWithDropdownService>(
                                  context,
                                  listen: false);
                              sProvider.setCityValue(chosenCity);
                              sProvider.setSelectedCityId(chosenCityId);
                            } catch (_) {}
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
                                  lnProvider.getString(p.statesDropdownList[i]),
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
                        title: lnProvider.getString('No city found'),
                        message: '',
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
