import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:funmoments/model/shedule_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/view/utils/others_helper.dart';

class SheduleService with ChangeNotifier {
  var schedules;

  int totalDay = 0;

  bool isloading = true;

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  fetchShedule(sellerId, selectedWeek, {int? serviceId, DateTime? date}) async {
    setLoadingTrue();
    var connection = await checkConnection();
    if (connection) {
      //internet connection is on
      var header = {
        "Accept": "application/json",
      };

      final baseUri =
          Uri.parse('$baseApi/service-list/service-schedule/$selectedWeek/$sellerId');
      final queryParams = <String, String>{};
      if (serviceId != null) {
        queryParams['service_id'] = serviceId.toString();
      }
      if (date != null) {
        queryParams['date'] = DateFormat('yyyy-MM-dd').format(date);
      }
      final requestUri = queryParams.isNotEmpty
          ? baseUri.replace(queryParameters: queryParams)
          : baseUri;

      var response = await http.get(requestUri, headers: header);

      if (response.statusCode == 200 && response.body.contains('day')) {
        var data = SheduleModel.fromJson(jsonDecode(response.body));
        totalDay = data.day.totalDay ?? 0;
        schedules = data;

        notifyListeners();
        setLoadingFalse();
      } else {
        schedules = 'nothing';
        setLoadingFalse();
        notifyListeners();
      }
    }
  }
}
