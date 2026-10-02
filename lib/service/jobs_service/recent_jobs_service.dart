import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:funmoments/model/jobs/recent_jobs_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/view/utils/others_helper.dart';

class RecentJobsService with ChangeNotifier {
  var recentJobs;
  var recentJobsImages;

  bool isloading = false;

  setLoadingStatus(bool status) {
    isloading = status;
    notifyListeners();
  }

  fetchRecentJobs(BuildContext context) async {
    //check internet connection
    var connection = await checkConnection();
    if (!connection) return;

    if (recentJobs != null) return;

    try {
      var response = await http
          .get(
            Uri.parse('$baseApi/job/recent-jobs'),
          )
          .timeout(const Duration(seconds: 5));
      debugPrint('$baseApi/job/recent-jobs');
      if (response.statusCode == 201 || response.statusCode == 200) {
        var data = RecentJobsModel.fromJson(jsonDecode(response.body));

        recentJobs = data.recent10Jobs;
        recentJobsImages = data.jobsImage;
        notifyListeners();
      } else {
        debugPrint(response.body);
      }
    } catch (e) {
      debugPrint('RecentJobsService.fetchRecentJobs error: $e');
    }
  }
}
