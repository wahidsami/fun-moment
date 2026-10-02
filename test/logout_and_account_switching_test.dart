import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/apple_sign_in_sevice.dart';
import 'package:funmoments/service/auth_services/google_sign_service.dart';
import 'package:funmoments/service/auth_services/login_service.dart';
import 'package:funmoments/service/auth_services/logout_service.dart';
import 'package:funmoments/service/jobs_service/my_jobs_service.dart';
import 'package:funmoments/service/live_chat/chat_list_service.dart';
import 'package:funmoments/service/live_chat/chat_message_service.dart';
import 'package:funmoments/service/my_orders_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/provider_availability_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/report_services/report_message_service.dart';
import 'package:funmoments/service/report_services/report_service.dart';
import 'package:funmoments/service/support_ticket/support_messages_service.dart';
import 'package:funmoments/service/support_ticket/support_ticket_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/view/auth/login/login.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/book_confirmation_service.dart';
import 'package:funmoments/service/book_steps_service.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/filter_services_service.dart';
import 'package:funmoments/service/orders_service.dart';
import 'package:funmoments/view/utils/custom_button.dart';
import 'package:funmoments/view/utils/login_or_register.dart';
import 'package:funmoments/view/home/bottom_nav.dart';
import 'package:funmoments/view/home/landing_page.dart';
import 'package:funmoments/view/utils/responsive.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    rtlProvider = RtlService();
    lnProvider = AppStringService();

    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('PonnamKarthik/fluttertoast'),
            (call) async => null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('dev.fluttercommunity.plus/connectivity'),
            (call) async => 'wifi');
  });

  group('T-LOG: Logout & Account Switching Lifecycle Tests', () {
    test('T-LOG-01: clear() removes all user auth keys and preserves global configs', () async {
      SharedPreferences.setMockInitialValues({
        'token': 'test_bearer_token_123',
        'userId': 42,
        'userType': 0,
        'email': 'user@example.com',
        'pass': 'secret123',
        'state': 'Riyadh',
        'countryId': '191',
        'keepLoggedIn': true,
        'googleLogin': true,
        'fbLogin': false,
        'appleLogin': true,
        'userName': 'User Name',
        'userToken': 'apple_token_xyz',
        'appleId': 'apple_id_abc',
        // Global configs that MUST be preserved:
        'intro': true,
        'translated_string': '{"key":"val"}',
        'cached_categories_json': '[{"id":1}]',
      });

      final logoutService = LogoutService();
      await logoutService.clear();

      final prefs = await SharedPreferences.getInstance();

      // Auth keys removed
      expect(prefs.containsKey('token'), isFalse);
      expect(prefs.containsKey('userId'), isFalse);
      expect(prefs.containsKey('userType'), isFalse);
      expect(prefs.containsKey('email'), isFalse);
      expect(prefs.containsKey('pass'), isFalse);
      expect(prefs.containsKey('state'), isFalse);
      expect(prefs.containsKey('countryId'), isFalse);
      expect(prefs.containsKey('keepLoggedIn'), isFalse);
      expect(prefs.containsKey('googleLogin'), isFalse);
      expect(prefs.containsKey('fbLogin'), isFalse);
      expect(prefs.containsKey('appleLogin'), isFalse);
      expect(prefs.containsKey('userName'), isFalse);
      expect(prefs.containsKey('userToken'), isFalse);
      expect(prefs.containsKey('appleId'), isFalse);

      // Global configs preserved
      expect(prefs.getBool('intro'), isTrue);
      expect(prefs.getString('translated_string'), equals('{"key":"val"}'));
      expect(prefs.getString('cached_categories_json'), equals('[{"id":1}]'));
    });

    test('T-LOG-02: All user-scoped providers have working resetState() methods', () {
      // 1. ProviderAvailabilityService
      final availabilityService = ProviderAvailabilityService();
      availabilityService.days = [
        ProviderWorkingDay(id: 1, day: 'Sun', status: 1, totalDay: 7, schedules: [])
      ];
      availabilityService.isInitialized = true;
      availabilityService.errorMessage = 'Some error';
      availabilityService.isLoading = true;
      availabilityService.isSaving = true;

      availabilityService.resetState();
      expect(availabilityService.days.isEmpty, isTrue);
      expect(availabilityService.isInitialized, isFalse);
      expect(availabilityService.errorMessage, isNull);
      expect(availabilityService.isLoading, isFalse);
      expect(availabilityService.isSaving, isFalse);

      // 2. ProviderServiceManagementService
      final psmService = ProviderServiceManagementService();
      psmService.services = [
        ProviderServiceItem(id: 1, title: 'Service 1', price: 100, isServiceOn: 1)
      ];
      psmService.dashboardData = ProviderDashboardData(pendingOrders: 5);
      psmService.isLoading = true;

      psmService.resetState();
      expect(psmService.services.isEmpty, isTrue);
      expect(psmService.dashboardData, isNull);
      expect(psmService.isLoading, isFalse);

      // 3. MyOrdersService
      final ordersService = MyOrdersService();
      ordersService.myServices = [{'id': 99}];
      ordersService.nextPageUrl = 'https://api.com/orders?page=2';
      ordersService.totalPages = 5;
      ordersService.currentPage = 2;
      ordersService.isLoading = true;

      ordersService.resetState();
      expect(ordersService.myServices, isNull);
      expect(ordersService.nextPageUrl, isNull);
      expect(ordersService.totalPages, equals(1));
      expect(ordersService.currentPage, equals(1));
      expect(ordersService.isLoading, isFalse);

      // 4. OrderDetailsService
      final detailsService = OrderDetailsService();
      detailsService.orderDetails = {'id': 99};
      detailsService.orderStatus = 'pending';
      detailsService.orderExtra = ['extra1'];
      detailsService.isLoading = false;

      detailsService.resetState();
      expect(detailsService.orderDetails, isNull);
      expect(detailsService.orderStatus, isNull);
      expect(detailsService.orderExtra.isEmpty, isTrue);
      expect(detailsService.isLoading, isTrue);

      // 5. WalletService
      final walletService = WalletService();
      walletService.walletHistory = [{'amount': 100}];
      walletService.amountToAdd = '50';

      walletService.resetState();
      expect(walletService.walletHistory, isNull);
      expect(walletService.amountToAdd, isNull);

      // 6. PushNotificationService
      final pushService = PushNotificationService();
      pushService.notifications = [{'title': 'Hello'}];
      pushService.unreadCount = 3;

      pushService.resetState();
      expect(pushService.notifications.isEmpty, isTrue);
      expect(pushService.unreadCount, equals(0));

      // 7. ChatListService
      final chatListService = ChatListService();
      chatListService.chatList = [{'sender': 'Provider'}];
      chatListService.storeChatList = [{'sender': 'Provider'}];

      chatListService.resetState();
      expect(chatListService.chatList.isEmpty, isTrue);
      expect(chatListService.storeChatList.isEmpty, isTrue);

      // 8. ChatMessagesService
      final chatMsgService = ChatMessagesService();
      chatMsgService.messagesList = [{'msg': 'Hi'}];

      chatMsgService.resetState();
      expect(chatMsgService.messagesList, isNull);

      // 9. SupportTicketService
      final ticketService = SupportTicketService();
      ticketService.ticketList = [{'subject': 'Help'}];
      ticketService.resetState();
      expect(ticketService.ticketList.isEmpty, isTrue);

      // 10. SupportMessagesService
      final supportMsgService = SupportMessagesService();
      supportMsgService.messagesList = [{'msg': 'Help reply'}];
      supportMsgService.resetState();
      expect(supportMsgService.messagesList.isEmpty, isTrue);

      // 11. ReportService
      final reportService = ReportService();
      reportService.reportList = [{'id': 1}];
      reportService.resetState();
      expect(reportService.reportList.isEmpty, isTrue);

      // 12. ReportMessagesService
      final reportMsgService = ReportMessagesService();
      reportMsgService.messagesList = [{'msg': 'Report note'}];
      reportMsgService.resetState();
      expect(reportMsgService.messagesList.isEmpty, isTrue);

      // 13. MyJobsService
      final jobsService = MyJobsService();
      jobsService.myJobsListMap = [{'title': 'Job 1'}];
      jobsService.resetState();
      expect(jobsService.myJobsListMap.isEmpty, isTrue);

      // 14. ProfileService
      final profileService = ProfileService();
      profileService.profileImage = 'https://example.com/avatar.jpg';
      profileService.ordersList = [1, 2, 3, 4];
      profileService.setEverythingToDefault();
      expect(profileService.profileDetails, isNull);
      expect(profileService.profileImage, isNull);
      expect(profileService.ordersList, equals([0, 0, 0, 0]));
      expect(profileService.isRoleResolved, isFalse);

      // 15. OrdersService
      final os = OrdersService();
      os.setMarkLoadingStatus(true);
      expect(os.markLoading, isTrue);
      os.resetState();
      expect(os.markLoading, isFalse);

      // 16. FilterServicesService
      final fs = FilterServicesService();
      fs.searchText = 'DJ Party';
      fs.minPrice = '100';
      fs.resetState();
      expect(fs.searchText, isEmpty);
      expect(fs.minPrice, isEmpty);

      // 17. BookConfirmationService
      final bcs = BookConfirmationService();
      bcs.setTotalOfflineService(750.0);
      bcs.setPanelOpenedTrue();
      bcs.resetState();
      expect(bcs.totalPriceAfterAllcalculation, equals(0.0));
      expect(bcs.isPanelOpened, isFalse);

      // 18. BookStepsService
      final bss = BookStepsService();
      bss.increaseCurrentStep(2);
      expect(bss.currentStep, equals(3));
      bss.resetState();
      expect(bss.currentStep, equals(1));

      // 19. PlaceOrderService
      final pos = PlaceOrderService();
      pos.setOrderId('ORDER_TEST_1');
      pos.setLoadingTrue();
      pos.resetState();
      expect(pos.orderId, isNull);
      expect(pos.isloading, isFalse);
    });

    testWidgets('T-LOG-03: Guaranteed teardown works under simulated network failure/timeout', (tester) async {
      SharedPreferences.setMockInitialValues({
        'token': 'mock_token',
        'userId': 123,
        'userType': 0,
      });

      final logoutService = LogoutService();
      final availabilityService = ProviderAvailabilityService();
      availabilityService.days = [
        ProviderWorkingDay(id: 1, day: 'Sun', status: 1, totalDay: 7, schedules: [])
      ];

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: logoutService),
            ChangeNotifierProvider.value(value: availabilityService),
            ChangeNotifierProvider(create: (_) => ProfileService()),
            ChangeNotifierProvider(create: (_) => ProviderServiceManagementService()),
            ChangeNotifierProvider(create: (_) => MyOrdersService()),
            ChangeNotifierProvider(create: (_) => OrderDetailsService()),
            ChangeNotifierProvider(create: (_) => OrdersService()),
            ChangeNotifierProvider(create: (_) => FilterServicesService()),
            ChangeNotifierProvider(create: (_) => BookConfirmationService()),
            ChangeNotifierProvider(create: (_) => BookStepsService()),
            ChangeNotifierProvider(create: (_) => PlaceOrderService()),
            ChangeNotifierProvider(create: (_) => WalletService()),
            ChangeNotifierProvider.value(value: PushNotificationService()),
            ChangeNotifierProvider(create: (_) => ChatListService()),
            ChangeNotifierProvider(create: (_) => ChatMessagesService()),
            ChangeNotifierProvider(create: (_) => SupportTicketService()),
            ChangeNotifierProvider(create: (_) => SupportMessagesService()),
            ChangeNotifierProvider(create: (_) => ReportService()),
            ChangeNotifierProvider(create: (_) => ReportMessagesService()),
            ChangeNotifierProvider(create: (_) => MyJobsService()),
            ChangeNotifierProvider(create: (_) => LoginService()),
            ChangeNotifierProvider(create: (_) => GoogleSignInService()),
            ChangeNotifierProvider(create: (_) => AppleSignInService()),
            ChangeNotifierProvider.value(value: rtlProvider),
            ChangeNotifierProvider.value(value: lnProvider),
          ],
          child: MaterialApp(
            navigatorKey: navigatorKey,
            home: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => logoutService.logout(context),
                child: const Text('Logout'),
              ),
            ),
          ),
        ),
      );

      // Trigger logout
      await tester.tap(find.text('Logout'));
      await tester.pump();

      // Ensure loading flag was reset in finally block even if network fails
      await tester.pumpAndSettle();

      expect(logoutService.isloading, isFalse, reason: 'isloading must be reset to false in finally');
      expect(availabilityService.days.isEmpty, isTrue, reason: 'Provider days must be cleared');

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.containsKey('token'), isFalse, reason: 'Token must be removed');
      expect(prefs.containsKey('userId'), isFalse, reason: 'userId must be removed');
      expect(prefs.containsKey('userType'), isFalse, reason: 'userType must be removed');
    });

    testWidgets('T-LOG-04: Customer to Provider account switching does not leak state', (tester) async {
      // Step 1: Customer A is logged in with orders and chats
      final myOrdersService = MyOrdersService();
      myOrdersService.myServices = [{'order_id': 1001, 'type': 'customer_order'}];

      final chatListService = ChatListService();
      chatListService.chatList = [{'buyer_id': 500}];

      // Step 2: Customer A logs out
      myOrdersService.resetState();
      chatListService.resetState();

      // Step 3: Verify state is empty before Provider B logs in
      expect(myOrdersService.myServices, isNull);
      expect(chatListService.chatList.isEmpty, isTrue);
    });

    testWidgets('T-LOG-05: Provider to Customer account switching does not leak availability or services', (tester) async {
      // Step 1: Provider A has schedules and dashboard metrics
      final availabilityService = ProviderAvailabilityService();
      availabilityService.days = [
        ProviderWorkingDay(id: 1, day: 'Mon', status: 1, totalDay: 7, schedules: [])
      ];

      final psmService = ProviderServiceManagementService();
      psmService.dashboardData = ProviderDashboardData(completedOrders: 15, remainingBalance: 500);

      // Step 2: Provider A logs out
      availabilityService.resetState();
      psmService.resetState();

      // Step 3: Customer B session starts clean
      expect(availabilityService.days.isEmpty, isTrue);
      expect(psmService.dashboardData, isNull);
    });

    testWidgets('T-LOG-06: Provider A to Provider B switching does not leak schedule or services', (tester) async {
      final availabilityService = ProviderAvailabilityService();
      availabilityService.days = [
        ProviderWorkingDay(id: 77, day: 'Tue', status: 1, totalDay: 7, schedules: [])
      ];
      final psmService = ProviderServiceManagementService();
      psmService.services = [
        ProviderServiceItem(id: 88, title: 'Provider A Service', price: 200, isServiceOn: 1)
      ];

      // Logout clears Provider A state
      availabilityService.resetState();
      psmService.resetState();

      expect(availabilityService.days.isEmpty, isTrue);
      expect(psmService.services.isEmpty, isTrue);
    });

    test('T-LOG-07: Unauthenticated role resolution resolves cleanly without deadlock', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.get('userType');
      expect(raw, isNull);

      // Verify ProfileService reports isBuyer == true when unauthenticated (default safe mode)
      final profileService = ProfileService();
      expect(profileService.isSeller, isFalse);
      expect(profileService.isBuyer, isTrue);

      // When userType is provider (0), resolves to seller
      await prefs.setInt('userType', 0);
      await profileService.initUserType();
      expect(profileService.isRoleResolved, isTrue);
      expect(profileService.isSeller, isTrue);
      expect(profileService.isBuyer, isFalse);

      // After logout, clear removes userType and profileService resets
      await LogoutService().clear();
      expect(prefs.containsKey('userType'), isFalse);
      profileService.setEverythingToDefault();
      expect(profileService.profileDetails, isNull);
      expect(profileService.userType, isNull);
      expect(profileService.isSeller, isFalse);
      expect(profileService.isBuyer, isTrue);
    });

    test('T-LOG-08: ProfileService fetchData does not fire network call when token is null', () async {
      SharedPreferences.setMockInitialValues({});
      final profileService = ProfileService();

      final result = await profileService.fetchData();
      expect(result, isFalse);
      expect(profileService.isloading, isFalse);
      expect(profileService.profileDetails, isNull);
    });

    testWidgets('T-LOG-09: FMPrimaryButton respects height, fontSize, and rendered dimensions', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: FMPrimaryButton(
              label: 'Login',
              height: 54,
              fontSize: 16,
              onPressed: () {},
            ),
          ),
        ),
      );

      final sizedBoxFinder = find.byType(SizedBox).first;
      final sizedBox = tester.widget<SizedBox>(sizedBoxFinder);
      expect(sizedBox.height, equals(54.0));

      final textFinder = find.text('Login');
      expect(textFinder, findsOneWidget);
      final textWidget = tester.widget<Text>(textFinder);
      expect(textWidget.style?.fontSize, equals(16.0));
      expect(textWidget.style?.fontWeight, equals(FontWeight.w700));

      // Assert physical rendered dimensions via RenderBox
      final renderedSize = tester.getSize(find.byType(ElevatedButton));
      expect(renderedSize.height, greaterThanOrEqualTo(50.0));
    });

    testWidgets('T-LOG-10: LoginOrRegister CTA renders full-width, >=50dp height in English & Arabic', (tester) async {
      tester.view.physicalSize = const Size(390 * 3, 844 * 3);
      tester.view.devicePixelRatio = 3.0;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
      });

      // 1. English viewport test
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: lnProvider),
            ChangeNotifierProvider.value(value: rtlProvider),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: LoginOrRegister(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // CTA is FMPrimaryButton, not legacy CustomButton
      final primaryBtnFinder = find.byType(FMPrimaryButton);
      expect(primaryBtnFinder, findsOneWidget);
      expect(find.byType(CustomButton), findsNothing);

      // Measure real rendered geometry
      final englishBtnSize = tester.getSize(primaryBtnFinder);
      expect(englishBtnSize.height, greaterThanOrEqualTo(50.0));
      // Full available content width: >= 80% of test viewport width
      const viewportWidth = 390.0;
      expect(englishBtnSize.width, greaterThanOrEqualTo(viewportWidth * 0.8));

      // 2. Arabic RTL viewport test
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: lnProvider),
            ChangeNotifierProvider.value(value: rtlProvider),
          ],
          child: const MaterialApp(
            locale: Locale('ar'),
            home: Directionality(
              textDirection: TextDirection.rtl,
              child: Scaffold(
                body: LoginOrRegister(),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      final arabicBtnFinder = find.byType(FMPrimaryButton);
      expect(arabicBtnFinder, findsOneWidget);
      final arabicBtnSize = tester.getSize(arabicBtnFinder);
      expect(arabicBtnSize.height, greaterThanOrEqualTo(50.0));
      expect(arabicBtnSize.width, greaterThanOrEqualTo(viewportWidth * 0.8));

      // Verify Cairo font family is applied to Arabic button text
      final textWidget = tester.widget<Text>(find.descendant(of: arabicBtnFinder, matching: find.byType(Text)));
      expect(textWidget.style?.fontFamily, equals('Cairo'));
    });

    testWidgets('T-LOG-11: Login return behavior navigates to LoginPage with returnToPrevious=true', (tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => LoginService()),
            ChangeNotifierProvider(create: (_) => GoogleSignInService()),
            ChangeNotifierProvider(create: (_) => AppleSignInService()),
            ChangeNotifierProvider.value(value: lnProvider),
            ChangeNotifierProvider.value(value: rtlProvider),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: LoginOrRegister(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Tap the CTA
      await tester.tap(find.byType(FMPrimaryButton));
      await tester.pumpAndSettle();

      // Verify LoginPage was pushed with returnToPrevious == true
      final loginPageFinder = find.byType(LoginPage);
      expect(loginPageFinder, findsOneWidget);

      final loginPage = tester.widget<LoginPage>(loginPageFinder);
      expect(loginPage.hasBackButton, isTrue);
      expect(loginPage.returnToPrevious, isTrue);
    });

    testWidgets('T-LOG-12: Account-switch teardown clears Account A state for clean Account B login', (tester) async {
      // Simulate Account A (Provider) populating state
      final ordersService = OrdersService()..setMarkLoadingStatus(true);
      final filterService = FilterServicesService()..searchText = 'DJ Event Services';
      final bookConfService = BookConfirmationService()..setTotalOfflineService(1250.0);
      final bookStepsService = BookStepsService()..increaseCurrentStep(3);
      final placeOrderService = PlaceOrderService()..setOrderId('ORDER_98765');
      final availabilityService = ProviderAvailabilityService()
        ..days = [ProviderWorkingDay(id: 1, day: 'Mon', status: 1, totalDay: 7, schedules: [])];
      final profileService = ProfileService();

      final logoutService = LogoutService();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: logoutService),
            ChangeNotifierProvider.value(value: ordersService),
            ChangeNotifierProvider.value(value: filterService),
            ChangeNotifierProvider.value(value: bookConfService),
            ChangeNotifierProvider.value(value: bookStepsService),
            ChangeNotifierProvider.value(value: placeOrderService),
            ChangeNotifierProvider.value(value: availabilityService),
            ChangeNotifierProvider.value(value: profileService),
            ChangeNotifierProvider(create: (_) => ProviderServiceManagementService()),
            ChangeNotifierProvider(create: (_) => MyOrdersService()),
            ChangeNotifierProvider(create: (_) => OrderDetailsService()),
            ChangeNotifierProvider(create: (_) => WalletService()),
            ChangeNotifierProvider.value(value: PushNotificationService()),
            ChangeNotifierProvider(create: (_) => ChatListService()),
            ChangeNotifierProvider(create: (_) => ChatMessagesService()),
            ChangeNotifierProvider(create: (_) => SupportTicketService()),
            ChangeNotifierProvider(create: (_) => SupportMessagesService()),
            ChangeNotifierProvider(create: (_) => ReportService()),
            ChangeNotifierProvider(create: (_) => ReportMessagesService()),
            ChangeNotifierProvider(create: (_) => MyJobsService()),
            ChangeNotifierProvider(create: (_) => LoginService()),
            ChangeNotifierProvider(create: (_) => GoogleSignInService()),
            ChangeNotifierProvider(create: (_) => AppleSignInService()),
            ChangeNotifierProvider.value(value: rtlProvider),
            ChangeNotifierProvider.value(value: lnProvider),
          ],
          child: MaterialApp(
            navigatorKey: navigatorKey,
            home: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => logoutService.logout(context),
                child: const Text('Logout'),
              ),
            ),
          ),
        ),
      );

      // Perform logout teardown
      await tester.tap(find.text('Logout'));
      await tester.pumpAndSettle();

      // Verify Account A state was completely wiped for Account B
      expect(ordersService.markLoading, isFalse, reason: 'OrdersService markLoading must be reset');
      expect(filterService.searchText, isEmpty, reason: 'FilterServicesService search query must be reset');
      expect(bookConfService.totalPriceAfterAllcalculation, equals(0.0), reason: 'Booking draft total must be reset');
      expect(bookStepsService.currentStep, equals(1), reason: 'Booking wizard step must be reset to 1');
      expect(placeOrderService.orderId, isNull, reason: 'PlaceOrderService orderId must be reset');
      expect(availabilityService.days.isEmpty, isTrue, reason: 'Provider availability must be cleared');
      expect(profileService.profileDetails, isNull, reason: 'ProfileDetails must be cleared');
    });
  });
}
