import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ProviderTimeSlot {
  final int id;
  final int dayId;
  final String schedule;
  final int status;

  ProviderTimeSlot({
    required this.id,
    required this.dayId,
    required this.schedule,
    required this.status,
  });

  factory ProviderTimeSlot.fromJson(Map<String, dynamic> json) {
    return ProviderTimeSlot(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      dayId: json['day_id'] is int ? json['day_id'] : int.tryParse(json['day_id']?.toString() ?? '') ?? 0,
      schedule: json['schedule']?.toString() ?? '',
      status: json['status'] is int ? json['status'] : int.tryParse(json['status']?.toString() ?? '') ?? 1,
    );
  }
}

class ProviderWorkingDay {
  final int id;
  final String day;
  int status;
  final int totalDay;
  List<ProviderTimeSlot> schedules;

  ProviderWorkingDay({
    required this.id,
    required this.day,
    required this.status,
    required this.totalDay,
    required this.schedules,
  });

  bool get isEnabled => status == 1;

  String get shortName {
    final d = day.trim();
    return d.length > 3 ? d.substring(0, 3) : d;
  }

  String get fullName {
    switch (shortName.toLowerCase()) {
      case 'sun': return 'Sunday';
      case 'mon': return 'Monday';
      case 'tue': return 'Tuesday';
      case 'wed': return 'Wednesday';
      case 'thu': return 'Thursday';
      case 'fri': return 'Friday';
      case 'sat': return 'Saturday';
      default: return day;
    }
  }

  String get arabicName {
    switch (shortName.toLowerCase()) {
      case 'sun': return 'الأحد';
      case 'mon': return 'الاثنين';
      case 'tue': return 'الثلاثاء';
      case 'wed': return 'الأربعاء';
      case 'thu': return 'الخميس';
      case 'fri': return 'الجمعة';
      case 'sat': return 'السبت';
      default: return day;
    }
  }

  factory ProviderWorkingDay.fromJson(Map<String, dynamic> json) {
    var rawSchedules = json['schedules'] as List? ?? [];
    List<ProviderTimeSlot> parsedSlots = [];
    for (var s in rawSchedules) {
      if (s is Map<String, dynamic>) {
        parsedSlots.add(ProviderTimeSlot.fromJson(s));
      }
    }

    return ProviderWorkingDay(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      day: json['day']?.toString() ?? '',
      status: json['status'] is int ? json['status'] : int.tryParse(json['status']?.toString() ?? '') ?? 0,
      totalDay: json['total_day'] is int ? json['total_day'] : int.tryParse(json['total_day']?.toString() ?? '') ?? 7,
      schedules: parsedSlots,
    );
  }
}

class ProviderAvailabilityService with ChangeNotifier {
  List<ProviderWorkingDay> days = [];
  bool isInitialized = false;
  bool isLoading = false;
  bool isSaving = false;
  String? errorMessage;

  bool get hasError => errorMessage != null;

  void setLoading(bool val) {
    isLoading = val;
    notifyListeners();
  }

  void resetState() {
    days = [];
    isInitialized = false;
    isLoading = false;
    isSaving = false;
    errorMessage = null;
    notifyListeners();
  }

