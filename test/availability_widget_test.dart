import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/provider_availability_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/provider/provider_availability_page.dart';
import 'package:provider/provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Widget createProviderAvailabilityTestWidget(ProviderAvailabilityService service) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<ProviderAvailabilityService>.value(value: service),
        ChangeNotifierProvider<AppStringService>(create: (_) => AppStringService()),
        ChangeNotifierProvider<RtlService>(create: (_) => RtlService()),
      ],
      child: const MaterialApp(
        home: ProviderAvailabilityPage(),
      ),
    );
  }

  group('ProviderAvailabilityPage Widget Lifecycle Tests', () {
    testWidgets('1. Shows loading indicator when not initialized, never fake OFF switches', (tester) async {
      final service = ProviderAvailabilityService();
      // Not initialized
      service.isInitialized = false;
      service.isLoading = false;
      service.days = [];

      await tester.pumpWidget(createProviderAvailabilityTestWidget(service));
      await tester.pump();

      // CircularProgressIndicator must be present
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      // Switches must NOT be present
      expect(find.byType(Switch), findsNothing);
      expect(find.text('Day Disabled'), findsNothing);
    });

    testWidgets('2. Shows Error UI with Retry button on API failure, never fake OFF switches', (tester) async {
      final service = ProviderAvailabilityService();
      service.isInitialized = true;
      service.isLoading = false;
      service.days = [];
      service.errorMessage = 'Connection refused';

      await tester.pumpWidget(createProviderAvailabilityTestWidget(service));
      await tester.pump();

      expect(find.text('Could not load availability'), findsOneWidget);
      expect(find.text('Connection refused'), findsOneWidget);
      expect(find.text('Retry'), findsOneWidget);
      expect(find.byType(Switch), findsNothing);
    });

    testWidgets('3. Renders hydrated days with real switches when initialized successfully', (tester) async {
      tester.view.physicalSize = const Size(800, 1400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(() => tester.view.resetPhysicalSize());

      final service = ProviderAvailabilityService();
      service.isInitialized = true;
      service.isLoading = false;
      service.days = [
        ProviderWorkingDay(
          id: 1,
          day: 'Sun',
          status: 1,
          totalDay: 7,
          schedules: [
            ProviderTimeSlot(id: 101, dayId: 1, schedule: '10:00 AM - 11:00 AM', status: 1)
          ],
        ),
        ProviderWorkingDay(
          id: 2,
          day: 'Mon',
          status: 0,
          totalDay: 7,
          schedules: [],
        ),
      ];

      await tester.pumpWidget(createProviderAvailabilityTestWidget(service));
      await tester.pump();

      // Should show the 7 days
      expect(find.text('Configure Availability'), findsOneWidget);
      // Sunday is enabled
      expect(find.text('1 Slots Active'), findsOneWidget);
      // Monday is disabled
      expect(find.text('Day Disabled'), findsWidgets);

      // Switches are present
      final switches = find.byType(Switch);
      expect(switches, findsNWidgets(7));

      // Sunday switch is true, Monday switch is false
      final sunSwitch = tester.widget<Switch>(switches.first);
      expect(sunSwitch.value, isTrue);

      final monSwitch = tester.widget<Switch>(switches.at(1));
      expect(monSwitch.value, isFalse);
    });

    testWidgets('4. Switches are disabled while saving/loading to prevent race conditions', (tester) async {
      final service = ProviderAvailabilityService();
      service.isInitialized = true;
      service.isSaving = true; // In the middle of mutation
      service.days = [
        ProviderWorkingDay(id: 1, day: 'Sun', status: 1, totalDay: 7, schedules: []),
      ];

      await tester.pumpWidget(createProviderAvailabilityTestWidget(service));
      await tester.pump();

      final switches = find.byType(Switch);
      expect(switches, findsWidgets);

      // All switch onChanged handlers must be null (disabled)
      for (final s in tester.widgetList<Switch>(switches)) {
        expect(s.onChanged, isNull, reason: 'Switch must be disabled during isSaving');
      }
    });
  });
}
