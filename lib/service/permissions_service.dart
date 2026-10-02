import 'dart:convert';

import 'package:flutter/widgets.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class PermissionsService with ChangeNotifier {
  bool jobPermission = true;
  bool subsPermission = true;
  bool chatPermission = true;
  bool walletPermission = true;

  fetchUserPermissions(BuildContext context) async {
    var header = {
      //if header type is application/json then the data should be in jsonEncode method
      "Accept": "application/json",
      "Content-Type": "application/json",
    };

    var connection = await checkConnection();
    if (!connection) return;

    try {
      var response = await http
          .get(Uri.parse('$baseApi/module-permission'), headers: header)
          .timeout(const Duration(seconds: 5));

      final decodedData = jsonDecode(response.body);

      debugPrint("permission api response ${response.body}");

      if (response.statusCode == 201 || response.statusCode == 200) {
        jobPermission = decodedData['permissions']['JobPost'];
        subsPermission = decodedData['permissions']['Subscription'];
        chatPermission = decodedData['permissions']['LiveChat'];
        walletPermission = decodedData['permissions']['Wallet'];
        notifyListeners();
      }
    } catch (e) {
      debugPrint('PermissionsService.fetchUserPermissions error: $e');
    }
  }
}
