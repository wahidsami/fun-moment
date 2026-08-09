import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/booking_services/place_order_service.dart';
import 'package:funmoments/service/wallet_service.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_styles.dart';
import 'package:funmoments/view/utils/custom_input.dart';

class DepositeAmountSection extends StatefulWidget {
  const DepositeAmountSection({Key? key}) : super(key: key);

  @override
  State<DepositeAmountSection> createState() => _DepositeAmountSectionState();
}

class _DepositeAmountSectionState extends State<DepositeAmountSection> {
  final amountController = TextEditingController();

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, ln, child) => Consumer<PlaceOrderService>(
        builder: (context, provider, child) => Consumer<WalletService>(
          builder: (context, wProvider, child) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              //Amount ============>
              CommonHelper().labelCommon(ln.getString("Deposit Amount")),

              sizedBoxCustom(5),
              CustomInput(
                controller: amountController,
                hintText: ln.getString("Enter deposit amount"),
                textInputAction: TextInputAction.next,
                paddingHorizontal: 18,
                onChanged: (v) {
                  wProvider.setAmount(v);
                },
              ),
              sizedBoxCustom(25),
            ],
          ),
        ),
      ),
    );
  }
}
