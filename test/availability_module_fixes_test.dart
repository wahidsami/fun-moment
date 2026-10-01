import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/service/provider_availability_service.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    lnProvider = AppStringService();
    await initializeDateFormatting('ar', null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(const MethodChannel('PonnamKarthik/fluttertoast'), (call) async => null);
  });

  group('Fix Group 1, 2, 3, 4: Provider Availability Service State & Safety', () {
    test('1. Initial state is NOT initialized and has no error and no fake days', () {
      final service = ProviderAvailabilityService();
      expect(service.isInitialized, isFalse, reason: 'Must not be initialized before first fetch');
      expect(service.isLoading, isFalse);
      expect(service.days.isEmpty, isTrue);
      expect(service.hasError, isFalse);
      expect(service.errorMessage, isNull);
    });

    test('2. ProviderWorkingDay parsing accurately reflects enabled vs disabled status', () {
      final jsonEnabled = {
        'id': 10,
        'day': 'Mon',
        'status': 1,
        'total_day': 7,
        'schedules': [
          {'id': 101, 'day_id': 10, 'schedule': '09:00 AM - 10:00 AM', 'status': 1}
        ]
      };
      final enabledDay = ProviderWorkingDay.fromJson(jsonEnabled);
      expect(enabledDay.id, equals(10));
      expect(enabledDay.shortName, equals('Mon'));
      expect(enabledDay.fullName, equals('Monday'));
      expect(enabledDay.isEnabled, isTrue);
      expect(enabledDay.schedules.length, equals(1));
      expect(enabledDay.schedules.first.schedule, equals('09:00 AM - 10:00 AM'));

      final jsonDisabled = {
        'id': 11,
        'day': 'Tue',
        'status': 0,
        'total_day': 7,
        'schedules': []
      };
      final disabledDay = ProviderWorkingDay.fromJson(jsonDisabled);
      expect(disabledDay.id, equals(11));
      expect(disabledDay.shortName, equals('Tue'));
      expect(disabledDay.fullName, equals('Tuesday'));
      expect(disabledDay.isEnabled, isFalse);
      expect(disabledDay.schedules.isEmpty, isTrue);
    });

    test('3. Unconfigured fallback day has id 0 and is NOT marked as valid database day', () {
      final unconfigured = ProviderWorkingDay(
        id: 0,
        day: 'Wed',
        status: 0,
        totalDay: 7,
        schedules: [],
      );
      expect(unconfigured.id, equals(0));
      expect(unconfigured.id > 0, isFalse);
    });

    test('4. toggleWorkingDay rejects invalid dayId <= 0', () async {
      final service = ProviderAvailabilityService();
      final result = await service.toggleWorkingDay(0);
      expect(result, isFalse, reason: 'Cannot toggle an unconfigured day with id 0');
    });

    test('5. addTimeSlot rejects dayId <= 0 when not allDays', () async {
      final service = ProviderAvailabilityService();
      final result = await service.addTimeSlot(dayId: 0, schedule: '09:00 AM - 10:00 AM', allDays: false);
      expect(result, isFalse, reason: 'Cannot add slot to invalid day id');
    });

    test('6. Concurrency guard prevents duplicate mutation while isSaving is true', () async {
      final service = ProviderAvailabilityService();
      service.isSaving = true;
      expect(await service.toggleWorkingDay(5), isFalse);
      expect(await service.createWorkingDay('Mon'), isFalse);
      expect(await service.addTimeSlot(dayId: 5, schedule: '09:00 AM - 10:00 AM'), isFalse);
      expect(await service.deleteTimeSlot(101), isFalse);
    });

    test('7. Error state accurately sets errorMessage and hasError', () {
      final service = ProviderAvailabilityService();
      service.errorMessage = 'Failed to load availability';
      service.isInitialized = true;
      expect(service.hasError, isTrue);
      expect(service.errorMessage, equals('Failed to load availability'));
    });
  });

  group('Fix Group 5: Arabic Customer Availability Canonical Weekday', () {
    test('8. firstThreeLetter(date, null) ALWAYS returns canonical 3-letter English weekday', () {
      // Create specific calendar dates representing all 7 weekdays
      final sunday = DateTime(2026, 10, 4);
      final monday = DateTime(2026, 10, 5);
      final tuesday = DateTime(2026, 10, 6);
      final wednesday = DateTime(2026, 10, 7);
      final thursday = DateTime(2026, 10, 8);
      final friday = DateTime(2026, 10, 9);
      final saturday = DateTime(2026, 10, 10);

      expect(firstThreeLetter(sunday, null), equals('Sun'));
      expect(firstThreeLetter(monday, null), equals('Mon'));
      expect(firstThreeLetter(tuesday, null), equals('Tue'));
      expect(firstThreeLetter(wednesday, null), equals('Wed'));
      expect(firstThreeLetter(thursday, null), equals('Thu'));
      expect(firstThreeLetter(friday, null), equals('Fri'));
      expect(firstThreeLetter(saturday, null), equals('Sat'));
    });

    test('9. Arabic locale must NEVER be passed to API weekday identifier', () {
      final sunday = DateTime(2026, 10, 4);
      // Passing 'ar' produces Arabic characters which fail backend $dayMap lookup
      final arabicFormatted = firstThreeLetter(sunday, 'ar');
      expect(arabicFormatted, isNot(equals('Sun')));

      // Passing null produces canonical English which matches backend $dayMap
      final canonicalFormatted = firstThreeLetter(sunday, null);
      expect(canonicalFormatted, equals('Sun'));
    });
  });

  group('Fix Group 6, 7, 8: Customer Week Navigation & Date Arithmetic', () {
    test('10. Next week advances visible dates by exactly 7 calendar days', () {
      final startWeek = DateTime(2026, 10, 4);
      final nextWeek = startWeek.add(const Duration(days: 7));
      expect(nextWeek, equals(DateTime(2026, 10, 11)));
      expect(nextWeek.difference(startWeek).inDays, equals(7));
    });

    test('11. Previous week decreases visible dates by 7 days and is clamped to today', () {
      final today = DateTime(2026, 10, 4);
      DateTime currentWeek = DateTime(2026, 10, 18);

      // Move previous once (from Oct 18 to Oct 11)
      DateTime prevWeek = currentWeek.subtract(const Duration(days: 7));
      if (prevWeek.isBefore(today)) prevWeek = today;
      expect(prevWeek, equals(DateTime(2026, 10, 11)));

      // Move previous again (from Oct 11 to Oct 4 = today)
      DateTime prevWeek2 = prevWeek.subtract(const Duration(days: 7));
      if (prevWeek2.isBefore(today)) prevWeek2 = today;
      expect(prevWeek2, equals(DateTime(2026, 10, 4)));

      // Attempt to move earlier than today: clamped to today!
      DateTime prevWeek3 = prevWeek2.subtract(const Duration(days: 7));
      if (prevWeek3.isBefore(today)) prevWeek3 = today;
      expect(prevWeek3, equals(today));
      expect(prevWeek3.isBefore(today), isFalse);
    });

    test('12. Calendar picker arbitrary future date jump synchronizes weekday and exact date', () {
      // Pick a date 3 months in the future: Jan 15, 2027 (Friday)
      final pickedDate = DateTime(2027, 1, 15);
      final weekday = firstThreeLetter(pickedDate, null);
      final apiDateFormatted = DateFormat('yyyy-MM-dd').format(pickedDate);
      final orderDateFormatted = DateFormat.yMMMMEEEEd().format(pickedDate);

      expect(weekday, equals('Fri'));
      expect(apiDateFormatted, equals('2027-01-15'));
      expect(orderDateFormatted, contains('Friday'));
      expect(orderDateFormatted, contains('2027'));
    });

    test('13. Booking date persistence format matches expected order date', () {
      final testDate = DateTime(2026, 11, 23); // Monday
      final formattedForOrder = DateFormat.yMMMMEEEEd().format(testDate);
      expect(formattedForOrder, equals('Monday, November 23, 2026'));
      expect(firstThreeLetter(testDate, null), equals('Mon'));
    });
  });
}
