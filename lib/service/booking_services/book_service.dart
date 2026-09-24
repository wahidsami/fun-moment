import 'package:flutter/cupertino.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class BookService with ChangeNotifier {
  int? serviceId;
  String? serviceTitle;
  String? serviceImage;
  int totalPrice = 0;
  int? sellerId;

  String selectedPayment = 'manual_payment';

  //address variables
  String? name;
  String? email;
  String? phone;
  String? postCode;
  String? address;
  String? orderNote;

  //selected shedule variables
  String? selectedDateAndMonth;
  DateTime? selectedDate;
  String? selectedTime;
  String? weekDay;

  setData(id, title, newPrice, sellerNewId, {image}) {
    serviceId = id is int ? id : int.tryParse(id?.toString() ?? '');
    serviceTitle = title?.toString() ?? '';
    serviceImage = image ?? placeHolderUrl;
    double parsedPrice = 0.0;
    if (newPrice is num) {
      parsedPrice = newPrice.toDouble();
    } else if (newPrice != null) {
      parsedPrice = double.tryParse(newPrice.toString()) ?? 0.0;
    }
    totalPrice = parsedPrice.round();
    sellerId = sellerNewId is int ? sellerNewId : int.tryParse(sellerNewId?.toString() ?? '');
    notifyListeners();
  }

  setSelectedPayment(String value) {
    selectedPayment = value;
    print('selected payment $selectedPayment');
    notifyListeners();
  }

  setAddress(
      newName, newEmail, newPhone, newPostCode, newAddress, newOrderNote) {
    name = newName;
    email = newEmail;
    phone = newPhone;
    postCode = newPostCode;
    address = newAddress;
    orderNote = newOrderNote;
    notifyListeners();
  }

  setDeliveryDetailsBasedOnProfile(BuildContext context) {
    try {
      final userDetails = Provider.of<ProfileService>(context, listen: false)
          .profileDetails
          .userDetails;
      name = userDetails?.name ?? 'Customer';
      phone = userDetails?.phone ?? '';
      email = userDetails?.email ?? '';
    } catch (_) {
      name = 'Customer';
      phone = '';
      email = '';
    }
    notifyListeners();
  }

  setDateTime(dateandMonth, time, newWeekday, {date}) {
    selectedDateAndMonth = dateandMonth;
    selectedTime = time;
    weekDay = newWeekday;
    selectedDate = date;
    notifyListeners();
  }

  setTotalPrice(newPrice) {
    if (newPrice is num) {
      totalPrice = newPrice.round();
    } else if (newPrice != null) {
      totalPrice = (double.tryParse(newPrice.toString()) ?? 0.0).round();
    }
    notifyListeners();
  }

  defaultTotalPrice() {
    totalPrice = 0;
    notifyListeners();
  }
}
