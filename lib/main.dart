import 'package:firebase_core/firebase_core.dart';
import 'package:funmoments/firebase_options.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:provider/provider.dart';
import 'package:google_maps_flutter_android/google_maps_flutter_android.dart';
import 'package:google_maps_flutter_platform_interface/google_maps_flutter_platform_interface.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:funmoments/service/all_services_service.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/apple_sign_in_sevice.dart';
import 'package:funmoments/service/auth_services/change_pass_service.dart';
import 'package:funmoments/service/auth_services/delete_account_service.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/auth_services/facebook_login_service.dart';
import 'package:funmoments/service/auth_services/google_sign_service.dart';
import 'package:funmoments/service/auth_services/login_service.dart';
import 'package:funmoments/service/auth_services/logout_service.dart';
import 'package:funmoments/service/auth_services/reset_password_service.dart';
import 'package:funmoments/service/book_confirmation_service.dart';
import 'package:funmoments/service/book_steps_service.dart';
import 'package:funmoments/service/booking_services/book_service.dart';
import 'package:funmoments/service/booking_services/coupon_service.dart';
import 'package:funmoments/service/booking_services/personalization_service.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/booking_services/shedule_service.dart';
import 'package:funmoments/service/country_states_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/jobs_service/create_job_service.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/home_services/recent_services_service.dart';
import 'package:funmoments/service/home_services/slider_service.dart';
import 'package:funmoments/service/home_services/top_all_services_service.dart';
import 'package:funmoments/service/home_services/top_rated_services_service.dart';
import 'package:funmoments/service/jobs_service/edit_job_country_states_service.dart';
import 'package:funmoments/service/jobs_service/edit_job_service.dart';
import 'package:funmoments/service/jobs_service/job_conversation_service.dart';
import 'package:funmoments/service/jobs_service/job_request_service.dart';
import 'package:funmoments/service/jobs_service/my_jobs_service.dart';
import 'package:funmoments/service/jobs_service/recent_jobs_service.dart';
import 'package:funmoments/service/leave_feedback_service.dart';
import 'package:funmoments/service/live_chat/chat_list_service.dart';
import 'package:funmoments/service/live_chat/chat_message_service.dart';
import 'package:funmoments/service/my_orders_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/orders_service.dart';
import 'package:funmoments/service/pay_services/bank_transfer_service.dart';
import 'package:funmoments/service/pay_services/stripe_service.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';
import 'package:funmoments/service/permissions_service.dart';
import 'package:funmoments/service/profile_edit_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/report_services/report_message_service.dart';
import 'package:funmoments/service/report_services/report_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/saved_items_service.dart';
import 'package:funmoments/service/seller_all_services_service.dart';
import 'package:funmoments/service/searchbar_with_dropdown_service.dart';
import 'package:funmoments/service/service_details_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/service/serviceby_category_service.dart';
import 'package:funmoments/service/support_ticket/create_ticket_service.dart';
import 'package:funmoments/service/support_ticket/support_messages_service.dart';
import 'package:funmoments/service/support_ticket/support_ticket_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/intro/splash.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'service/filter_category_service.dart';
import 'service/filter_services_service.dart';
import 'service/google_location_search_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final GoogleMapsFlutterPlatform mapsImplementation =
      GoogleMapsFlutterPlatform.instance;
  if (mapsImplementation is GoogleMapsFlutterAndroid) {
    mapsImplementation.useAndroidViewSurface = true;
  }

  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  flutterLocalNotificationsPlugin.initialize(
    const InitializationSettings(
      android: AndroidInitializationSettings('icon'),
    ),
    // onDidReceiveBackgroundNotificationResponse: (_) {},
    // onDidReceiveNotificationResponse: (_) {},
  );
  final AndroidFlutterLocalNotificationsPlugin? androidImplementation =
      flutterLocalNotificationsPlugin.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();

  await androidImplementation?.requestPermission();
  await HomepageHelper().locationPermissionCheck();

  runApp(const MyApp());

//get user id, so that we can clear everything cached by provider when user logs out and logs in again
  SharedPreferences prefs = await SharedPreferences.getInstance();
  userId = prefs.getInt('userId');
}

