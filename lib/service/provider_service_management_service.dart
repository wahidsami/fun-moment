import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ProviderServiceItem {
  final int id;
  final String title;
  final num price;
  final String? imageUrl;
  final int isServiceOn;
  final int? reviewsCount;
  final int? pendingOrderCount;
  final int? completeOrderCount;
  final int? cancelOrderCount;
  final int? view;

  ProviderServiceItem({
    required this.id,
    required this.title,
    required this.price,
    this.imageUrl,
    required this.isServiceOn,
    this.reviewsCount,
    this.pendingOrderCount,
    this.completeOrderCount,
    this.cancelOrderCount,
    this.view,
  });

  factory ProviderServiceItem.fromJson(Map<String, dynamic> json, String? imgUrl) {
    return ProviderServiceItem(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      title: json['title']?.toString() ?? '',
      price: json['price'] is num ? json['price'] : (num.tryParse(json['price']?.toString() ?? '') ?? 0),
      imageUrl: imgUrl,
      isServiceOn: json['is_service_on'] is int ? json['is_service_on'] : (int.tryParse(json['is_service_on']?.toString() ?? '') ?? 1),
      reviewsCount: json['reviews_count'] is int ? json['reviews_count'] : int.tryParse(json['reviews_count']?.toString() ?? ''),
      pendingOrderCount: json['pending_order_count'] is int ? json['pending_order_count'] : int.tryParse(json['pending_order_count']?.toString() ?? ''),
      completeOrderCount: json['complete_order_count'] is int ? json['complete_order_count'] : int.tryParse(json['complete_order_count']?.toString() ?? ''),
      cancelOrderCount: json['cancel_order_count'] is int ? json['cancel_order_count'] : int.tryParse(json['cancel_order_count']?.toString() ?? ''),
      view: json['view'] is int ? json['view'] : int.tryParse(json['view']?.toString() ?? ''),
    );
  }
}

class ProviderDashboardData {
  final int pendingOrders;
  final int completedOrders;
  final num totalWithdrawn;
  final num remainingBalance;

  ProviderDashboardData({
    this.pendingOrders = 0,
    this.completedOrders = 0,
    this.totalWithdrawn = 0,
    this.remainingBalance = 0,
  });

  factory ProviderDashboardData.fromJson(Map<String, dynamic> json) {
    return ProviderDashboardData(
      pendingOrders: json['pending_order'] is int ? json['pending_order'] : (int.tryParse(json['pending_order']?.toString() ?? '') ?? 0),
      completedOrders: json['completed_order'] is int ? json['completed_order'] : (int.tryParse(json['completed_order']?.toString() ?? '') ?? 0),
      totalWithdrawn: json['total_withdrawn_money'] is num ? json['total_withdrawn_money'] : (num.tryParse(json['total_withdrawn_money']?.toString() ?? '') ?? 0),
      remainingBalance: json['remaining_balance'] is num ? json['remaining_balance'] : (num.tryParse(json['remaining_balance']?.toString() ?? '') ?? 0),
    );
  }
}

class ProviderServiceManagementService with ChangeNotifier {
  List<ProviderServiceItem> services = [];
  bool isLoading = false;
  bool isCreating = false;
  bool isToggling = false;
  ProviderDashboardData? dashboardData;

  void setLoading(bool val) {
    isLoading = val;
    notifyListeners();
  }

