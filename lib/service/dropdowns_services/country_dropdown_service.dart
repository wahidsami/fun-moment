import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:funmoments/model/dropdown_models/country_dropdown_model.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

var defaultId = '0';
const int saudiCountryId = 2;
const String saudiCountryName = 'Saudi Arabia';

class CountryDropdownService with ChangeNotifier {
  var countryDropdownList = [saudiCountryName];
  var countryDropdownIndexList = [saudiCountryId];
  dynamic selectedCountry = saudiCountryName;
  dynamic selectedCountryId = saudiCountryId;

  // Cached master list of all countries
  final List<String> _allCountries = [saudiCountryName];
  final List<dynamic> _allCountryIds = [saudiCountryId];

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

  setCountryValue(value) {
    selectedCountry = value;
    notifyListeners();
  }

  setSelectedCountryId(value) {
    selectedCountryId = value;
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

  void preselectSaudi() {
    selectedCountry = saudiCountryName;
    selectedCountryId = saudiCountryId;
    if (!countryDropdownList.contains(saudiCountryName)) {
      countryDropdownList.insert(0, saudiCountryName);
      countryDropdownIndexList.insert(0, saudiCountryId);
    }
    notifyListeners();
  }

  setDefault() {
    countryDropdownList = [saudiCountryName];
    countryDropdownIndexList = [saudiCountryId];
    selectedCountry = saudiCountryName;
    selectedCountryId = saudiCountryId;
    hasError = false;
    errorMessage = null;
    notifyListeners();
  }

  Future<bool> fetchCountries(BuildContext context,
      {bool isrefresh = false}) async {
    if (countryDropdownList.length > 1 && !isrefresh) return false;

    if (isrefresh) {
      countryDropdownList = [];
      countryDropdownIndexList = [];
      currentPage = 1;
    }

    setLoadingTrue();

    try {
      final response = await http
          .get(Uri.parse('$baseApi/country?page=$currentPage'))
          .timeout(const Duration(seconds: 8));

      setLoadingFalse();

      if ((response.statusCode == 200 || response.statusCode == 201)) {
        final decoded = jsonDecode(response.body);
        final dataList = decoded['countries']?['data'];

        if (dataList is List && dataList.isNotEmpty) {
          var data = CountryDropdownModel.fromJson(decoded);

          countryDropdownList.clear();
          countryDropdownIndexList.clear();
          _allCountries.clear();
          _allCountryIds.clear();

          for (int i = 0; i < data.countries.data.length; i++) {
            final cName = data.countries.data[i].country;
            final cId = data.countries.data[i].id;
            countryDropdownList.add(cName);
            countryDropdownIndexList.add(cId);
            _allCountries.add(cName);
            _allCountryIds.add(cId);
          }

          setCountry(context, data: data);
          hasError = false;
          notifyListeners();

          currentPage++;
          return true;
        }
      }

      // If response empty or not 200, ensure Saudi Arabia remains available
      preselectSaudi();
      return true;
    } catch (e) {
      setLoadingFalse();
      hasError = true;
      errorMessage = 'Could not load countries. Using default.';
      preselectSaudi();
      return false;
    }
  }

  setCountryBasedOnUserProfile(BuildContext context) {
    try {
      final profile =
          Provider.of<ProfileService>(context, listen: false).profileDetails;
      if (profile != null &&
          profile.userDetails.country != null &&
          profile.userDetails.country.country != null) {
        selectedCountry = profile.userDetails.country.country;
        selectedCountryId = profile.userDetails.countryId ?? saudiCountryId;
      } else {
        preselectSaudi();
      }
    } catch (_) {
      preselectSaudi();
    }
    notifyListeners();
  }

  setCountry(BuildContext context, {CountryDropdownModel? data}) {
    var profileData =
        Provider.of<ProfileService>(context, listen: false).profileDetails;
    if (profileData != null &&
        profileData.userDetails.country != null &&
        profileData.userDetails.country.country != null) {
      setCountryBasedOnUserProfile(context);
    } else {
      if (data != null && data.countries.data.isNotEmpty) {
        // Prioritize Saudi Arabia if present
        int saudiIdx = data.countries.data
            .indexWhere((c) => c.id == saudiCountryId || c.country.toLowerCase().contains('saudi'));
        if (saudiIdx >= 0) {
          selectedCountry = data.countries.data[saudiIdx].country;
          selectedCountryId = data.countries.data[saudiIdx].id;
        } else {
          selectedCountry = data.countries.data[0].country;
          selectedCountryId = data.countries.data[0].id;
        }
      } else {
        preselectSaudi();
      }
    }
    notifyListeners();
  }

  // ================>
  // Bilingual Search country
  // ================>
  Future<bool> searchCountry(BuildContext? context, String searchText,
      {bool isrefresh = false, bool isSearching = false}) async {
    final query = searchText.trim().toLowerCase();

    if (query.isEmpty) {
      countryDropdownList = List.from(_allCountries.isNotEmpty ? _allCountries : [saudiCountryName]);
      countryDropdownIndexList = List.from(_allCountryIds.isNotEmpty ? _allCountryIds : [saudiCountryId]);
      notifyListeners();
      return true;
    }

    // Check if query is searching for Saudi Arabia in Arabic or English
    final isSaudiSearch = query.contains('سعود') ||
        query.contains('مملك') ||
        query.contains('عرب') ||
        query.contains('saudi') ||
        query.contains('ksa') ||
        query.contains('arabia');

    // Local client-side filter first
    List<String> matched = [];
    List<int> matchedIds = [];
    for (int i = 0; i < _allCountries.length; i++) {
      final name = _allCountries[i].toLowerCase();
      if (name.contains(query) || (isSaudiSearch && name.contains('saudi'))) {
        matched.add(_allCountries[i]);
        matchedIds.add(_allCountryIds[i]);
      }
    }

    if (matched.isNotEmpty) {
      countryDropdownList = matched;
      countryDropdownIndexList = matchedIds;
      notifyListeners();
      return true;
    }

    if (isSaudiSearch) {
      countryDropdownList = [saudiCountryName];
      countryDropdownIndexList = [saudiCountryId];
      notifyListeners();
      return true;
    }

    // Try backend search with sanitized English query if not Arabic
    try {
      var response = await http
          .get(Uri.parse('$baseApi/country-search?q=$searchText'))
          .timeout(const Duration(seconds: 5));

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          jsonDecode(response.body)['countries']['data'].isNotEmpty) {
        var data = CountryDropdownModel.fromJson(jsonDecode(response.body));
        countryDropdownList.clear();
        countryDropdownIndexList.clear();
        for (int i = 0; i < data.countries.data.length; i++) {
          countryDropdownList.add(data.countries.data[i].country);
          countryDropdownIndexList.add(data.countries.data[i].id);
        }
        notifyListeners();
        return true;
      }
    } catch (_) {}

    // Fallback if no results
    countryDropdownList.clear();
    countryDropdownIndexList.clear();
    notifyListeners();
    return false;
  }
}

