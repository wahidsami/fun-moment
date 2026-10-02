import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:pull_to_refresh/pull_to_refresh.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/searchbar_with_dropdown_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class CountryDropdownPopup extends StatefulWidget {
  const CountryDropdownPopup({Key? key}) : super(key: key);

  @override
  State<CountryDropdownPopup> createState() => _CountryDropdownPopupState();
}

class _CountryDropdownPopupState extends State<CountryDropdownPopup> {
  late final RefreshController _refreshController;
  late final TextEditingController _searchController;

  @override
  void initState() {
    super.initState();
    _refreshController = RefreshController(initialRefresh: false);
    _searchController = TextEditingController();
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
        title: Text(lnProvider.getString('Search country')),
      ),
      body: SmartRefresher(
        controller: _refreshController,
        enablePullUp: true,
        enablePullDown: context.watch<CountryDropdownService>().currentPage > 1
            ? false
            : true,
        onRefresh: () async {
          final result =
              await Provider.of<CountryDropdownService>(context, listen: false)
                  .fetchCountries(context, isrefresh: true);
          if (result) {
            _refreshController.refreshCompleted();
          } else {
            _refreshController.refreshFailed();
          }
        },
        onLoading: () async {
          final result =
              await Provider.of<CountryDropdownService>(context, listen: false)
                  .fetchCountries(context);
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
            child: Consumer<CountryDropdownService>(
              builder: (context, p, child) => Column(
                children: [
                  FMTextField(
                    controller: _searchController,
                    label: lnProvider.getString('Search country'),
                    hintText: lnProvider.getString('Search country'),
                    prefixIcon: const Icon(Icons.search_rounded),
                    onChanged: (v) => p.searchCountry(context, v, isSearching: true),
                  ),
                  const SizedBox(height: 14),
                  if (p.countryDropdownList.isNotEmpty &&
                      p.countryDropdownList[0] != 'Select Country')
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: p.countryDropdownList.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, i) {
                        return InkWell(
                          onTap: () {
                            p.setCountryValue(p.countryDropdownList[i]);
                            p.setSelectedCountryId(
                              p.countryDropdownIndexList[p.countryDropdownList.indexOf(
                                  p.countryDropdownList[i])],
                            );
                            Navigator.pop(context);
                            Provider.of<StateDropdownService>(context,
                                    listen: false)
                                .setStateDefault();
                            try {
                              Provider.of<SearchBarWithDropdownService>(context,
                                      listen: false)
                                  .fetchService(context);
                            } catch (_) {}
                          },
                          child: FMSurfaceCard(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 14,
                            ),
                            child: Text(
                              lnProvider.getString(p.countryDropdownList[i]),
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        );
                      },
                    )
                  else
                    FMScreenState.empty(
                      title: lnProvider.getString('No country found'),
                      message: '',
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
