import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/dropdown_models/states_dropdown_model.dart';
import 'package:funmoments/model/dropdown_models/area_dropdown_model.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/view/utils/app_strings.dart';
import 'package:funmoments/view/utils/saudi_locations_strings.dart';

void main() {
  group('Saudi Arabia Nationwide Location Expansion Tests', () {
    test('1. Authoritative Saudi country lookup defaults to Saudi Arabia (ID 2)', () {
      final countryService = CountryDropdownService();
      expect(countryService.selectedCountryId, 2);
      expect(countryService.selectedCountry, 'Saudi Arabia');
      expect(countryService.countryDropdownList, contains('Saudi Arabia'));
      expect(countryService.countryDropdownIndexList, contains(2));
    });

    test('2. Multi-city payload parsing supports nationwide Saudi cities', () {
      final stateService = StateDropdownService();

      final mockCitiesJson = {
        'service_cities': {
          'current_page': 1,
          'data': [
            {'id': 2, 'service_city': 'Riyadh'},
            {'id': 83, 'service_city': 'Jeddah'},
            {'id': 86, 'service_city': 'Makkah'},
            {'id': 72, 'service_city': 'Madinah'},
            {'id': 39, 'service_city': 'Dammam'},
            {'id': 31, 'service_city': 'Al Khobar'},
            {'id': 3, 'service_city': 'Abha'},
            {'id': 151, 'service_city': 'Tabuk'},
            {'id': 65, 'service_city': 'Jazan'},
            {'id': 92, 'service_city': 'Najran'},
          ],
          'total': 10
        }
      };

      final model = StatesDropdownModel.fromJson(mockCitiesJson);
      expect(model.serviceCities.data.length, 10);

      stateService.statesDropdownList.clear();
      stateService.statesDropdownIndexList.clear();
      for (final item in model.serviceCities.data) {
        stateService.statesDropdownList.add(item.serviceCity);
        stateService.statesDropdownIndexList.add(item.id);
      }

      expect(stateService.statesDropdownList, containsAll([
        'Riyadh', 'Jeddah', 'Makkah', 'Madinah', 'Dammam',
        'Al Khobar', 'Abha', 'Tabuk', 'Jazan', 'Najran'
      ]));
      expect(stateService.statesDropdownIndexList, containsAll([2, 83, 86, 72, 39, 31, 3, 151, 65, 92]));
    });

    test('3. Area payload parsing maps specifically to selected city without crosstalk', () {
      final areaService = AreaDropdownService();

      // Mock Jeddah districts
      final jeddahAreasJson = {
        'service_areas': {
          'current_page': 1,
          'data': [
            {'id': 1001, 'service_area': 'Al Hamra', 'service_city_id': 83},
            {'id': 1002, 'service_area': 'Al Rawdah', 'service_city_id': 83},
            {'id': 1003, 'service_area': 'Al Zahra', 'service_city_id': 83},
          ],
          'links': <dynamic>[],
          'total': 3
        }
      };

      final model = AreaDropdownModel.fromJson(jeddahAreasJson);
      areaService.setInitialAreas([], []);
      for (final item in model.serviceAreas.data) {
        areaService.areaDropdownList.add(item.serviceArea);
        areaService.areaDropdownIndexList.add(item.id);
      }

      expect(areaService.areaDropdownList, containsAll(['Al Hamra', 'Al Rawdah', 'Al Zahra']));
      expect(areaService.areaDropdownList, isNot(contains('Olaya')));
    });

    test('4. Changing city resets and clears previously selected area', () {
      final stateService = StateDropdownService();
      final areaService = AreaDropdownService();

      // Initial state: Riyadh + Olaya
      stateService.setStatesValue('Riyadh');
      stateService.setSelectedStatesId(2);
      areaService.setAreaValue('Olaya');
      areaService.setSelectedAreaId(2);

      expect(stateService.selectedState, 'Riyadh');
      expect(areaService.selectedArea, 'Olaya');

      // User changes city to Jeddah
      stateService.setStatesValue('Jeddah');
      stateService.setSelectedStatesId(83);
      areaService.clearArea();

      // Area must now be completely reset
      expect(stateService.selectedState, 'Jeddah');
      expect(stateService.selectedStateId, 83);
      expect(areaService.selectedArea, isNull);
      expect(areaService.selectedAreaId, isNull);
      expect(areaService.areaDropdownList, isEmpty);
      expect(areaService.areaDropdownIndexList, isEmpty);
    });

    test('5. Bilingual search matches Arabic queries for Saudi cities', () async {
      final stateService = StateDropdownService();
      
      // Inject Saudi sample cities into service using setInitialStates
      stateService.setInitialStates(
        ['Riyadh', 'Jeddah', 'Makkah', 'Madinah', 'Dammam', 'Al Khobar'],
        [2, 83, 86, 72, 39, 31],
      );

      // Search in Arabic for Jeddah: "جدة"
      await stateService.searchState(null, 'جدة');
      expect(stateService.statesDropdownList, contains('Jeddah'));

      // Search in Arabic for Dammam: "الدمام"
      await stateService.searchState(null, 'الدمام');
      expect(stateService.statesDropdownList, contains('Dammam'));

      // Search in Arabic for Khobar: "الخبر"
      await stateService.searchState(null, 'الخبر');
      expect(stateService.statesDropdownList, contains('Al Khobar'));

      // Search in English for Makkah: "makkah"
      await stateService.searchState(null, 'makkah');
      expect(stateService.statesDropdownList, contains('Makkah'));
    });

    test('6. Bilingual search matches Arabic queries for city areas', () async {
      final areaService = AreaDropdownService();

      areaService.setInitialAreas(
        ['Olaya', 'Al Malaz', 'Al Murabba', 'Al Nakheel'],
        [2, 201, 202, 203],
      );

      // Search in Arabic for Olaya: "العليا"
      await areaService.searchArea(null, 'العليا');
      expect(areaService.areaDropdownList, contains('Olaya'));

      // Search in Arabic for Malaz: "الملز"
      await areaService.searchArea(null, 'الملز');
      expect(areaService.areaDropdownList, contains('Al Malaz'));

      // Search in English for Nakheel
      await areaService.searchArea(null, 'nakheel');
      expect(areaService.areaDropdownList, contains('Al Nakheel'));
    });

    test('7. Saudi dictionary covers cities across all 13 administrative regions', () {
      final sampleRegionCities = {
        'Riyadh': 'الرياض',           // Riyadh Region
        'Jeddah': 'جدة',             // Makkah Region
        'Madinah': 'المدينة المنورة', // Madinah Region
        'Dammam': 'الدمام',          // Eastern Province
        'Buraidah': 'بريدة',         // Al Qassim
        'Abha': 'ابها',              // Asir
        'Tabuk': 'تبوك',             // Tabuk
        'Hail': 'حائل',              // Hail
        'Arar': 'عرعر',              // Northern Borders
        'Jazan': 'جازان',            // Jazan
        'Najran': 'نجران',           // Najran
        'Al Bahah': 'الباحة',        // Al Bahah
        'Sakaka': 'سكاكا',           // Al Jouf
      };

      for (final entry in sampleRegionCities.entries) {
        expect(
          saudiLocationStrings[entry.key],
          isNotNull,
          reason: 'City ${entry.key} must exist in Saudi location dictionary',
        );
        expect(
          translations[entry.key],
          isNotNull,
          reason: 'City ${entry.key} must be mapped in active translations',
        );
      }
    });
  });
}
