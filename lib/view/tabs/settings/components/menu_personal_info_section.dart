import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/view/booking/booking_helper.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_styles.dart';

class MenuPersonalInfoSection extends StatelessWidget {
  const MenuPersonalInfoSection({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) => Consumer<ProfileService>(
          builder: (context, profileProvider, child) => Container(
                padding: EdgeInsets.symmetric(horizontal: screenPadding),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CommonHelper().titleCommon(
                          asProvider.getString("Personal information's")),
                      const SizedBox(
                        height: 25,
                      ),
                      BookingHelper().bRow(
                          'null',
                          asProvider.getString("Email"),
                          profileProvider.profileDetails.userDetails.email ??
                              ''),
                      BookingHelper().bRow(
                          'null',
                          asProvider.getString("City"),
                          profileProvider.profileDetails.userDetails.city
                                  .serviceCity ??
                              ''),
                      BookingHelper().bRow(
                          'null',
                          asProvider.getString("Area"),
                          profileProvider.profileDetails.userDetails.area
                                  .serviceArea ??
                              ''),
                      BookingHelper().bRow(
                          'null',
                          asProvider.getString("Country"),
                          profileProvider
                                  .profileDetails.userDetails.country.country ??
                              ''),
                      BookingHelper().bRow(
                          'null',
                          asProvider.getString("Post Code"),
                          profileProvider.profileDetails.userDetails.postCode ??
                              ''),
                      BookingHelper().bRow(
                          'null',
                          asProvider.getString("Address"),
                          profileProvider.profileDetails.userDetails.address ??
                              '',
                          lastBorder: false),
                    ]),
              )),
    );
  }
}
