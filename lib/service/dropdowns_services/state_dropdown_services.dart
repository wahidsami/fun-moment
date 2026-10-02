import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/dropdown_models/states_dropdown_model.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class StateDropdownService with ChangeNotifier {
  var statesDropdownList = ['Riyadh'];
  var statesDropdownIndexList = [2];

  final List<String> _allStates = ['Riyadh'];
  final List<dynamic> _allStateIds = [2];

  dynamic selectedState = 'Riyadh';
  dynamic selectedStateId = 2;

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

  setStateDefault() {
    statesDropdownList = ['Riyadh'];
    statesDropdownIndexList = [2];
    selectedState = 'Riyadh';
    selectedStateId = 2;
    hasError = false;
    errorMessage = null;
    currentPage = 1;
    notifyListeners();
  }

  setStatesValue(value) {
    selectedState = value;
    notifyListeners();
  }

  setSelectedStatesId(value) {
    selectedStateId = value;
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

  Future<bool> fetchStates(BuildContext context,
      {bool isrefresh = false}) async {
    if (statesDropdownList.length > 1 && !isrefresh) return false;

    if (isrefresh) {
      statesDropdownList = [];
      statesDropdownIndexList = [];
      currentPage = 1;
    }

    setLoadingTrue();

    var selectedCountryId =
        Provider.of<CountryDropdownService>(context, listen: false)
            .selectedCountryId;

    if (selectedCountryId == defaultId || selectedCountryId == '0' || selectedCountryId == null) {
      selectedCountryId = saudiCountryId;
    }

    try {
      final response = await http
          .get(Uri.parse('$baseApi/country/service-city/$selectedCountryId?page=$currentPage'))
          .timeout(const Duration(seconds: 8));

      setLoadingFalse();

      if ((response.statusCode == 200 || response.statusCode == 201)) {
        final decoded = jsonDecode(response.body);
        final citiesData = decoded['service_cities']?['data'];

        if (citiesData is List && citiesData.isNotEmpty) {
          var data = StatesDropdownModel.fromJson(decoded);

          statesDropdownList.clear();
          statesDropdownIndexList.clear();
          _allStates.clear();
          _allStateIds.clear();

          for (int i = 0; i < data.serviceCities.data.length; i++) {
            final cityName = data.serviceCities.data[i].serviceCity;
            final cityId = data.serviceCities.data[i].id;
            statesDropdownList.add(cityName);
            statesDropdownIndexList.add(cityId);
            _allStates.add(cityName);
            _allStateIds.add(cityId);
          }

          set_State(context, data: data);
          hasError = false;
          notifyListeners();

          currentPage++;
          return true;
        }
      }

      // Default fallback to Riyadh if country is Saudi Arabia
      if (selectedCountryId == saudiCountryId || selectedCountryId == '2') {
        selectedState = 'Riyadh';
        selectedStateId = 2;
        statesDropdownList = ['Riyadh'];
        statesDropdownIndexList = [2];
      }
      notifyListeners();
      return true;
    } catch (e) {
      setLoadingFalse();
      hasError = true;
      errorMessage = 'Could not load cities. Using default.';
      if (selectedCountryId == saudiCountryId || selectedCountryId == '2') {
        selectedState = 'Riyadh';
        selectedStateId = 2;
        statesDropdownList = ['Riyadh'];
        statesDropdownIndexList = [2];
      }
      notifyListeners();
      return false;
    }
  }

  setStateBasedOnUserProfile(BuildContext context) {
    try {
      final profile =
          Provider.of<ProfileService>(context, listen: false).profileDetails;
      if (profile != null &&
          profile.userDetails.city != null &&
          profile.userDetails.city.serviceCity != null) {
        selectedState = profile.userDetails.city.serviceCity;
        selectedStateId = profile.userDetails.city.id ?? 2;
      } else {
        selectedState = 'Riyadh';
        selectedStateId = 2;
      }
    } catch (_) {
      selectedState = 'Riyadh';
      selectedStateId = 2;
    }
  }

  set_State(BuildContext context, {StatesDropdownModel? data}) {
    var profileData =
        Provider.of<ProfileService>(context, listen: false).profileDetails;

    if (profileData != null &&
        profileData.userDetails.city != null &&
        profileData.userDetails.city.serviceCity != null) {
      setStateBasedOnUserProfile(context);
    } else {
      if (data != null && data.serviceCities.data.isNotEmpty) {
        // Prioritize Riyadh if present or first
        int riyadhIdx = data.serviceCities.data
            .indexWhere((c) => c.id == 2 || c.serviceCity.toLowerCase().contains('riyadh'));
        if (riyadhIdx >= 0) {
          selectedState = data.serviceCities.data[riyadhIdx].serviceCity;
          selectedStateId = data.serviceCities.data[riyadhIdx].id;
        } else {
          selectedState = data.serviceCities.data[0].serviceCity;
          selectedStateId = data.serviceCities.data[0].id;
        }
      } else {
        selectedState = 'Riyadh';
        selectedStateId = 2;
      }
    }

    notifyListeners();
  }

  // ================>
  // Bilingual Search State/City
  // ================>
  Future<bool> searchState(BuildContext? context, String searchText,
      {bool isrefresh = false, bool isSearching = false}) async {
    final query = searchText.trim().toLowerCase();

    if (query.isEmpty) {
      statesDropdownList = List.from(_allStates.isNotEmpty ? _allStates : ['Riyadh']);
      statesDropdownIndexList = List.from(_allStateIds.isNotEmpty ? _allStateIds : [2]);
      notifyListeners();
      return true;
    }

    final isRiyadhSearch = query.contains('رياض') || query.contains('riyadh');

    List<String> matched = [];
    List<int> matchedIds = [];
    for (int i = 0; i < _allStates.length; i++) {
      final name = _allStates[i].toLowerCase();
      if (name.contains(query) || (isRiyadhSearch && name.contains('riyadh'))) {
        matched.add(_allStates[i]);
        matchedIds.add(_allStateIds[i]);
      }
    }

    if (matched.isNotEmpty) {
      statesDropdownList = matched;
      statesDropdownIndexList = matchedIds;
      notifyListeners();
      return true;
    }

    if (isRiyadhSearch) {
      statesDropdownList = ['Riyadh'];
      statesDropdownIndexList = [2];
      notifyListeners();
      return true;
    }

    // Try backend search
    try {
      var response = await http
          .get(Uri.parse('$baseApi/city-search?q=$searchText'))
          .timeout(const Duration(seconds: 5));

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          jsonDecode(response.body)['service_cities']['data'].isNotEmpty) {
        var data = StatesDropdownModel.fromJson(jsonDecode(response.body));
        statesDropdownList.clear();
        statesDropdownIndexList.clear();
        for (int i = 0; i < data.serviceCities.data.length; i++) {
          statesDropdownList.add(data.serviceCities.data[i].serviceCity);
          statesDropdownIndexList.add(data.serviceCities.data[i].id);
        }
        notifyListeners();
        return true;
      }
    } catch (_) {}

    statesDropdownList.clear();
    statesDropdownIndexList.clear();
    notifyListeners();
    return false;
  }
}
