import 'dart:convert';
import 'dart:io';
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
  final int status;
  final int? reviewsCount;
  final int? pendingOrderCount;
  final int? completeOrderCount;
  final int? cancelOrderCount;
  final int? view;
  final String? description;
  final int? categoryId;
  final int? subcategoryId;
  final int? deliveryDays;

  ProviderServiceItem({
    required this.id,
    required this.title,
    required this.price,
    this.imageUrl,
    required this.isServiceOn,
    this.status = 0,
    this.reviewsCount,
    this.pendingOrderCount,
    this.completeOrderCount,
    this.cancelOrderCount,
    this.view,
    this.description,
    this.categoryId,
    this.subcategoryId,
    this.deliveryDays,
  });

  bool get isPendingApproval => status == 0;
  bool get isApproved => status == 1;

  factory ProviderServiceItem.fromJson(Map<String, dynamic> json, String? imgUrl) {
    return ProviderServiceItem(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      title: json['title']?.toString() ?? '',
      price: json['price'] is num ? json['price'] : (num.tryParse(json['price']?.toString() ?? '') ?? 0),
      imageUrl: imgUrl,
      isServiceOn: json['is_service_on'] is int ? json['is_service_on'] : (int.tryParse(json['is_service_on']?.toString() ?? '') ?? 1),
      status: json['status'] is int ? json['status'] : (int.tryParse(json['status']?.toString() ?? '') ?? 0),
      reviewsCount: json['reviews_count'] is int ? json['reviews_count'] : int.tryParse(json['reviews_count']?.toString() ?? ''),
      pendingOrderCount: json['pending_order_count'] is int ? json['pending_order_count'] : int.tryParse(json['pending_order_count']?.toString() ?? ''),
      completeOrderCount: json['complete_order_count'] is int ? json['complete_order_count'] : int.tryParse(json['complete_order_count']?.toString() ?? ''),
      cancelOrderCount: json['cancel_order_count'] is int ? json['cancel_order_count'] : int.tryParse(json['cancel_order_count']?.toString() ?? ''),
      view: json['view'] is int ? json['view'] : int.tryParse(json['view']?.toString() ?? ''),
      description: json['description']?.toString(),
      categoryId: json['category_id'] is int ? json['category_id'] : int.tryParse(json['category_id']?.toString() ?? ''),
      subcategoryId: json['subcategory_id'] is int ? json['subcategory_id'] : int.tryParse(json['subcategory_id']?.toString() ?? ''),
      deliveryDays: json['delivery_days'] is int ? json['delivery_days'] : int.tryParse(json['delivery_days']?.toString() ?? ''),
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
  bool isUpdating = false;
  bool isToggling = false;
  ProviderDashboardData? dashboardData;

  void setLoading(bool val) {
    isLoading = val;
    notifyListeners();
  }

  void resetState() {
    services = [];
    isLoading = false;
    isCreating = false;
    isUpdating = false;
    isToggling = false;
    dashboardData = null;
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

  Future<Map<String, dynamic>?> fetchServiceDetails(int serviceId) async {
    final token = await _getToken();
    if (token == null) return null;

    try {
      final url = Uri.parse('$baseApi/seller/service/details/$serviceId');
      final res = await http.get(url, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200 || res.statusCode == 201) {
        final body = jsonDecode(res.body);
        if (body is Map<String, dynamic>) {
          return body;
        }
      }
    } catch (e) {
      debugPrint('fetchServiceDetails error: $e');
    }
    return null;
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
        final index = services.indexWhere((s) => s.id == serviceId);
        if (index != -1) {
          final current = services[index];
          final updated = ProviderServiceItem(
            id: current.id,
            title: current.title,
            price: current.price,
            imageUrl: current.imageUrl,
            isServiceOn: current.isServiceOn == 1 ? 0 : 1,
            status: current.status,
            reviewsCount: current.reviewsCount,
            pendingOrderCount: current.pendingOrderCount,
            completeOrderCount: current.completeOrderCount,
            cancelOrderCount: current.cancelOrderCount,
            view: current.view,
            description: current.description,
            categoryId: current.categoryId,
            subcategoryId: current.subcategoryId,
            deliveryDays: current.deliveryDays,
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
    int? deliveryDays,
    File? imageFile,
    List<Map<String, dynamic>>? includes,
  }) async {
    final token = await _getToken();
    if (token == null) return false;

    isCreating = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/service/add-service');
      final request = http.MultipartRequest('POST', url);

      request.headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      request.fields['title'] = title;
      request.fields['description'] = description;
      request.fields['category_id'] = categoryId.toString();
      request.fields['price'] = price.toString();

      if (subcategoryId != null) {
        request.fields['subcategory_id'] = subcategoryId.toString();
      }
      if (deliveryDays != null && deliveryDays > 0) {
        request.fields['delivery_days'] = deliveryDays.toString();
      }
      if (includes != null && includes.isNotEmpty) {
        request.fields['includes'] = jsonEncode(includes);
      }

      if (imageFile != null && await imageFile.exists()) {
        request.files.add(await http.MultipartFile.fromPath('image', imageFile.path));
      }

      final streamedResponse = await request.send();
      final res = await http.Response.fromStream(streamedResponse);

      isCreating = false;
      notifyListeners();

      if (res.statusCode == 200 || res.statusCode == 201) {
        OthersHelper().showToast('Service submitted for admin review!', Colors.green);
        await fetchMyServices(isRefresh: true);
        return true;
      } else {
        _handleApiError(res);
        return false;
      }
    } catch (e) {
      isCreating = false;
      notifyListeners();
      OthersHelper().showToast('Connection error: $e', Colors.black);
      return false;
    }
  }

  Future<bool> updateService({
    required BuildContext context,
    required int serviceId,
    required String title,
    required String description,
    int? categoryId,
    double? price,
    int? subcategoryId,
    int? deliveryDays,
    File? imageFile,
    List<Map<String, dynamic>>? includes,
  }) async {
    final token = await _getToken();
    if (token == null) return false;

    isUpdating = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/service/update-service');
      final request = http.MultipartRequest('POST', url);

      request.headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      request.fields['service_id'] = serviceId.toString();
      request.fields['title'] = title;
      request.fields['description'] = description;

      if (categoryId != null) {
        request.fields['category_id'] = categoryId.toString();
      }
      if (price != null) {
        request.fields['price'] = price.toString();
      }
      if (subcategoryId != null) {
        request.fields['subcategory_id'] = subcategoryId.toString();
      }
      if (deliveryDays != null && deliveryDays > 0) {
        request.fields['delivery_days'] = deliveryDays.toString();
      }
      if (includes != null) {
        request.fields['includes'] = jsonEncode(includes);
      }

      if (imageFile != null && await imageFile.exists()) {
        request.files.add(await http.MultipartFile.fromPath('image', imageFile.path));
      }

      final streamedResponse = await request.send();
      final res = await http.Response.fromStream(streamedResponse);

      isUpdating = false;
      notifyListeners();

      if (res.statusCode == 200 || res.statusCode == 201) {
        OthersHelper().showToast('Service updated! Resubmitted for review.', Colors.green);
        await fetchMyServices(isRefresh: true);
        return true;
      } else {
        _handleApiError(res);
        return false;
      }
    } catch (e) {
      isUpdating = false;
      notifyListeners();
      OthersHelper().showToast('Connection error: $e', Colors.black);
      return false;
    }
  }

  void _handleApiError(http.Response res) {
    try {
      final parsed = jsonDecode(res.body);
      String errorMessage = '';
      if (parsed is Map && parsed['errors'] is Map) {
        final errors = parsed['errors'] as Map;
        final errorList = <String>[];
        errors.forEach((key, val) {
          if (val is List && val.isNotEmpty) {
            errorList.add(val.first.toString());
          } else if (val is String) {
            errorList.add(val);
          }
        });
        if (errorList.isNotEmpty) {
          errorMessage = errorList.join('\n');
        }
      }
      if (errorMessage.isEmpty && parsed is Map) {
        errorMessage = parsed['message']?.toString() ?? parsed['msg']?.toString() ?? 'Error occurred';
      }
      OthersHelper().showToast(errorMessage.isNotEmpty ? errorMessage : 'Server Error (${res.statusCode})', Colors.black);
    } catch (_) {
      OthersHelper().showToast('Server Error (${res.statusCode})', Colors.black);
    }
  }
}
