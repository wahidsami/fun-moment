import 'package:flutter/material.dart';
import 'package:page_transition/page_transition.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/book_steps_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/view/auth/signup/components/country_states_dropdowns.dart';
import 'package:funmoments/view/booking/delivery_address_page.dart.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/constant_styles.dart';
import 'package:funmoments/view/utils/others_helper.dart';

import 'components/steps.dart';

class BookingLocationPage extends StatefulWidget {
  const BookingLocationPage({
    Key? key,
  }) : super(key: key);

  @override
  _BookingLocationPageState createState() => _BookingLocationPageState();
}

class _BookingLocationPageState extends State<BookingLocationPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final cp = Provider.of<CountryDropdownService>(context, listen: false);
      cp.preselectSaudi();
      final sp = Provider.of<StateDropdownService>(context, listen: false);
      sp.fetchStates(context).then((_) {
        if (mounted) {
          final ap = Provider.of<AreaDropdownService>(context, listen: false);
          ap.fetchArea(context);
        }
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    ConstantColors cc = ConstantColors();
    return WillPopScope(
      onWillPop: () {
        BookStepsService().decreaseStep(context);
        return Future.value(true);
      },
      child: Consumer<AppStringService>(
        builder: (context, asProvider, child) => Scaffold(
          appBar: CommonHelper().appbarForBookingPages(
            asProvider.getString("Location"),
            context,
          ),
          body: SingleChildScrollView(
            physics: physicsCommon,
            child: Container(
              padding: EdgeInsets.symmetric(
                horizontal: screenPadding,
              ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      //Circular Progress bar
                      Steps(cc: cc),

                      CommonHelper().titleCommon(
                          asProvider.getString("Booking information's")),

                      const SizedBox(
                        height: 20,
                      ),

                      const CountryStatesDropdowns(),

                      Consumer2<StateDropdownService, AreaDropdownService>(
                        builder: (context, sp, ap, child) {
                          if (sp.hasError || ap.hasError) {
                            return Container(
                              margin: const EdgeInsets.only(top: 16),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: cc.warningColor.withOpacity(0.12),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: cc.warningColor.withOpacity(0.3)),
                              ),
                              child: Row(
                                children: [
                                  Icon(Icons.info_outline, color: cc.warningColor, size: 20),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      asProvider.getString('Could not load locations. Tap retry.'),
                                      style: TextStyle(color: cc.greyFour, fontSize: 13),
                                    ),
                                  ),
                                  TextButton(
                                    onPressed: () {
                                      sp.fetchStates(context, isrefresh: true).then((_) {
                                        if (mounted) {
                                          ap.fetchArea(context, isrefresh: true);
                                        }
                                      });
                                    },
                                    child: Text(
                                      asProvider.getString('Retry'),
                                      style: TextStyle(color: cc.primaryColor, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                ],
                              ),
                            );
                          }
                          return const SizedBox.shrink();
                        },
                      ),

                      //Next button ==================>
                      const SizedBox(
                        height: 27,
                      ),
                      CommonHelper().buttonOrange(asProvider.getString('Next'),
                          () {
                        var selectedStateId = Provider.of<StateDropdownService>(
                                context,
                                listen: false)
                            .selectedStateId;
                        var selectedAreaId = Provider.of<AreaDropdownService>(
                                context,
                                listen: false)
                            .selectedAreaId;
                        if (selectedStateId == '0' ||
                            selectedStateId == 0 ||
                            selectedAreaId == '0' ||
                            selectedAreaId == 0) {
                          OthersHelper().showSnackBar(
                              context,
                              asProvider.getString(
                                  'Please select a city and area'),
                              cc.warningColor);
                          return;
                        }

                        //increase page steps by one
                        BookStepsService().onNext(context);

                        Navigator.push(
                            context,
                            PageTransition(
                                type: PageTransitionType.rightToLeft,
                                child: const DeliveryAddressPage()));
                      }),

                      const SizedBox(
                        height: 30,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
  }
}
