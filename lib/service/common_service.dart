import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform, TargetPlatform, visibleForTesting;
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
import 'package:http/http.dart' as http;

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

String removeUnderscore(dynamic value) {
  if (value == null) return '';
  return value.toString().replaceAll(RegExp('_'), ' ');
}

String removeDollar(dynamic value) {
  if (value == null) return '0';
  return value.toString().replaceAll(RegExp('[^0-9.]'), '');
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


final http.Client commonHttpClient = http.Client();
bool _isHomeBootstrapActive = false;
bool _hasQueuedRefresh = false;

@visibleForTesting
void resetHomeBootstrapState() {
  _isHomeBootstrapActive = false;
  _hasQueuedRefresh = false;
}

Future<void> runAtHome(BuildContext context, {bool isRefresh = false}) async {
  if (_isHomeBootstrapActive) {
    if (isRefresh) {
      debugPrint('[runAtHome] Bootstrap already active; queuing single refresh for post-completion');
      _hasQueuedRefresh = true;
    } else {
      debugPrint('[runAtHome] Bootstrap already active; skipping redundant invocation');
    }
    return;
  }
  _isHomeBootstrapActive = true;
  debugPrint('[runAtHome] Staged home bootstrap initiated (isRefresh: $isRefresh)');

  try {
    // ----------------------------------------------------
    // STAGE 1: CRITICAL HOME CONTENT (Category & Slider)
    // ----------------------------------------------------
    // Dispatched first with the shared HTTP client to claim
    // server worker capacity without socket contention.
    final categoryFuture = Provider.of<CategoryService>(context, listen: false)
        .fetchCategory(isRefresh: isRefresh, client: commonHttpClient);

    final sliderFuture = Provider.of<SliderService>(context, listen: false)
        .loadSlider(isRefresh: isRefresh, client: commonHttpClient);

    // Allow Category + Slider priority access to server workers.
    // Wait for both to complete, or until bounded timeout (5s) expires.
    await Future.wait([categoryFuture, sliderFuture])
        .timeout(const Duration(seconds: 5), onTimeout: () {
      debugPrint(
          '[runAtHome] Critical stage (Category & Slider) reached 5s boundary; proceeding to Stage 2');
      return [];
    });

    // ----------------------------------------------------
    // STAGE 2: SECONDARY HOME SERVICES (Bounded Batches)
    // ----------------------------------------------------
    // Secondary services run asynchronously in small batches so they
    // do not starve critical UI or delay category/slider rendering.

    // Batch 2A: Service listings
    try {
      Provider.of<TopRatedServicesSerivce>(context, listen: false)
          .fetchTopService();
    } catch (e) {
      debugPrint('[runAtHome] fetchTopService non-fatal: $e');
    }

    try {
      Provider.of<RecentServicesService>(context, listen: false)
          .fetchRecentService();
    } catch (e) {
      debugPrint('[runAtHome] fetchRecentService non-fatal: $e');
    }

    // Small delay between batches to stagger TCP connections
    await Future.delayed(const Duration(milliseconds: 200));
    if (!context.mounted) return;

    // Batch 2B: Jobs & Background services
    try {
      Provider.of<RecentJobsService>(context, listen: false)
          .fetchRecentJobs(context);
    } catch (e) {
      debugPrint('[runAtHome] fetchRecentJobs non-fatal: $e');
    }

    // Avoid duplicate profile request if already loaded or actively loading
    try {
      final profileService = Provider.of<ProfileService>(context, listen: false);
      if (profileService.profileDetails == null && !profileService.isloading) {
        profileService.getProfileDetails();
      } else {
        debugPrint(
            '[runAtHome] Profile already loaded or in-flight; skipping duplicate fetch');
      }
    } catch (e) {
      debugPrint('[runAtHome] getProfileDetails non-fatal: $e');
    }

    try {
      Provider.of<PushNotificationService>(context, listen: false)
          .fetchPusherCredential(context: context)
          .timeout(const Duration(seconds: 4))
          .catchError((e) {
        debugPrint('[runAtHome] fetchPusherCredential non-fatal: $e');
        return false;
      });
    } catch (e) {
      debugPrint('[runAtHome] fetchPusherCredential dispatch error: $e');
    }

    try {
      Provider.of<PermissionsService>(context, listen: false)
          .fetchUserPermissions(context);
    } catch (e) {
      debugPrint('[runAtHome] fetchUserPermissions non-fatal: $e');
    }
  } finally {
    _isHomeBootstrapActive = false;
    debugPrint('[runAtHome] Staged home bootstrap complete');
    if (_hasQueuedRefresh) {
      _hasQueuedRefresh = false;
      debugPrint('[runAtHome] Executing queued refresh post-completion');
      Future.microtask(() {
        try {
          if (context.mounted) {
            runAtHome(context, isRefresh: true);
          }
        } catch (_) {}
      });
    }
  }
}
