import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:pull_to_refresh/pull_to_refresh.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/searchbar_with_dropdown_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class StateDropdownPopup extends StatelessWidget {
  const StateDropdownPopup({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final RefreshController refreshController =
        RefreshController(initialRefresh: true);

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        title: Text(lnProvider.getString('Search state')),
      ),
      body: SmartRefresher(
        controller: refreshController,
        enablePullUp: true,
        enablePullDown: context.watch<StateDropdownService>().currentPage > 1
            ? false
            : true,
        onRefresh: () async {
          final result =
              await Provider.of<StateDropdownService>(context, listen: false)
                  .fetchStates(context);
          if (result) {
            refreshController.refreshCompleted();
          } else {
            refreshController.refreshFailed();
          }
        },
        onLoading: () async {
          final result =
              await Provider.of<StateDropdownService>(context, listen: false)
                  .fetchStates(context);
          if (result) {
            refreshController.loadComplete();
          } else {
            refreshController.loadNoData();
            Future.delayed(const Duration(seconds: 1), () {
              refreshController.resetNoData();
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
                    controller: TextEditingController(),
                    label: lnProvider.getString('Search state'),
                    hintText: lnProvider.getString('Search state'),
                    prefixIcon: const Icon(Icons.search_rounded),
                    onChanged: (v) => p.searchState(context, v, isSearching: true),
                  ),
                  const SizedBox(height: 14),
                  if (p.statesDropdownList.isNotEmpty &&
                      p.statesDropdownList[0] != 'Select City')
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: p.statesDropdownList.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, i) {
                        return InkWell(
                          onTap: () {
                            p.setStatesValue(p.statesDropdownList[i]);
                            p.setSelectedStatesId(
                              p.statesDropdownIndexList[
                                  p.statesDropdownList.indexOf(
                                      p.statesDropdownList[i])],
                            );
                            Navigator.pop(context);
                            Provider.of<AreaDropdownService>(context,
                                    listen: false)
                                .setAreaDefault();
                            final sProvider = Provider.of<
                                    SearchBarWithDropdownService>(
                                context,
                                listen: false);
                            sProvider.setCityValue(p.selectedState);
                            sProvider.setSelectedCityId(p.selectedStateId);
                            sProvider.fetchService(context);
                          },
                          child: FMSurfaceCard(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 14,
                            ),
                            child: Text(
                              lnProvider.getString('${p.statesDropdownList[i]}'),
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        );
                      },
                    )
                  else if (p.statesDropdownList.isNotEmpty)
                    FMScreenState.empty(
                      title: lnProvider.getString('No city found'),
                      message: '',
                    )
                  else
                    const Center(
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        valueColor:
                            AlwaysStoppedAnimation<Color>(FMColors.magenta),
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
