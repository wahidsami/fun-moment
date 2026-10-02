import 'package:auto_size_text/auto_size_text.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/my_orders_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/rtl_service.dart';

import 'package:funmoments/view/tabs/orders/order_details_page.dart';
import 'package:funmoments/view/tabs/orders/order_sort.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/constant_styles.dart';
import 'package:funmoments/view/utils/login_or_register.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';

import 'orders_helper.dart';

class OrdersPage extends StatefulWidget {
  const OrdersPage({Key? key}) : super(key: key);

  @override
  _OrdersPageState createState() => _OrdersPageState();
}

class _OrdersPageState extends State<OrdersPage> {
  @override
  void initState() {
    super.initState();
  }

  final ScrollController controller = ScrollController();
  @override
  Widget build(BuildContext context) {
    ConstantColors cc = ConstantColors();
    controller.addListener(() {
      scrollListener(context);
    });

    return Scaffold(
        backgroundColor: cc.bgColor,
        body: SafeArea(
          child: Consumer<ProfileService>(builder: (context, ps, child) {
            if (ps.profileDetails == null || ps.profileDetails is String) {
              return const LoginOrRegister();
            }
            if (!ps.isRoleResolved) {
              return Container(
                  alignment: Alignment.center,
                  height: MediaQuery.of(context).size.height - 120,
                  child: OthersHelper().showLoading(cc.primaryColor));
            }
            return FutureBuilder(
                future: Provider.of<MyOrdersService>(context, listen: false)
                    .fetchMyOrders(isSellerOverride: ps.isSeller),
                    builder: (context, snapshot) {
                      if (snapshot.connectionState == ConnectionState.waiting) {
                        return Container(
                            alignment: Alignment.center,
                            height: MediaQuery.of(context).size.height - 120,
                            child: OthersHelper().showLoading(cc.primaryColor));
                      }
                      return Consumer<MyOrdersService>(
                        builder: (context, provider, child) {
                          final count = provider.myServices is List
                              ? provider.myServices.length
                              : 0;
                          debugPrint(
                              '[PROVIDER ORDERS] UI rebuild with orders count: $count');
                          return Container(
                            padding:
                                EdgeInsets.symmetric(horizontal: screenPadding),
                            child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const SizedBox(height: 25),
                                  CommonHelper().titleCommon(
                                      lnProvider.getString('My Orders')),
                                  const SizedBox(height: 10),
                                  const OrderSort(),
                                  // for (int i = 0;
                                  //     i < provider.myServices.length;
                                  //     i++)
                                  Expanded(
                                    child: !provider.isLoading
                                        ? provider.myServices != 'error'
                                            ? provider.myServices.isNotEmpty
                                                ? ListView.separated(
                                                    shrinkWrap: true,
                                                    physics: physicsCommon,
                                                    itemCount:
                                                        provider.nextPageUrl !=
                                                                null
                                                            ? provider
                                                                    .myServices
                                                                    .length +
                                                                1
                                                            : provider
                                                                .myServices
                                                                .length,
                                                    controller: controller,
                                                    separatorBuilder:
                                                        (context, index) =>
                                                            const SizedBox(
                                                                height: 16),
                                                    itemBuilder: ((context, i) {
                                                      if (i ==
                                                          provider.myServices
                                                              .length) {
                                                        return SizedBox(
                                                          height: 40,
                                                          child: Center(
                                                              child: OthersHelper()
                                                                  .showLoading(cc
                                                                      .primaryColor)),
                                                        );
                                                      }
                                                      return InkWell(
                                                        onTap: () {
                                                          Navigator.push(
                                                              context,
                                                              MaterialPageRoute<
                                                                  void>(
                                                                builder: (BuildContext
                                                                        context) =>
                                                                    OrderDetailsPage(
                                                                        orderId: provider
                                                                            .myServices[i]
                                                                            .id),
                                                              ));
                                                          //             );
                                                        },
                                                        child: Container(
                                                          alignment:
                                                              Alignment.center,
                                                          decoration: BoxDecoration(
                                                              color: cc.black9,
                                                              border: Border.all(
                                                                  color: cc
                                                                      .borderColor),
                                                              borderRadius:
                                                                  BorderRadius
                                                                      .circular(
                                                                          12)),
                                                          child: Column(
                                                              children: [
                                                                Container(
                                                                  padding:
                                                                      const EdgeInsets
                                                                          .fromLTRB(
                                                                          15,
                                                                          6,
                                                                          0,
                                                                          0),
                                                                  child: Row(
                                                                    mainAxisAlignment:
                                                                        MainAxisAlignment
                                                                            .spaceBetween,
                                                                    children: [
                                                                      AutoSizeText(
                                                                        '#${provider.myServices[i].id}',
                                                                        maxLines:
                                                                            1,
                                                                        overflow:
                                                                            TextOverflow.ellipsis,
                                                                        style:
                                                                            TextStyle(
                                                                          color:
                                                                              cc.primaryColor,
                                                                        ),
                                                                      ),
                                                                      Row(
                                                                        children: [
                                                                          OrdersHelper().statusCapsule(
                                                                              lnProvider.getString(OrderDetailsService().getOrderStatus(provider.myServices[i].status)),
                                                                              cc.greyFour),

                                                                          //popup button
                                                                          Builder(builder: (context) {
                                                                            final actionItems = OrdersHelper().getOrderActions(
                                                                              isSeller: ps.isSeller,
                                                                              orderStatus: provider.myServices[i].status,
                                                                              paymentStatus: provider.myServices[i].paymentStatus,
                                                                            );
                                                                            if (actionItems.isEmpty) return const SizedBox.shrink();
                                                                            return PopupMenuButton(
                                                                              itemBuilder: (BuildContext context) =>
                                                                                  <PopupMenuEntry>[
                                                                                for (int j = 0; j < actionItems.length; j++)
                                                                                  PopupMenuItem(
                                                                                    onTap: () {
                                                                                      Future.delayed(Duration.zero, () {
                                                                                        OrdersHelper().handleOrderAction(
                                                                                          context,
                                                                                          actionKey: actionItems[j].actionKey,
                                                                                          serviceId: provider.myServices[i].serviceId,
                                                                                          orderId: provider.myServices[i].id,
                                                                                        );
                                                                                      });
                                                                                    },
                                                                                    child: Text(lnProvider.getString(actionItems[j].title)),
                                                                                  ),
                                                                              ],
                                                                            );
                                                                          })
                                                                        ],
                                                                      )
                                                                    ],
                                                                  ),
                                                                ),

                                                                //Divider
                                                                Container(
                                                                  margin:
                                                                      const EdgeInsets
                                                                          .only(
                                                                          top:
                                                                              6,
                                                                          bottom:
                                                                              17),
                                                                  child: CommonHelper()
                                                                      .dividerCommon(),
                                                                ),

                                                                provider.myServices[i]
                                                                            .date !=
                                                                        "00.00.00"
                                                                    ? Container(
                                                                        padding: const EdgeInsets
                                                                            .symmetric(
                                                                            horizontal:
                                                                                15),
                                                                        child:
                                                                            Column(
                                                                          children: [
                                                                            OrdersHelper().orderRow(
                                                                              'assets/svg/calendar.svg',
                                                                              'Date',
                                                                              provider.myServices[i].date == null ? lnProvider.getString("No date found") : DateFormat.MMMMEEEEd(rtlProvider.langSlug.substring(0, 2)).format(provider.myServices[i].date),
                                                                            ),
                                                                            Container(
                                                                              margin: const EdgeInsets.symmetric(vertical: 14),
                                                                              child: CommonHelper().dividerCommon(),
                                                                            ),
                                                                          ],
                                                                        ),
                                                                      )
                                                                    : Container(),

                                                                provider.myServices[i]
                                                                            .schedule !=
                                                                        "00.00.00"
                                                                    ? Container(
                                                                        padding: const EdgeInsets
                                                                            .symmetric(
                                                                            horizontal:
                                                                                15),
                                                                        child:
                                                                            Column(
                                                                          children: [
                                                                            OrdersHelper().orderRow(
                                                                              'assets/svg/clock.svg',
                                                                              'Schedule',
                                                                              lnProvider.getString(provider.myServices[i].schedule),
                                                                            ),
                                                                            Container(
                                                                              margin: const EdgeInsets.symmetric(vertical: 14),
                                                                              child: CommonHelper().dividerCommon(),
                                                                            ),
                                                                          ],
                                                                        ),
                                                                      )
                                                                    : Container(),

                                                                Container(
                                                                  padding:
                                                                      const EdgeInsets
                                                                          .fromLTRB(
                                                                          15,
                                                                          0,
                                                                          15,
                                                                          15),
                                                                  child: Consumer<
                                                                      RtlService>(
                                                                    builder: (context,
                                                                            rtlP,
                                                                            child) =>
                                                                        OrdersHelper()
                                                                            .orderRow(
                                                                      'assets/svg/bill.svg',
                                                                      'Billed',
                                                                      rtlP.currencyDirection ==
                                                                              'left'
                                                                          ? '${rtlP.currency}${(provider.myServices[i].total ?? 0.0).toStringAsFixed(2)}'
                                                                          : '${(provider.myServices[i].total ?? 0.0).toStringAsFixed(2)}${rtlP.currency}',
                                                                    ),
                                                                  ),
                                                                )
                                                              ]),
                                                        ),
                                                      );
                                                    }))
                                                : CommonHelper().nothingfound(
                                                    context, "No active order")
                                            : CommonHelper().nothingfound(
                                                context, "No active order")
                                        : Container(
                                            alignment: Alignment.center,
                                            height: MediaQuery.of(context)
                                                    .size
                                                    .height -
                                                200,
                                            child: OthersHelper()
                                                .showLoading(cc.primaryColor)),
                                  ),

                                  ],
                                ),
                              );
                            },
                          );
                        },
                      );
          }),
        ));
  }

  scrollListener(BuildContext context) async {
    final moProvider = Provider.of<MyOrdersService>(context, listen: false);
    if (controller.offset >= controller.position.maxScrollExtent &&
        !controller.position.outOfRange) {
      if (moProvider.nextPageUrl == null) {
        return;
      }
      if (moProvider.nextPageUrl != null && !moProvider.isLoadingNextPage) {
        print('fetching next page orders');
        await moProvider.fetchNextOrders();
        return;
      }
    }
  }
}
