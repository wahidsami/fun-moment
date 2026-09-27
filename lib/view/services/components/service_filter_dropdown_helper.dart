import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/all_services_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

import '../../utils/others_helper.dart';

class ServiceFilterDropdownHelper {
  //category dropdown
  categoryDropdown(cc, BuildContext context) {
    Provider.of<AllServicesService>(context, listen: false)
        .fetchCategories(context);
    return Consumer<AllServicesService>(
      builder: (context, provider, child) => provider
              .categoryDropdownList.isNotEmpty
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CommonHelper().labelCommon("Category"),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 15),
                  decoration: BoxDecoration(
                    color: FMColors.inputSurface,
                    border: Border.all(color: FMColors.border),
                    borderRadius: BorderRadius.circular(FMRadii.md),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      dropdownColor: FMColors.surfaceElevated,
                      isExpanded: true,
                      value: provider.selectedCategory,
                      icon: const Icon(Icons.keyboard_arrow_down_rounded,
                          color: FMColors.magenta),
                      iconSize: 26,
                      elevation: 4,
                      style: const TextStyle(color: FMColors.textPrimary, fontSize: 14),
                      onChanged: (newValue) {
                        provider.setCategoryValue(newValue);

                        //setting the id of selected value
                        provider.setSelectedCategoryId(provider
                                .categoryDropdownIndexList[
                            provider.categoryDropdownList.indexOf(newValue!)]);

                        provider.setEverythingToDefault();
                        provider.fetchSubcategory(provider.selectedCategoryId);
                        //fetch service
                        provider.fetchServiceByFilter(context);
                      },
                      items: provider.categoryDropdownList
                          .map<DropdownMenuItem<String>>((value) {
                        return DropdownMenuItem(
                          value: value,
                          child: Text(
                            value,
                            style: const TextStyle(
                                color: FMColors.textPrimary),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                )
              ],
            )
          : Row(
              mainAxisAlignment: MainAxisAlignment.start,
              children: [OthersHelper().showLoading(cc.primaryColor)],
            ),
    );
  }

  //sub category dropdown =======>
  subCategoryDropdown(cc, BuildContext context) {
    return Consumer<AllServicesService>(
      builder: (context, provider, child) =>
          provider.subcatDropdownList.isNotEmpty
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CommonHelper().labelCommon("Sub Category"),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(horizontal: 15),
                      decoration: BoxDecoration(
                        color: FMColors.inputSurface,
                        border: Border.all(color: FMColors.border),
                        borderRadius: BorderRadius.circular(FMRadii.md),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          dropdownColor: FMColors.surfaceElevated,
                          isExpanded: true,
                          value: provider.selectedSubcat,
                          icon: const Icon(Icons.keyboard_arrow_down_rounded,
                              color: FMColors.magenta),
                          iconSize: 26,
                          elevation: 4,
                          style: const TextStyle(color: FMColors.textPrimary, fontSize: 14),
                          onChanged: (newValue) {
                            provider.setSubcatValue(newValue);

                            //setting the id of selected value
                            provider.setSelectedSubcatsId(
                                provider.subcatDropdownIndexList[provider
                                    .subcatDropdownList
                                    .indexOf(newValue!)]);

                            //fetch service
                            provider.setEverythingToDefault();
                            provider.fetchServiceByFilter(context);
                          },
                          items: provider.subcatDropdownList
                              .map<DropdownMenuItem<String>>((value) {
                            return DropdownMenuItem(
                              value: value,
                              child: Text(
                                value,
                                style: const TextStyle(
                                    color: FMColors.textPrimary),
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    )
                  ],
                )
              : Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [OthersHelper().showLoading(FMColors.magenta)],
                ),
    );
  }

  //rating dropdown =======>
  ratingDropdown(cc, BuildContext context) {
    return Consumer<AllServicesService>(
      builder: (context, provider, child) => provider
              .ratingDropdownList.isNotEmpty
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CommonHelper().labelCommon("Ratings"),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 15),
                  decoration: BoxDecoration(
                    color: FMColors.inputSurface,
                    border: Border.all(color: FMColors.border),
                    borderRadius: BorderRadius.circular(FMRadii.md),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      dropdownColor: FMColors.surfaceElevated,
                      isExpanded: true,
                      value: provider.selectedRating,
                      icon: const Icon(Icons.keyboard_arrow_down_rounded,
                          color: FMColors.magenta),
                      iconSize: 26,
                      elevation: 4,
                      style: const TextStyle(color: FMColors.textPrimary, fontSize: 14),
                      onChanged: (newValue) {
                        provider.setRatingValue(newValue);

                        //setting the id of selected value
                        provider.setSelectedRatingId(provider
                                .ratingDropdownIndexList[
                            provider.ratingDropdownList.indexOf(newValue!)]);

                        //fetch states based on selected country
                        provider.setEverythingToDefault();
                        //fetch service
                        provider.fetchServiceByFilter(context);
                      },
                      items: provider.ratingDropdownList
                          .map<DropdownMenuItem<String>>((value) {
                        return DropdownMenuItem(
                          value: value,
                          child: Text(
                            value
                                .replaceAll(
                                    "Star", lnProvider.getString("Star"))
                                .replaceAll("All", lnProvider.getString("All")),
                            style: const TextStyle(
                                color: FMColors.textPrimary),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                )
              ],
            )
          : Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [OthersHelper().showLoading(FMColors.magenta)],
            ),
    );
  }

  //sort by dropdown =======>
  sortByDropdown(cc, BuildContext context) {
    return Consumer<AllServicesService>(
      builder: (context, provider, child) =>
          provider.sortbyDropdownList.isNotEmpty
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CommonHelper().labelCommon("Sort By"),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(horizontal: 15),
                      decoration: BoxDecoration(
                        color: FMColors.inputSurface,
                        border: Border.all(color: FMColors.border),
                        borderRadius: BorderRadius.circular(FMRadii.md),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          dropdownColor: FMColors.surfaceElevated,
                          isExpanded: true,
                          value: provider.selectedSortby,
                          icon: const Icon(Icons.keyboard_arrow_down_rounded,
                              color: FMColors.magenta),
                          iconSize: 26,
                          elevation: 4,
                          style: const TextStyle(color: FMColors.textPrimary, fontSize: 14),
                          onChanged: (newValue) {
                            provider.setSortbyValue(newValue);

                            //setting the id of selected value
                            provider.setSelectedSortbyId(
                                provider.sortbyDropdownIndexList[provider
                                    .sortbyDropdownList
                                    .indexOf(newValue!)]);

                            //fetch states based on selected country
                            provider.setEverythingToDefault();
                            //fetch service
                            provider.fetchServiceByFilter(context);
                          },
                          items: provider.sortbyDropdownList
                              .map<DropdownMenuItem<String>>((value) {
                            return DropdownMenuItem(
                              value: value,
                              child: Text(
                                lnProvider.getString(value),
                                style: const TextStyle(
                                    color: FMColors.textPrimary),
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    )
                  ],
                )
              : Container(),
    );
  }
}
