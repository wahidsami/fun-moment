// ignore_for_file: prefer_typing_uninitialized_variables, avoid_print, non_constant_identifier_names, use_build_context_synchronously

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/dropdown_models/area_dropdown_model.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class AreaDropdownService with ChangeNotifier {
  var areaDropdownList = ['Olaya'];
  var areaDropdownIndexList = [2];

  final List<String> _allAreas = ['Olaya'];
  final List<dynamic> _allAreaIds = [2];

  dynamic selectedArea = 'Olaya';
  dynamic selectedAreaId = 2;

  bool isLoading = false;
  bool hasError = false;
  String? errorMessage;

  late int totalPages;
  int currentPage = 1;

  setCurrentPage(newValue) {
    currentPage = newValue;
    notifyListeners();
  }

  setTotalPage(newPageNumber) {
    totalPages = newPageNumber;
    notifyListeners();
  }

  void clearArea() {
    selectedArea = null;
    selectedAreaId = null;
    areaDropdownList.clear();
    areaDropdownIndexList.clear();
    _allAreas.clear();
    _allAreaIds.clear();
    hasError = false;
    errorMessage = null;
    currentPage = 1;
    notifyListeners();
  }

  setAreaDefault() {
    areaDropdownList = ['Olaya'];
    areaDropdownIndexList = [2];
    selectedArea = 'Olaya';
    selectedAreaId = 2;
    hasError = false;
    errorMessage = null;
    currentPage = 1;
    notifyListeners();
  }

  setAreaValue(value) {
    selectedArea = value;
    notifyListeners();
  }

  setSelectedAreaId(value) {
    selectedAreaId = value;
    notifyListeners();
  }

  setLoadingTrue() {
    isLoading = true;
    hasError = false;
    notifyListeners();
  }

  setLoadingFalse() {
    isLoading = false;
    notifyListeners();
  }

  setAreaBasedOnUserProfile(BuildContext context) {
    try {
      final profile =
          Provider.of<ProfileService>(context, listen: false).profileDetails;
      if (profile != null &&
          profile.userDetails.area != null &&
          profile.userDetails.area?.serviceArea != null) {
        selectedArea = profile.userDetails.area?.serviceArea;
        selectedAreaId = profile.userDetails.area?.id ?? 2;
      } else {
        selectedArea = 'Olaya';
        selectedAreaId = 2;
      }
    } catch (_) {
      selectedArea = 'Olaya';
      selectedAreaId = 2;
    }
  }

  Future<bool> fetchArea(BuildContext context, {bool isrefresh = false}) async {
    if (areaDropdownList.length > 1 && !isrefresh) return false;

    if (isrefresh) {
      areaDropdownList = [];
      areaDropdownIndexList = [];
      _allAreas.clear();
      _allAreaIds.clear();
      currentPage = 1;
    }

    setLoadingTrue();

    var selectedCountryId =
        Provider.of<CountryDropdownService>(context, listen: false)
            .selectedCountryId;
    var selectedStateId =
        Provider.of<StateDropdownService>(context, listen: false)
            .selectedStateId;

    if (selectedCountryId == defaultId || selectedCountryId == '0' || selectedCountryId == null) {
      selectedCountryId = saudiCountryId;
    }

    if (selectedStateId == null || selectedStateId == defaultId || selectedStateId == '0') {
      setLoadingFalse();
      notifyListeners();
      return false;
    }

    try {
      final response = await http
          .get(Uri.parse('$baseApi/country/service-city/service-area/$selectedCountryId/$selectedStateId?page=$currentPage'))
          .timeout(const Duration(seconds: 12));

      setLoadingFalse();

      if ((response.statusCode == 200 || response.statusCode == 201)) {
        final decoded = jsonDecode(response.body);
        final areasData = decoded['service_areas']?['data'];

        if (areasData is List && areasData.isNotEmpty) {
          var data = AreaDropdownModel.fromJson(decoded);

          areaDropdownList.clear();
          areaDropdownIndexList.clear();
          _allAreas.clear();
          _allAreaIds.clear();

          for (int i = 0; i < data.serviceAreas.data.length; i++) {
            final areaName = data.serviceAreas.data[i].serviceArea;
            final areaId = data.serviceAreas.data[i].id;
            areaDropdownList.add(areaName);
            areaDropdownIndexList.add(areaId);
            _allAreas.add(areaName);
            _allAreaIds.add(areaId);
          }

          setArea(context, data: data);
          hasError = false;
          notifyListeners();

          currentPage++;
          return true;
        }
      }

      // If no areas exist for this city
      if (selectedStateId == 2 || selectedStateId == '2') {
        // Riyadh fallback
        selectedArea = 'Olaya';
        selectedAreaId = 2;
        areaDropdownList = ['Olaya'];
        areaDropdownIndexList = [2];
      } else {
        selectedArea = null;
        selectedAreaId = null;
        areaDropdownList.clear();
        areaDropdownIndexList.clear();
        _allAreas.clear();
        _allAreaIds.clear();
      }
      notifyListeners();
      return true;
    } catch (e) {
      setLoadingFalse();
      hasError = true;
      errorMessage = 'Could not load areas.';
      if (selectedStateId == 2 || selectedStateId == '2') {
        selectedArea = 'Olaya';
        selectedAreaId = 2;
        areaDropdownList = ['Olaya'];
        areaDropdownIndexList = [2];
      } else {
        selectedArea = null;
        selectedAreaId = null;
        areaDropdownList.clear();
        areaDropdownIndexList.clear();
        _allAreas.clear();
        _allAreaIds.clear();
      }
      notifyListeners();
      return false;
    }
  }

  setArea(BuildContext context, {AreaDropdownModel? data}) {
    var profileData =
        Provider.of<ProfileService>(context, listen: false).profileDetails;

    if (profileData != null &&
        profileData.userDetails.area != null &&
        profileData.userDetails.area?.serviceArea != null) {
      setAreaBasedOnUserProfile(context);
    } else {
      if (data != null && data.serviceAreas.data.isNotEmpty) {
        int olayaIdx = data.serviceAreas.data
            .indexWhere((a) => a.id == 2 || a.serviceArea.toLowerCase().contains('olaya'));
        if (olayaIdx >= 0) {
          selectedArea = data.serviceAreas.data[olayaIdx].serviceArea;
          selectedAreaId = data.serviceAreas.data[olayaIdx].id;
        } else {
          selectedArea = data.serviceAreas.data[0].serviceArea;
          selectedAreaId = data.serviceAreas.data[0].id;
        }
      } else {
        selectedArea = null;
        selectedAreaId = null;
      }
    }

    notifyListeners();
  }

  // ================>
  // Bilingual Search Area
  // ================>
  Future<bool> searchArea(BuildContext? context, String searchText,
      {bool isrefresh = false, bool isSearching = false}) async {
    final query = searchText.trim().toLowerCase();

    if (query.isEmpty) {
      areaDropdownList = List.from(_allAreas);
      areaDropdownIndexList = List.from(_allAreaIds);
      notifyListeners();
      return true;
    }

    final isOlayaSearch = query.contains('عليا') || query.contains('olaya');

    List<String> matched = [];
    List<int> matchedIds = [];
    for (int i = 0; i < _allAreas.length; i++) {
      final name = _allAreas[i].toLowerCase();
      if (name.contains(query) || (isOlayaSearch && name.contains('olaya'))) {
        matched.add(_allAreas[i]);
        matchedIds.add(_allAreaIds[i]);
      }
    }

    if (matched.isNotEmpty) {
      areaDropdownList = matched;
      areaDropdownIndexList = matchedIds;
      notifyListeners();
      return true;
    }

    if (isOlayaSearch && _allAreas.contains('Olaya')) {
      areaDropdownList = ['Olaya'];
      areaDropdownIndexList = [2];
      notifyListeners();
      return true;
    }

    areaDropdownList.clear();
    areaDropdownIndexList.clear();
    notifyListeners();
    return false;
  }
}