int? userId;

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark));
    return MultiProvider(
      key: ObjectKey(userId),
      providers: [
        ChangeNotifierProvider(create: (_) => CountryStatesService()),
        ChangeNotifierProvider(create: (_) => SignupService()),
        ChangeNotifierProvider(create: (_) => BookConfirmationService()),
        ChangeNotifierProvider(create: (_) => BookStepsService()),
        ChangeNotifierProvider(create: (_) => AllServicesService()),
        ChangeNotifierProvider(create: (_) => LoginService()),
        ChangeNotifierProvider(create: (_) => ResetPasswordService()),
        ChangeNotifierProvider(create: (_) => LogoutService()),
        ChangeNotifierProvider(create: (_) => ChangePassService()),
        ChangeNotifierProvider(create: (_) => ProfileService()),
        ChangeNotifierProvider(create: (_) => SliderService()),
        ChangeNotifierProvider(create: (_) => CategoryService()),
        ChangeNotifierProvider(create: (_) => TopRatedServicesSerivce()),
        ChangeNotifierProvider(create: (_) => ProfileEditService()),
        ChangeNotifierProvider(create: (_) => RecentServicesService()),
        ChangeNotifierProvider(create: (_) => SavedItemService()),
        ChangeNotifierProvider(create: (_) => ServiceDetailsService()),
        ChangeNotifierProvider(create: (_) => LeaveFeedbackService()),
        ChangeNotifierProvider(create: (_) => GoogleSignInService()),
        ChangeNotifierProvider(create: (_) => StripeService()),
        ChangeNotifierProvider(create: (_) => BankTransferService()),
        ChangeNotifierProvider(create: (_) => ServiceByCategoryService()),
        ChangeNotifierProvider(create: (_) => PersonalizationService()),
        ChangeNotifierProvider(create: (_) => BookService()),
        ChangeNotifierProvider(create: (_) => SheduleService()),
        ChangeNotifierProvider(create: (_) => CouponService()),
        ChangeNotifierProvider(create: (_) => SearchBarWithDropdownService()),
        ChangeNotifierProvider(create: (_) => MyOrdersService()),
        ChangeNotifierProvider(create: (_) => PlaceOrderService()),
        ChangeNotifierProvider(create: (_) => FacebookLoginService()),
        ChangeNotifierProvider(create: (_) => SupportTicketService()),
        ChangeNotifierProvider(create: (_) => SupportMessagesService()),
        ChangeNotifierProvider(create: (_) => CreateTicketService()),
        ChangeNotifierProvider(create: (_) => EmailVerifyService()),
        ChangeNotifierProvider(create: (_) => OrderDetailsService()),
        ChangeNotifierProvider(create: (_) => RtlService()),
        ChangeNotifierProvider(create: (_) => TopAllServicesService()),
        ChangeNotifierProvider(create: (_) => PaymentGatewayListService()),
        ChangeNotifierProvider(create: (_) => AppStringService()),
        ChangeNotifierProvider(create: (_) => ChatListService()),
        ChangeNotifierProvider(create: (_) => ChatMessagesService()),
        ChangeNotifierProvider(create: (_) => MyJobsService()),
        ChangeNotifierProvider(create: (_) => CreateJobService()),
        ChangeNotifierProvider(create: (_) => EditJobService()),
        ChangeNotifierProvider(create: (_) => JobRequestService()),
        ChangeNotifierProvider(create: (_) => JobConversationService()),
        ChangeNotifierProvider(create: (_) => EditJobCountryStatesService()),
        ChangeNotifierProvider(create: (_) => OrdersService()),
        ChangeNotifierProvider(create: (_) => WalletService()),
        ChangeNotifierProvider(create: (_) => SellerAllServicesService()),
        ChangeNotifierProvider(create: (_) => PushNotificationService()),
        ChangeNotifierProvider(create: (_) => ReportService()),
        ChangeNotifierProvider(create: (_) => ReportMessagesService()),
        ChangeNotifierProvider(create: (_) => RecentJobsService()),
        ChangeNotifierProvider(create: (_) => PermissionsService()),
        ChangeNotifierProvider(create: (_) => CountryDropdownService()),
        ChangeNotifierProvider(create: (_) => StateDropdownService()),
        ChangeNotifierProvider(create: (_) => AreaDropdownService()),
        ChangeNotifierProvider(create: (_) => DeleteAccountService()),
        ChangeNotifierProvider(create: (_) => AppleSignInService()),
        ChangeNotifierProvider(create: (_) => GoogleLocationSearch()),
        ChangeNotifierProvider(create: (_) => FilterServicesService()),
        ChangeNotifierProvider(create: (_) => FilterCategoryService()),
      ],
      child: Consumer<RtlService>(
        builder: (context, rtlProvider, child) {
          return MaterialApp(
            debugShowCheckedModeBanner: false,
            title: 'Fun Moments',
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            supportedLocales: [
              Locale(rtlProvider.langSlug.substring(0, 2)),
              const Locale('en', "US")
            ],
            builder: (context, rtlchild) {
              return Consumer<RtlService>(
                builder: (context, rtlP, child) => Directionality(
                  textDirection: rtlP.direction == 'ltr'
                      ? TextDirection.ltr
                      : TextDirection.rtl,
                  child: rtlchild!,
                ),
              );
            },
            theme: FMTheme.dark(),
            home: child,
          );
        },
        child: const SplashScreen(),
      ),
    );
  }
}
