import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/booking_services/book_service.dart';
import 'package:funmoments/view/utils/app_strings.dart';

void main() {
  group('Booking Location & Address Flow Tests', () {
    test('Saudi country is preselected by default', () {
      final countryService = CountryDropdownService();
      // Should default to Saudi Arabia ID 2
      expect(countryService.selectedCountryId.toString(), '2');
      expect(countryService.selectedCountry, 'Saudi Arabia');
      expect(countryService.countryDropdownList.contains('Saudi Arabia'), isTrue);
      expect(countryService.countryDropdownIndexList.contains(2), isTrue);
    });

    test('Saudi country search supports both Arabic and English queries', () {
      final countryService = CountryDropdownService();
      
      // Arabic search query for Saudi Arabia
      countryService.searchCountry(null, 'السعودية');
      expect(countryService.countryDropdownList.contains('Saudi Arabia'), isTrue);
      expect(countryService.countryDropdownIndexList.contains(2), isTrue);

      // English search query
      countryService.searchCountry(null, 'Saudi');
      expect(countryService.countryDropdownList.contains('Saudi Arabia'), isTrue);
    });

    test('City service initializes with Riyadh as Saudi-first default', () {
      final stateService = StateDropdownService();
      expect(stateService.selectedStateId.toString(), '2');
      expect(stateService.selectedState, 'Riyadh');
      expect(stateService.statesDropdownList.contains('Riyadh'), isTrue);
      expect(stateService.statesDropdownIndexList.contains(2), isTrue);
    });

    test('City search handles Arabic "الرياض" and English "Riyadh"', () {
      final stateService = StateDropdownService();
      
      stateService.searchState(null, 'الرياض');
      expect(stateService.statesDropdownList.contains('Riyadh'), isTrue);
      expect(stateService.statesDropdownIndexList.contains(2), isTrue);

      stateService.searchState(null, 'Riyadh');
      expect(stateService.statesDropdownList.contains('Riyadh'), isTrue);
    });

    test('Area service defaults to Olaya and supports Arabic/English search', () {
      final areaService = AreaDropdownService();
      expect(areaService.selectedAreaId.toString(), '2');
      expect(areaService.selectedArea, 'Olaya');
      expect(areaService.areaDropdownList.contains('Olaya'), isTrue);

      // Arabic search
      areaService.searchArea(null, 'العليا');
      expect(areaService.areaDropdownList.contains('Olaya'), isTrue);
      expect(areaService.areaDropdownIndexList.contains(2), isTrue);

      // English search
      areaService.searchArea(null, 'Olaya');
      expect(areaService.areaDropdownList.contains('Olaya'), isTrue);
    });

    test('Bilingual strings dictionary contains all Saudi location and address keys', () {
      expect(translations['Saudi Arabia'], 'المملكة العربية السعودية');
      expect(translations['Riyadh'], 'الرياض');
      expect(translations['Olaya'], 'العليا');
      expect(translations['Building number'], 'رقم المبنى');
      expect(translations['Street address'], 'عنوان الشارع');
      expect(translations['Selected Location'], 'الموقع المحدد');
      expect(translations['Additional details / Notes'], 'تفاصيل إضافية / ملاحظات');
      
      expect(appStrings['Saudi Arabia'], 'Saudi Arabia');
      expect(appStrings['Riyadh'], 'Riyadh');
      expect(appStrings['Olaya'], 'Olaya');
      expect(appStrings['Building number'], 'Building number');
    });

    test('BookService preserves full address, building number, and order note', () {
      final bookService = BookService();
      
      const name = 'Ahmed Al-Ghamdi';
      const email = 'ahmed@example.sa';
      const phone = '0501234567';
      const postCode = '12211';
      const street = 'King Fahd Road';
      const building = '1234';
      const fullAddress = '$street, مبنى $building';
      const note = 'Gate 3, 2nd floor';

      bookService.setAddress(name, email, phone, postCode, fullAddress, note);

      expect(bookService.name, name);
      expect(bookService.email, email);
      expect(bookService.phone, phone);
      expect(bookService.postCode, postCode);
      expect(bookService.address, fullAddress);
      expect(bookService.orderNote, note);
    });
  });
}
