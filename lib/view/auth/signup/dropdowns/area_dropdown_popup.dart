import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:pull_to_refresh/pull_to_refresh.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class AreaDropdownPopup extends StatelessWidget {
  const AreaDropdownPopup({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final RefreshController refreshController =
        RefreshController(initialRefresh: true);

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        title: Text(lnProvider.getString('Search area')),
      ),
      body: SmartRefresher(
        controller: refreshController,
        enablePullUp: true,
        enablePullDown: context.watch<AreaDropdownService>().currentPage > 1
            ? false
            : true,
        onRefresh: () async {
          final result =
              await Provider.of<AreaDropdownService>(context, listen: false)
                  .fetchArea(context);
          if (result) {
            refreshController.refreshCompleted();
          } else {
            refreshController.refreshFailed();
          }
        },
        onLoading: () async {
          final result =
              await Provider.of<AreaDropdownService>(context, listen: false)
                  .fetchArea(context);
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
            child: Consumer<AreaDropdownService>(
              builder: (context, p, child) => Column(
                children: [
                  FMTextField(
                    controller: TextEditingController(),
                    label: lnProvider.getString('Search area'),
                    hintText: lnProvider.getString('Search area'),
                    prefixIcon: const Icon(Icons.search_rounded),
                    onChanged: (v) => p.searchArea(context, v, isSearching: true),
                  ),
                  const SizedBox(height: 14),
                  if (p.areaDropdownList.isNotEmpty &&
                      p.areaDropdownList[0] != 'Select Area')
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: p.areaDropdownList.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, i) {
                        return InkWell(
                          onTap: () {
                            p.setAreaValue(p.areaDropdownList[i]);
                            p.setSelectedAreaId(
                              p.areaDropdownIndexList[
                                  p.areaDropdownList.indexOf(p.areaDropdownList[i])],
                            );
                            Navigator.pop(context);
                          },
                          child: FMSurfaceCard(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 14,
                            ),
                            child: Text(
                              lnProvider.getString('${p.areaDropdownList[i]}'),
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        );
                      },
                    )
                  else if (p.areaDropdownList.isNotEmpty)
                    FMScreenState.empty(
                      title: lnProvider.getString('No area found'),
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
