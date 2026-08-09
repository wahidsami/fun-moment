import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/model/jobs/job_conversation_model.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:http/http.dart' as http;
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:shared_preferences/shared_preferences.dart';

class JobConversationService with ChangeNotifier {
  List messagesList = [];

  bool isloading = false;
  bool sendLoading = false;

  setLoadingTrue() {
    isloading = true;
    notifyListeners();
  }

  setLoadingFalse() {
    isloading = false;
    notifyListeners();
  }

  setSendLoadingTrue() {
    sendLoading = true;
    notifyListeners();
  }

  setSendLoadingFalse() {
    sendLoading = false;
    notifyListeners();
  }

  // final ImagePicker _picker = ImagePicker();
  Future pickFile() async {
    OthersHelper()
        .showToast('Only zip file is supported', ConstantColors().primaryColor);

    FilePickerResult? result = await FilePicker.platform
        .pickFiles(type: FileType.custom, allowedExtensions: ['zip']);

    if (result != null) {
      return result;
    } else {
      return null;
    }
  }

  fetchMessages({required jobRequestId}) async {
    var connection = await checkConnection();
    if (connection) {
      messagesList = [];
      setLoadingTrue();
      //if connection is ok

      SharedPreferences prefs = await SharedPreferences.getInstance();
      var token = prefs.getString('token');
      var header = {
        "Authorization": "Bearer $token",
      };
      var response = await http.get(
          Uri.parse(
              '$baseApi/user/job/request/conversation?job_request_id=$jobRequestId'),
          headers: header);
      setLoadingFalse();

      print(response.body);

      if (response.statusCode == 201 &&
          jsonDecode(response.body)['all_messages'].isNotEmpty) {
        var data = JobConversationModel.fromJson(jsonDecode(response.body));

        setMessageList(data.allMessages);
      } else {
        //Something went wrong
        print(response.body);
      }
    } else {
      OthersHelper()
          .showToast('Please check your internet connection', Colors.black);
    }
  }

  setMessageList(dataList) {
    for (int i = 0; i < dataList.length; i++) {
      messagesList.add({
        'id': dataList[i].id,
        'message': dataList[i].message,
        'notify': 'off',
        'attachment': dataList[i].attachment,
        'type': dataList[i].type,
        'imagePicked':
            false //check if this image is just got picked from device in that case we will show it from device location
      });
    }
    notifyListeners();
  }

//Send new message ======>

  sendMessage(jobRequestId, message, filePath, BuildContext context,
      {required sellerId}) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    var token = prefs.getString('token');

    var dio = Dio();
    dio.options.headers['Content-Type'] = 'multipart/form-data';
    dio.options.headers['Accept'] = 'application/json';
    dio.options.headers['Authorization'] = "Bearer $token";
    FormData formData;
    if (filePath != null) {
      formData = FormData.fromMap({
        'job_request_id': jobRequestId,
        'user_type': 'buyer',
        'message': message,
        'send_notify_mail': 'off',
        'file':
            await MultipartFile.fromFile(filePath, filename: 'image$filePath')
      });
    } else {
      formData = FormData.fromMap({
        'job_request_id': jobRequestId,
        'user_type': 'buyer',
        'message': message,
        'send_notify_mail': 'off',
      });
    }

    var connection = await checkConnection();
    if (connection) {
      setSendLoadingTrue();
      //if connection is ok

      var response = await dio.post(
        '$baseApi/user/job/request/conversation/send',
        data: formData,
        options: Options(
          validateStatus: (status) {
            return true;
          },
        ),
      );
      setSendLoadingFalse();

      if (response.statusCode == 201) {
        print(response.data);

        addNewMessage(message, filePath);

        sendNotification(context, sellerId: sellerId, msg: message);

        return true;
      } else {
        OthersHelper().showToast('Something went wrong', Colors.black);
        print(response.data);
        return false;
      }
    } else {
      OthersHelper()
          .showToast('Please check your internet connection', Colors.black);
      return false;
    }
  }

  addNewMessage(newMessage, filePath) {
    messagesList.add({
      'id': '',
      'message': newMessage,
      'notify': 'off',
      'attachment': filePath,
      'type': 'buyer',
      'filePicked':
          true //check if this image is just got picked from device in that case we will show it from device location
    });

    notifyListeners();
  }

  //Send notification to seller
  sendNotification(BuildContext context, {required sellerId, required msg}) {
    var username = Provider.of<ProfileService>(context, listen: false)
            .profileDetails
            .userDetails
            .name ??
        '';
    PushNotificationService().sendNotificationToSeller(context,
        sellerId: sellerId,
        title: lnProvider.getString("New job chat message") + ": $username",
        body: '$msg');
  }
}
