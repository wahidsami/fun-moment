import 'package:flutter/cupertino.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/booking_services/personalization_service.dart';

class BookConfirmationService with ChangeNotifier {
  bool isPanelOpened = false;

  double totalPriceAfterAllcalculation = 0.0;
  var subTotalAfterAllCalculation = 0.0;
  double totalPriceOnlineServiceAfterAllCalculation = 0.0;
  var subTotalOnlineServiceAfterAllCalculation = 0.0;

  var taxPrice;
  var taxPriceOnline;

  setTotalOnlineService(v) {
    totalPriceOnlineServiceAfterAllCalculation = v;
    notifyListeners();
  }

  setTotalOfflineService(v) {
    totalPriceAfterAllcalculation = v;
    notifyListeners();
  }

  setPanelOpenedTrue() {
    isPanelOpened = true;
    notifyListeners();
  }

  setPanelOpenedFalse() {
    isPanelOpened = false;
    notifyListeners();
  }

  includedTotalPrice(List includedList) {
    var total = 0.0;
    for (int i = 0; i < includedList.length; i++) {
      final item = includedList[i];
      if (item is Map) {
        final price = item['price'] is num
            ? (item['price'] as num).toDouble()
            : double.tryParse(item['price']?.toString() ?? '') ?? 0.0;
        final qty = item['qty'] is num
            ? (item['qty'] as num).toDouble()
            : double.tryParse(item['qty']?.toString() ?? '') ?? 0.0;
        total = total + (price * qty);
      }
    }
    return total;
  }

  extrasTotalPrice(List extrasList) {
    var total = 0.0;
    for (int i = 0; i < extrasList.length; i++) {
      final item = extrasList[i];
      if (item is Map && (item['selected'] == true || item['selected'] == 1 || item['selected'] == '1')) {
        final price = item['price'] is num
            ? (item['price'] as num).toDouble()
            : double.tryParse(item['price']?.toString() ?? '') ?? 0.0;
        final qty = item['qty'] is num
            ? (item['qty'] as num).toDouble()
            : double.tryParse(item['qty']?.toString() ?? '') ?? 0.0;
        total = total + (price * qty);
      }
    }
    return total;
  }

  calculateSubtotal(List includedList, List extrasList) {
    var includedTotal = 0.0;
    var extraTotal = 0.0;
    includedTotal = includedTotalPrice(includedList);
    extraTotal = extrasTotalPrice(extrasList);
    subTotalAfterAllCalculation = includedTotal + extraTotal;

    return subTotalAfterAllCalculation;
  }

  calculateSubtotalForOnline(List extrasList) {
    var extraTotal = 0.0;

    extraTotal = extrasTotalPrice(extrasList);
    subTotalOnlineServiceAfterAllCalculation = extraTotal;
    return extraTotal;
  }

  calculateTax(
    taxPercent,
    List includedList,
    List extrasList,
  ) {
    var subTotal = calculateSubtotal(includedList, extrasList);
    final parsedTax = taxPercent is num
        ? taxPercent.toDouble()
        : double.tryParse(taxPercent?.toString() ?? '') ?? 0.0;
    taxPrice = (subTotal * parsedTax) / 100;

    return taxPrice;
  }

  calculateTotal(taxPercent, List includedList, List extrasList) {
    var subTotal = calculateSubtotal(includedList, extrasList);
    var tax = calculateTax(taxPercent, includedList, extrasList);

    totalPriceAfterAllcalculation = subTotal + tax;
    Future.delayed(const Duration(microseconds: 500), () {
      notifyListeners();
    });
  }

  calculateTotalOnlineService(
      taxPercent, List includedList, List extrasList, BuildContext context) {
    var subTotal = calculateSubtotal(includedList, extrasList);
    var tax = calculateTax(taxPercent, includedList, extrasList);
    final defaultPrice = Provider.of<PersonalizationService>(context, listen: false).defaultprice;
    final parsedDefaultPrice = (defaultPrice as dynamic) is num
        ? ((defaultPrice as dynamic) as num).toDouble()
        : 0.0;
    totalPriceOnlineServiceAfterAllCalculation = subTotal + tax + parsedDefaultPrice;
    Future.delayed(const Duration(microseconds: 500), () {
      notifyListeners();
    });
  }

  caculateTotalAfterCouponApplied(couponDiscount) {
    final parsedDiscount = couponDiscount is num
        ? couponDiscount.toDouble()
        : double.tryParse(couponDiscount?.toString() ?? '') ?? 0.0;
    totalPriceAfterAllcalculation =
        totalPriceAfterAllcalculation - parsedDiscount;
    totalPriceOnlineServiceAfterAllCalculation =
        totalPriceOnlineServiceAfterAllCalculation - parsedDiscount;
    notifyListeners();
  }

  void resetState() {
    isPanelOpened = false;
    totalPriceAfterAllcalculation = 0.0;
    subTotalAfterAllCalculation = 0.0;
    totalPriceOnlineServiceAfterAllCalculation = 0.0;
    subTotalOnlineServiceAfterAllCalculation = 0.0;
    taxPrice = null;
    taxPriceOnline = null;
    notifyListeners();
  }
}
