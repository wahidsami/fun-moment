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
  bool isLoading = false;
  bool isSaving = false;

  void setLoading(bool val) {
    isLoading = val;
    notifyListeners();
  }

  Future<String?> _getToken() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    return prefs.getString('token');
  }

  // Returns all standard 7 days sorted Sun -> Sat
  static const List<String> standardDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  Future<void> fetchDaysAndSchedules({bool showLoader = true}) async {
    final connected = await checkConnection();
    if (!connected) return;

    final token = await _getToken();
    if (token == null) return;

    if (showLoader) setLoading(true);

    try {
      final url = Uri.parse('$baseApi/seller/schedule-days-list');
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
        }
      }
    } catch (e) {
      debugPrint('fetchDaysAndSchedules error: $e');
    } finally {
      if (showLoader) setLoading(false);
    }
  }

  Future<bool> createWorkingDay(String dayName) async {
    final token = await _getToken();
    if (token == null) return false;

    isSaving = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/create-day');
      final res = await http.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {'day': dayName},
      );

      isSaving = false;
      if (res.statusCode == 200 || res.statusCode == 201) {
        await fetchDaysAndSchedules(showLoader: false);
        return true;
      }
      return false;
    } catch (e) {
      isSaving = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> toggleWorkingDay(int dayId) async {
    final token = await _getToken();
    if (token == null) return false;

    try {
      final url = Uri.parse('$baseApi/seller/toggle-day');
      final res = await http.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {'id': dayId.toString()},
      );

      if (res.statusCode == 200 || res.statusCode == 201) {
        // Toggle locally
        final idx = days.indexWhere((d) => d.id == dayId);
        if (idx != -1) {
          days[idx].status = days[idx].status == 1 ? 0 : 1;
          notifyListeners();
        }
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('toggleWorkingDay error: $e');
      return false;
    }
  }

  Future<bool> addTimeSlot({
    required int dayId,
    required String schedule,
    bool allDays = false,
  }) async {
    final token = await _getToken();
    if (token == null) return false;

    isSaving = true;
    notifyListeners();

    try {
      final url = Uri.parse('$baseApi/seller/schedule/create');
      final body = {
        'day_id': dayId.toString(),
        'schedule': schedule,
        if (allDays) 'schedule_for_all_days': '1',
      };

      final res = await http.post(
        url,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: body,
      );

      isSaving = false;
      if (res.statusCode == 200 || res.statusCode == 201) {
        OthersHelper().showToast('Time slot added successfully', Colors.green);
        await fetchDaysAndSchedules(showLoader: false);
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

  Future<bool> deleteTimeSlot(int slotId) async {
    final token = await _getToken();
    if (token == null) return false;

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

      if (res.statusCode == 200 || res.statusCode == 201) {
        // Remove locally
        for (var day in days) {
          day.schedules.removeWhere((s) => s.id == slotId);
        }
        notifyListeners();
        OthersHelper().showToast('Time slot deleted', Colors.green);
        return true;
      } else {
        OthersHelper().showToast('Could not delete time slot', Colors.black);
        return false;
      }
    } catch (e) {
      OthersHelper().showToast('Error deleting time slot', Colors.black);
      return false;
    }
  }
}
