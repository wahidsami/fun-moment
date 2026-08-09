import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/my_orders_service.dart';

import '../../utils/common_helper.dart';
import '../../utils/custom_dropdown.dart';
import '../../utils/responsive.dart';

class OrderSort extends StatelessWidget {
  const OrderSort({Key? key}) : super(key: key);
  @override
  Widget build(BuildContext context) {
    return Consumer<MyOrdersService>(builder: (context, moProvider, child) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              CommonHelper().labelCommon(lnProvider.getString('Order') + ' ',
                  margin: EdgeInsets.zero),
              SizedBox(
                width: MediaQuery.of(context).size.width / 3.65,
                child: CustomDropdown(
                  lnProvider.getString("Select status"),
                  moProvider.orderStatusOptions,
                  (p0) {
                    moProvider.setOrderSort(p0);
                  },
                  value: moProvider.selectedOrderSort,
                ),
              ),
            ],
          ),
          Row(
            children: [
              CommonHelper().labelCommon(lnProvider.getString('Payment') + '  ',
                  margin: EdgeInsets.zero),
              SizedBox(
                width: MediaQuery.of(context).size.width / 3.65,
                child: CustomDropdown(
                  lnProvider.getString("Select status"),
                  moProvider.paymentStatusOptions,
                  (p0) {
                    moProvider.setPaymentSort(p0);
                  },
                  value: moProvider.selectedPaymentSort,
                ),
              ),
            ],
          ),
        ],
      );
    });
  }
}
