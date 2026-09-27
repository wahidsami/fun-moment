// ignore_for_file: avoid_print, prefer_typing_uninitialized_variables

import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/view/tabs/orders/order_details_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';

/// Top-level background message handler for FCM
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
  print('FCM background message received: ${message.messageId}');
}

/// Global navigator key for deep-link navigation
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

class PushNotificationService with ChangeNotifier {
  // Singleton pattern for easy service access
  static final PushNotificationService _instance =
      PushNotificationService._internal();
  factory PushNotificationService() => _instance;
  PushNotificationService._internal();

  final FirebaseMessaging _fcm = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  bool _initialized = false;
  String? _fcmToken;

  // Notification center state
  List<dynamic> notifications = [];
  int unreadCount = 0;
  bool isLoading = false;
  int currentPage = 1;
  bool hasMore = true;

  // Backward compatibility fields for legacy Pusher references
  bool pusherCredentialLoaded = false;
  var apiKey;
  var secret;
  var pusherToken;
  var pusherApiUrl;
  var pusherCluster;
  var pusherInstance;

  /// Initialize Firebase Messaging, local notifications, permission and listeners
  Future<void> initializeFCM() async {
    if (_initialized) return;
    _initialized = true;

    try {
      // 1. Request notification permission (Android 13+ & iOS)
      NotificationSettings settings = await _fcm.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        provisional: false,
      );

      print('FCM permission authorizationStatus: ${settings.authorizationStatus}');

      // 2. Initialize Flutter Local Notifications for foreground heads-up display
      await _initLocalNotifications();

      // 3. Retrieve and register FCM device token with backend
      await retrieveAndRegisterToken();

      // 4. Listen to token refresh
      _fcm.onTokenRefresh.listen((newToken) {
        print('FCM token refreshed: $newToken');
        _fcmToken = newToken;
        registerDeviceTokenWithBackend(newToken);
      });

      // 5. Handle foreground FCM messages
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        print('FCM message received in foreground: ${message.notification?.title}');
        _showForegroundNotification(message);
        unreadCount++;
        notifyListeners();
        fetchUnreadCount();
      });

      // 6. Handle notification tap when app opened from background
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        print('FCM message opened app from background: ${message.data}');
        handleNotificationTap(message.data);
      });

      // 7. Check if app was opened from terminated state via notification tap
      RemoteMessage? initialMessage = await _fcm.getInitialMessage();
      if (initialMessage != null) {
        print('FCM app opened from terminated state: ${initialMessage.data}');
        Future.delayed(const Duration(milliseconds: 1200), () {
          handleNotificationTap(initialMessage.data);
        });
      }

      // 8. Fetch initial unread count if authenticated
      fetchUnreadCount();
    } catch (e) {
      print('PushNotificationService initializeFCM error: $e');
    }
  }

  /// Initialize local notification channels
  Future<void> _initLocalNotifications() async {
    const AndroidInitializationSettings androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');

    const InitializationSettings initSettings = InitializationSettings(
      android: androidSettings,
    );

    await _localNotifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) {
        if (response.payload != null && response.payload!.isNotEmpty) {
          try {
            final Map<String, dynamic> data = jsonDecode(response.payload!);
            handleNotificationTap(data);
          } catch (e) {
            print('Error parsing local notification payload: $e');
          }
        }
      },
    );

    // Create high importance Android notification channel
    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      'high_importance_channel',
      'Order & Booking Notifications',
      description: 'Used for important booking alerts and status updates.',
      importance: Importance.max,
      playSound: true,
      enableVibration: true,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
  }

  /// Show foreground notification using flutter_local_notifications
  Future<void> _showForegroundNotification(RemoteMessage message) async {
    final notification = message.notification;
    final data = message.data;

    String title = notification?.title ?? data['title'] ?? 'Fun Moments Alert';
    String body = notification?.body ?? data['body'] ?? data['order_message'] ?? '';

    const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'high_importance_channel',
      'Order & Booking Notifications',
      channelDescription: 'Used for important booking alerts and status updates.',
      importance: Importance.max,
      priority: Priority.high,
      playSound: true,
      enableVibration: true,
      icon: '@mipmap/ic_launcher',
    );

    const NotificationDetails platformDetails =
        NotificationDetails(android: androidDetails);

    int notificationId = DateTime.now().millisecondsSinceEpoch ~/ 1000;

    await _localNotifications.show(
      notificationId,
      title,
      body,
      platformDetails,
      payload: jsonEncode(data),
    );
  }

  /// Deep link handler for tapped notifications
  void handleNotificationTap(Map<String, dynamic> data) {
    print('Handling notification tap with data: $data');
    final rawOrderId = data['order_id'];
    if (rawOrderId != null) {
      final orderId = int.tryParse(rawOrderId.toString()) ?? rawOrderId;
      navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (context) => OrderDetailsPage(orderId: orderId),
        ),
      );
    }
  }

  /// Retrieve current FCM token and send to backend
  Future<void> retrieveAndRegisterToken() async {
    try {
      String? token = await _fcm.getToken();
      if (token != null && token.isNotEmpty) {
        _fcmToken = token;
        print('FCM Token retrieved: $token');
        await registerDeviceTokenWithBackend(token);
      }
    } catch (e) {
      print('Failed retrieving FCM token: $e');
    }
  }

  /// Send device token to backend server
  Future<bool> registerDeviceTokenWithBackend(String token) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var authToken = prefs.getString('token');
    if (authToken == null || authToken.isEmpty) {
      print('User not authenticated, skipping backend device token registration');
      return false;
    }

    try {
      var header = {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Authorization": "Bearer $authToken",
      };

      String deviceType = defaultTargetPlatform == TargetPlatform.iOS
          ? 'ios'
          : defaultTargetPlatform == TargetPlatform.android
              ? 'android'
              : 'web';

      var body = jsonEncode({
        "device_token": token,
        "device_type": deviceType,
      });

      var response = await http.post(
        Uri.parse('$baseApi/user/device-token'),
        headers: header,
        body: body,
      );

      print('registerDeviceToken response: ${response.statusCode} - ${response.body}');
      return response.statusCode == 200 || response.statusCode == 201;
    } catch (e) {
      print('Error registering device token with backend: $e');
      return false;
    }
  }

  /// Remove device token on logout
  Future<void> removeDeviceTokenFromBackend() async {
    if (_fcmToken == null || _fcmToken!.isEmpty) return;

    SharedPreferences prefs = await SharedPreferences.getInstance();
    var authToken = prefs.getString('token');
    if (authToken == null) return;

    try {
      var header = {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Authorization": "Bearer $authToken",
      };

      await http.delete(
        Uri.parse('$baseApi/user/device-token'),
        headers: header,
        body: jsonEncode({"device_token": _fcmToken}),
      );
    } catch (e) {
      print('Error removing device token on logout: $e');
    }
  }

  // ==========================================
  // NOTIFICATION CENTER BACKEND API CALLS
  // ==========================================

  /// Fetch notifications list from backend API
  Future<void> fetchNotifications({bool refresh = false}) async {
    if (refresh) {
      currentPage = 1;
      hasMore = true;
      notifications.clear();
    }

    if (!hasMore && !refresh) return;

    SharedPreferences prefs = await SharedPreferences.getInstance();
    var authToken = prefs.getString('token');
    if (authToken == null) return;

    isLoading = true;
    notifyListeners();

    try {
      var header = {
        "Accept": "application/json",
        "Authorization": "Bearer $authToken",
      };

      var response = await http.get(
        Uri.parse('$baseApi/user/notifications?page=$currentPage'),
        headers: header,
      );

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          final List<dynamic> items = data['notifications']['data'] ?? [];
          final int lastPage = data['notifications']['last_page'] ?? 1;

          if (refresh) {
            notifications = items;
          } else {
            notifications.addAll(items);
          }

          unreadCount = data['unread_count'] ?? 0;
          hasMore = currentPage < lastPage;
          if (hasMore) currentPage++;
        }
      }
    } catch (e) {
      print('fetchNotifications error: $e');
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  /// Fetch unread notifications count for badge
  Future<void> fetchUnreadCount() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var authToken = prefs.getString('token');
    if (authToken == null) return;

    try {
      var header = {
        "Accept": "application/json",
        "Authorization": "Bearer $authToken",
      };

      var response = await http.get(
        Uri.parse('$baseApi/user/notifications/unread-count'),
        headers: header,
      );

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          unreadCount = data['unread_count'] ?? 0;
          notifyListeners();
        }
      }
    } catch (e) {
      print('fetchUnreadCount error: $e');
    }
  }

  /// Mark single notification as read
  Future<void> markAsRead(String notificationId) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var authToken = prefs.getString('token');
    if (authToken == null) return;

    // Optimistic UI update
    for (var n in notifications) {
      if (n['id'] == notificationId) {
        n['is_read'] = true;
        break;
      }
    }
    if (unreadCount > 0) unreadCount--;
    notifyListeners();

    try {
      var header = {
        "Accept": "application/json",
        "Authorization": "Bearer $authToken",
      };

      var response = await http.post(
        Uri.parse('$baseApi/user/notifications/$notificationId/read'),
        headers: header,
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        unreadCount = data['unread_count'] ?? unreadCount;
        notifyListeners();
      }
    } catch (e) {
      print('markAsRead error: $e');
    }
  }

  /// Mark all notifications as read
  Future<void> markAllAsRead() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var authToken = prefs.getString('token');
    if (authToken == null) return;

    // Optimistic UI update
    for (var n in notifications) {
      n['is_read'] = true;
    }
    unreadCount = 0;
    notifyListeners();

    try {
      var header = {
        "Accept": "application/json",
        "Authorization": "Bearer $authToken",
      };

      await http.post(
        Uri.parse('$baseApi/user/notifications/mark-all-read'),
        headers: header,
      );
    } catch (e) {
      print('markAllAsRead error: $e');
    }
  }

  // ==========================================
  // BACKWARD COMPATIBILITY STUBS
  // ==========================================

  Future<bool> fetchPusherCredential({context}) async {
    // Decoupled from LiveChat Pusher Beams.
    // FCM is the authoritative push transport.
    // Call token registration instead.
    await retrieveAndRegisterToken();
    return true;
  }

  sendNotificationToSeller(BuildContext context,
      {required sellerId,
      required title,
      required body,
      type = 'notification'}) async {
    // Server-side Laravel handles all order notifications automatically.
    print('Notification dispatched server-side for sellerId: $sellerId');
  }
}