  Future<String?> _getToken() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    return prefs.getString('token');
  }

  // Returns all standard 7 days sorted Sun -> Sat
  static const List<String> standardDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  /// Fetch working days and their time slots.
  /// Pass [serviceId] to retrieve availability scoped to a specific service.
  Future<void> fetchDaysAndSchedules({bool showLoader = true, int? serviceId}) async {
    final connected = await checkConnection();
    if (!connected) {
      errorMessage = 'No internet connection';
      isInitialized = true;
      if (showLoader) isLoading = false;
      notifyListeners();
      return;
    }

    final token = await _getToken();
    if (token == null) {
      errorMessage = 'User not authenticated';
      isInitialized = true;
      if (showLoader) isLoading = false;
      notifyListeners();
      return;
    }

    if (showLoader) {
      isLoading = true;
      errorMessage = null;
      notifyListeners();
    }

    try {
      String urlStr = '$baseApi/seller/schedule-days-list';
      if (serviceId != null) {
        urlStr += '?service_id=$serviceId';
      }
      final url = Uri.parse(urlStr);
      final res = await http.get(url, headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body is List) {
          final loaded = body.map((item) => ProviderWorkingDay.fromJson(item as Map<String, dynamic>)).toList();

          // Re-order strictly Sun -> Sat
          final order = {'Sun': 1, 'Mon': 2, 'Tue': 3, 'Wed': 4, 'Thu': 5, 'Fri': 6, 'Sat': 7};
          loaded.sort((a, b) => (order[a.shortName] ?? 99).compareTo(order[b.shortName] ?? 99));
          days = loaded;
          errorMessage = null;
        } else {
          if (days.isEmpty) {
            errorMessage = 'Unexpected response format';
          }
        }
      } else {
        if (days.isEmpty) {
          errorMessage = 'Failed to load availability (${res.statusCode})';
        }
      }
    } catch (e) {
      debugPrint('fetchDaysAndSchedules error: $e');
      if (days.isEmpty) {
        errorMessage = 'Failed to load availability: $e';
      }
    } finally {
      isInitialized = true;
      if (showLoader) isLoading = false;
      notifyListeners();
    }
  }

  /// Create (or reactivate) a working day.
  /// Pass [serviceId] to scope the day to a specific service.
  Future<bool> createWorkingDay(String dayName, {int? serviceId}) async {
    if (isSaving || isLoading) return false;

    final token = await _getToken();
    if (token == null) return false;

    isSaving = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/create-day');
      final bodyMap = <String, String>{'day': dayName};
      if (serviceId != null) bodyMap['service_id'] = serviceId.toString();

      final res = await http.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: bodyMap,
      );

      isSaving = false;
      if (res.statusCode == 200 || res.statusCode == 201) {
        await fetchDaysAndSchedules(showLoader: false, serviceId: serviceId);
        return true;
      }
      notifyListeners();
      return false;
    } catch (e) {
      isSaving = false;
      notifyListeners();
      return false;
    }
  }

  /// Toggle a working day on/off.
  /// Pass [serviceId] so the backend scopes the lookup correctly.
  Future<bool> toggleWorkingDay(int dayId, {int? serviceId}) async {
    if (isSaving || isLoading) return false;
    if (dayId <= 0) return false;

    final token = await _getToken();
    if (token == null) return false;

    isSaving = true;
    final idx = days.indexWhere((d) => d.id == dayId);
    final prevStatus = idx != -1 ? days[idx].status : null;

    // Optimistic toggle
    if (idx != -1) {
      days[idx].status = days[idx].status == 1 ? 0 : 1;
      notifyListeners();
    }

    try {
      final url = Uri.parse('$baseApi/seller/toggle-day');
      final bodyMap = <String, String>{'id': dayId.toString()};
      if (serviceId != null) bodyMap['service_id'] = serviceId.toString();

      final res = await http.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: bodyMap,
      );

      isSaving = false;
      if (res.statusCode == 200 || res.statusCode == 201) {
        // Backend returned authoritative state
        try {
          final data = jsonDecode(res.body);
          if (data is Map && data['day'] != null && data['day']['status'] != null) {
            final backendStatus = int.tryParse(data['day']['status'].toString()) ?? days[idx].status;
            if (idx != -1) {
              days[idx].status = backendStatus;
            }
          }
        } catch (_) {}
        notifyListeners();
        return true;
      }

      // Rollback on failure
      if (idx != -1 && prevStatus != null) {
        days[idx].status = prevStatus;
      }
      notifyListeners();
      OthersHelper().showToast('Could not update working day', Colors.black);
      return false;
    } catch (e) {
      debugPrint('toggleWorkingDay error: $e');
      if (idx != -1 && prevStatus != null) {
        days[idx].status = prevStatus;
      }
      isSaving = false;
      notifyListeners();
      OthersHelper().showToast('Error updating working day', Colors.black);
      return false;
    }
  }

  /// Add a time slot to a specific day.
  /// Pass [serviceId] to scope the slot to a specific service.
  Future<bool> addTimeSlot({
    required int dayId,
    required String schedule,
    bool allDays = false,
    int? serviceId,
  }) async {
    if (isSaving || isLoading) return false;
    if (dayId <= 0 && !allDays) {
      OthersHelper().showToast('Please activate this day first', Colors.black);
      return false;
    }

    final token = await _getToken();
    if (token == null) return false;

    isSaving = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/schedule/create');
      final bodyMap = <String, String>{
        'day_id': dayId.toString(),
        'schedule': schedule,
        if (allDays) 'schedule_for_all_days': '1',
      };
      if (serviceId != null) bodyMap['service_id'] = serviceId.toString();

      final res = await http.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: bodyMap,
      );

      isSaving = false;
      if (res.statusCode == 200 || res.statusCode == 201) {
        OthersHelper().showToast('Time slot added successfully', Colors.green);
        await fetchDaysAndSchedules(showLoader: false, serviceId: serviceId);
        return true;
      } else {
        try {
          final parsed = jsonDecode(res.body);
          final msg = parsed['message'] ?? parsed['msg'] ?? 'Could not add time slot';
          OthersHelper().showToast(msg.toString(), Colors.black);
        } catch (_) {
          OthersHelper().showToast('Failed to add time slot', Colors.black);
        }
        notifyListeners();
        return false;
      }
    } catch (e) {
      isSaving = false;
      notifyListeners();
      OthersHelper().showToast('Error: $e', Colors.black);
      return false;
    }
  }

  /// Delete a time slot by its ID.
  Future<bool> deleteTimeSlot(int slotId, {int? serviceId}) async {
    if (isSaving || isLoading) return false;
    final token = await _getToken();
    if (token == null) return false;

    isSaving = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/schedule/delete');
      final res = await http.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {'id': slotId.toString()},
      );

      isSaving = false;
      if (res.statusCode == 200 || res.statusCode == 201) {
        // Remove locally
        for (var day in days) {
          day.schedules.removeWhere((s) => s.id == slotId);
        }
        notifyListeners();
        OthersHelper().showToast('Time slot deleted', Colors.green);
        return true;
      } else {
        notifyListeners();
        OthersHelper().showToast('Could not delete time slot', Colors.black);
        return false;
      }
    } catch (e) {
      isSaving = false;
      notifyListeners();
      OthersHelper().showToast('Error deleting time slot', Colors.black);
      return false;
    }
  }
}