  Future<String?> _getToken() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    return prefs.getString('token');
  }

  Future<void> fetchDashboardInfo() async {
    try {
      final token = await _getToken();
      if (token == null) return;

      final url = Uri.parse('$baseApi/seller/dashboard-info');
      final res = await http.post(url, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200 || res.statusCode == 201) {
        final body = jsonDecode(res.body);
        if (body is Map<String, dynamic>) {
          dashboardData = ProviderDashboardData.fromJson(body);
          notifyListeners();
        }
      }
    } catch (e) {
      debugPrint('fetchDashboardInfo error: $e');
    }
  }

  Future<bool> fetchMyServices({bool isRefresh = false}) async {
    final connected = await checkConnection();
    if (!connected) return false;

    final token = await _getToken();
    if (token == null) return false;

    if (!isRefresh && services.isNotEmpty) {
      return true;
    }

    setLoading(true);

    try {
      final url = Uri.parse('$baseApi/seller/service/my-services');
      final res = await http.get(url, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200 || res.statusCode == 201) {
        final body = jsonDecode(res.body);
        final myServicesData = body['my_services'];
        final serviceImageList = body['service_image'] as List? ?? [];

        List<ProviderServiceItem> loaded = [];
        if (myServicesData is Map && myServicesData['data'] is List) {
          final items = myServicesData['data'] as List;
          for (int i = 0; i < items.length; i++) {
            String? imgUrl;
            if (i < serviceImageList.length && serviceImageList[i] is Map) {
              imgUrl = serviceImageList[i]['img_url']?.toString();
            }
            loaded.add(ProviderServiceItem.fromJson(items[i], imgUrl));
          }
        }
        services = loaded;
        setLoading(false);
        return true;
      } else {
        setLoading(false);
        return false;
      }
    } catch (e) {
      debugPrint('fetchMyServices error: $e');
      setLoading(false);
      return false;
    }
  }

  Future<bool> toggleServiceStatus(int serviceId) async {
    final token = await _getToken();
    if (token == null) return false;

    isToggling = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/service/on-off/$serviceId');
      final res = await http.post(url, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      isToggling = false;
      if (res.statusCode == 200 || res.statusCode == 201) {
        // Toggle locally
        final index = services.indexWhere((s) => s.id == serviceId);
        if (index != -1) {
          final current = services[index];
          final updated = ProviderServiceItem(
            id: current.id,
            title: current.title,
            price: current.price,
            imageUrl: current.imageUrl,
            isServiceOn: current.isServiceOn == 1 ? 0 : 1,
            reviewsCount: current.reviewsCount,
            pendingOrderCount: current.pendingOrderCount,
            completeOrderCount: current.completeOrderCount,
            cancelOrderCount: current.cancelOrderCount,
            view: current.view,
          );
          services[index] = updated;
          notifyListeners();
        }
        return true;
      }
      notifyListeners();
      return false;
    } catch (e) {
      isToggling = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> deleteService(int serviceId, BuildContext context) async {
    final token = await _getToken();
    if (token == null) return false;

    try {
      final url = Uri.parse('$baseApi/seller/service/delete/service-with-all-attributes/$serviceId');
      final res = await http.post(url, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200 || res.statusCode == 201) {
        services.removeWhere((s) => s.id == serviceId);
        notifyListeners();
        OthersHelper().showToast('Service deleted successfully', Colors.green);
        return true;
      } else {
        OthersHelper().showToast('Could not delete service', Colors.black);
        return false;
      }
    } catch (e) {
      OthersHelper().showToast('Error deleting service', Colors.black);
      return false;
    }
  }

  Future<bool> createService({
    required BuildContext context,
    required String title,
    required String description,
    required int categoryId,
    required double price,
    int? subcategoryId,
  }) async {
    final token = await _getToken();
    if (token == null) return false;

    isCreating = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/service/add-service');
      final Map<String, String> body = {
        'title': title,
        'description': description,
        'category_id': categoryId.toString(),
        if (subcategoryId != null) 'subcategory_id': subcategoryId.toString(),
      };

      final res = await http.post(url, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      }, body: body);

      isCreating = false;
      notifyListeners();

      if (res.statusCode == 200 || res.statusCode == 201) {
        OthersHelper().showToast('Service submitted for admin review!', Colors.green);
        await fetchMyServices(isRefresh: true);
        return true;
      } else {
        final parsed = jsonDecode(res.body);
        final msg = parsed['message'] ?? parsed['msg'] ?? 'Could not create service';
        OthersHelper().showToast(msg.toString(), Colors.black);
        return false;
      }
    } catch (e) {
      isCreating = false;
      notifyListeners();
      OthersHelper().showToast('Connection error: $e', Colors.black);
      return false;
    }
  }
}
