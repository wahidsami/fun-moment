import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:pusher_beams/pusher_beams.dart';
import 'package:funmoments/service/filter_services_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/searchbar_with_dropdown_service.dart';
import 'package:funmoments/view/home/homepage_helper.dart';
import 'package:funmoments/view/home/bottom_nav.dart';
import 'package:funmoments/view/home/home.dart';
import 'package:funmoments/view/notification/push_notification_helper.dart';
import 'package:funmoments/view/tabs/orders/orders_page.dart';
import 'package:funmoments/view/tabs/saved_item_page.dart';
import 'package:funmoments/view/tabs/search/search_tab.dart';
import 'package:funmoments/view/tabs/settings/menu_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/jobs/my_jobs_page.dart';
import 'package:funmoments/view/provider/provider_dashboard_page.dart';
import 'package:funmoments/view/provider/provider_services_page.dart';

import 'package:funmoments/view/utils/constant_colors.dart';
import '../search/service_filter_molde.dart';

class LandingPage extends StatefulWidget {
  const LandingPage({Key? key}) : super(key: key);

  @override
  State<LandingPage> createState() => _HomePageState();
}

class _HomePageState extends State<LandingPage> {
  DateTime? currentBackPressTime;
  bool _isRoleResolved = false;
  bool _isSeller = false;

  @override
  void initState() {
    super.initState();
    setChatSellerId(null);
    _resolveUserRole();
  }

  Future<void> _resolveUserRole() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.get('userType');
    int? uType;
    if (raw is int) {
      uType = raw;
    } else if (raw is num) {
      uType = raw.toInt();
    } else if (raw is String) {
      uType = int.tryParse(raw);
    }
    if (mounted) {
      setState(() {
        _isSeller = (uType == 0);
        _isRoleResolved = true;
      });
    }
  }

  void onTabTapped(int index) {
    final profileProvider = Provider.of<ProfileService>(context, listen: false);
    if (!profileProvider.isSeller && index == 1) {
      Provider.of<FilterServicesService>(context, listen: false).resetFilters();
      ServiceFilterViewModel.instance.searchTextController.text = "";
    }
    HomepageHelper.tabIndex.value = index;
  }

  final List<Widget> _buyerChildren = [
    const Homepage(),
    const SearchTab(),
    const OrdersPage(),
    const SavedItemPage(),
    const MenuPage(),
  ];

  final List<Widget> _sellerChildren = [
    const ProviderDashboardPage(),
    const ProviderServicesPage(),
    const OrdersPage(),
    const MyJobsPage(),
    const MenuPage(),
  ];

  initPusherBeams(BuildContext context) async {
    var pusherInstance =
        await Provider.of<PushNotificationService>(context, listen: false)
            .pusherInstance;

    if (pusherInstance == null) return;

    if (!kIsWeb) {
      await PusherBeams.instance
          .onMessageReceivedInTheForeground(_onMessageReceivedInTheForeground);
    }
    await _checkForInitialMessage(context);

    SharedPreferences prefs = await SharedPreferences.getInstance();
    var userId = prefs.getInt('userId');
    try {
      await PusherBeams.instance.addDeviceInterest('debug-buyer$userId');
    } catch (e) {}
  }

  Future<void> _checkForInitialMessage(BuildContext context) async {
    final initialMessage = await PusherBeams.instance.getInitialMessage();
    if (initialMessage != null) {
      PushNotificationHelper().notificationAlert(
          context, 'Initial Message Is:', initialMessage.toString());
    }
  }

  void _onMessageReceivedInTheForeground(Map<Object?, Object?> data) {
    Map metaData = data["data"] is Map ? data["data"] as Map : {};
    if (metaData["type"] == "message" &&
        metaData["sender-id"] == chatSellerId) {
      return;
    }
    PushNotificationHelper().notificationAlert(
        context, data["title"].toString(), data["body"].toString());
  }

  @override
  Widget build(BuildContext context) {
    final profileProvider = Provider.of<ProfileService>(context);
    final isRoleResolved = _isRoleResolved || profileProvider.isRoleResolved;
    final isSeller = profileProvider.profileDetails != null
        ? profileProvider.isSeller
        : _isSeller;

    if (!isRoleResolved) {
      return Scaffold(
        backgroundColor: ConstantColors().bgColor,
        body: Center(
          child: OthersHelper().showLoading(ConstantColors().primaryColor),
        ),
      );
    }

    return Scaffold(
      body: ValueListenableBuilder<int>(
        valueListenable: HomepageHelper.tabIndex,
        builder: (context, value, child) {
          return WillPopScope(
            onWillPop: () {
              final now = DateTime.now();
              if (currentBackPressTime == null ||
                  now.difference(currentBackPressTime!) >
                      const Duration(seconds: 2)) {
                currentBackPressTime = now;
                OthersHelper().showToast("Press again to exit", Colors.black);
                return Future.value(false);
              }
              return Future.value(true);
            },
            child: IndexedStack(
              index: value,
              children: isSeller ? _sellerChildren : _buyerChildren,
            ),
          );
        },
      ),
      bottomNavigationBar: ValueListenableBuilder<int>(
        valueListenable: HomepageHelper.tabIndex,
        builder: (context, value, child) {
          return BottomNav(
            currentIndex: value,
            onTabTapped: onTabTapped,
            isSeller: isSeller,
          );
        },
      ),
    );
  }
}
