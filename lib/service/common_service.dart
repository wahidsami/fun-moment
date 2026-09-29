import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform, TargetPlatform;
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/home_services/category_service.dart';
import 'package:funmoments/service/home_services/recent_services_service.dart';
import 'package:funmoments/service/home_services/slider_service.dart';
import 'package:funmoments/service/home_services/top_rated_services_service.dart';
import 'package:funmoments/service/jobs_service/recent_jobs_service.dart';
import 'package:funmoments/service/permissions_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

late bool isIos;

Future<bool> checkConnection() async {
  var connectivityResult = await (Connectivity().checkConnectivity());
  if (connectivityResult == ConnectivityResult.none) {
    OthersHelper()
        .showToast("Please turn on your internet connection", Colors.black);
    return false;
  } else {
    return true;
  }
}

twoDouble(double value) {
  return double.parse(value.toStringAsFixed(1));
}

getYear(value) {
  final f = DateFormat('yyyy');
  var d = f.format(value);
  return d;
}

getTime(value) {
  final f = DateFormat('hh:mm a');
  var d = f.format(value);
  return d;
}

getDate(value) {
  final f = DateFormat('yyyy-MM-dd');
  var d = f.format(value);
  return d;
}

getMonthAndDate(value, local) {
  final f = DateFormat("MMMM dd", local);
  var d = f.format(value);
  return d;
}

firstThreeLetter(value, local) {
  var weekDayName = DateFormat('EEEE', local).format(value).toString();
  return weekDayName.substring(0, 3);
}

checkPlatform() {
  if (kIsWeb) {
    isIos = false;
  } else if (defaultTargetPlatform == TargetPlatform.iOS) {
    isIos = true;
  } else {
    isIos = false;
  }
}

removeUnderscore(value) {
  return value.replaceAll(RegExp('_'), ' ');
}

removeDollar(value) {
  return value.replaceAll(RegExp('[^0-9.]'), '');
}

runAtstart(BuildContext context) async {
  try {
    Provider.of<RtlService>(context, listen: false).fetchCurrency();
  } catch (e) {
    debugPrint('runAtstart fetchCurrency non-fatal: $e');
  }

  try {
    await Provider.of<RtlService>(context, listen: false)
        .fetchDirection(context)
        .timeout(const Duration(seconds: 3));
  } catch (e) {
    debugPrint('runAtstart fetchDirection non-fatal: $e');
  }

  try {
    await Provider.of<ProfileService>(context, listen: false)
        .fetchData()
        .timeout(const Duration(seconds: 3));
  } catch (e) {
    debugPrint('runAtstart fetchData non-fatal: $e');
  }
}

runAtHome(BuildContext context, {bool isRefresh = false}) async {
  try {
    await Provider.of<PushNotificationService>(context, listen: false)
        .fetchPusherCredential(context: context)
        .timeout(const Duration(seconds: 4));
  } catch (e) {
    debugPrint('runAtHome fetchPusherCredential non-fatal: $e');
  }

  try {
    Provider.of<SliderService>(context, listen: false).loadSlider();
  } catch (e) {
    debugPrint('runAtHome loadSlider non-fatal: $e');
  }

  try {
    Provider.of<CategoryService>(context, listen: false)
        .fetchCategory(isRefresh: isRefresh);
  } catch (e) {
    debugPrint('runAtHome fetchCategory non-fatal: $e');
  }

  try {
    Provider.of<TopRatedServicesSerivce>(context, listen: false)
        .fetchTopService();
  } catch (e) {
    debugPrint('runAtHome fetchTopService non-fatal: $e');
  }

  try {
    Provider.of<RecentServicesService>(context, listen: false)
        .fetchRecentService();
  } catch (e) {
    debugPrint('runAtHome fetchRecentService non-fatal: $e');
  }

  try {
    Provider.of<RecentJobsService>(context, listen: false)
        .fetchRecentJobs(context);
  } catch (e) {
    debugPrint('runAtHome fetchRecentJobs non-fatal: $e');
  }

  try {
    Provider.of<ProfileService>(context, listen: false).getProfileDetails();
  } catch (e) {
    debugPrint('runAtHome getProfileDetails non-fatal: $e');
  }

  try {
    Provider.of<PermissionsService>(context, listen: false)
        .fetchUserPermissions(context);
  } catch (e) {
    debugPrint('runAtHome fetchUserPermissions non-fatal: $e');
  }
}
