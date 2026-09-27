import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/order_details_service.dart';
import 'package:funmoments/service/orders_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class ProviderOrderActions extends StatelessWidget {
  const ProviderOrderActions({Key? key, required this.orderId}) : super(key: key);

  final dynamic orderId;

  @override
  Widget build(BuildContext context) {
    return Consumer<ProfileService>(
      builder: (context, profileProvider, _) {
        if (!profileProvider.isSeller) {
          return const SizedBox.shrink();
        }

        return Consumer<OrderDetailsService>(
          builder: (context, detailsProvider, _) {
            final order = detailsProvider.orderDetails;
            if (order == null || order == 'error') {
              return const SizedBox.shrink();
            }

            final int status = order.status is int ? order.status : int.tryParse(order.status.toString()) ?? -1;

            return Consumer<OrdersService>(
              builder: (context, ordersService, _) {
                return Consumer<AppStringService>(
                  builder: (context, asProvider, _) {
                    // Pending Booking (status == 0): Provider can Accept or Decline
                    if (status == 0) {
                      return Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 25),
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: FMColors.surfaceDark,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: FMColors.magenta.withOpacity(0.5)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.notification_important_rounded,
                                    color: FMColors.magenta, size: 24),
                                const SizedBox(width: 8),
                                Text(
                                  asProvider.getString('New Booking Alert'),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              asProvider.getString(
                                  'A customer has booked your service. Please accept to start work or decline if unavailable.'),
                              style: const TextStyle(
                                color: FMColors.textMuted,
                                fontSize: 13,
                              ),
                            ),
                            const SizedBox(height: 18),
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton(
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: Colors.redAccent,
                                      side: const BorderSide(color: Colors.redAccent),
                                      padding: const EdgeInsets.symmetric(vertical: 14),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                    ),
                                    onPressed: ordersService.cancelLoading
                                        ? null
                                        : () {
                                            ordersService.cancelOrder(context,
                                                orderId: orderId);
                                          },
                                    child: ordersService.cancelLoading
                                        ? const SizedBox(
                                            height: 18,
                                            width: 18,
                                            child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.redAccent),
                                          )
                                        : Text(
                                            asProvider.getString('Decline'),
                                            style: const TextStyle(
                                                fontWeight: FontWeight.bold),
                                          ),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: ElevatedButton(
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: FMColors.success,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(vertical: 14),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                    ),
                                    onPressed: ordersService.acceptLoading
                                        ? null
                                        : () {
                                            ordersService.acceptOrder(context,
                                                orderId: orderId);
                                          },
                                    child: ordersService.acceptLoading
                                        ? const SizedBox(
                                            height: 18,
                                            width: 18,
                                            child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.white),
                                          )
                                        : Text(
                                            asProvider.getString('Accept Booking'),
                                            style: const TextStyle(
                                                fontWeight: FontWeight.bold),
                                          ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      );
                    }

                    // Active Booking (status == 1): Provider can complete
                    if (status == 1) {
                      return Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 25),
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: FMColors.surfaceDark,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: FMColors.success.withOpacity(0.4)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.play_circle_fill_rounded,
                                    color: FMColors.success, size: 24),
                                const SizedBox(width: 8),
                                Text(
                                  asProvider.getString('Booking In Progress'),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              asProvider.getString(
                                  'This booking has been accepted. Once your service is finished, mark it as completed.'),
                              style: const TextStyle(
                                color: FMColors.textMuted,
                                fontSize: 13,
                              ),
                            ),
                            const SizedBox(height: 16),
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton(
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: FMColors.magenta,
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 14),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                ),
                                onPressed: ordersService.acceptLoading
                                    ? null
                                    : () {
                                        ordersService.changeOrderStatus(context,
                                            orderId: orderId, status: 2);
                                      },
                                child: ordersService.acceptLoading
                                    ? const SizedBox(
                                        height: 18,
                                        width: 18,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2, color: Colors.white),
                                      )
                                    : Text(
                                        asProvider.getString('Mark as Completed'),
                                        style: const TextStyle(
                                            fontWeight: FontWeight.bold),
                                      ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }

                    return const SizedBox.shrink();
                  },
                );
              },
            );
          },
        );
      },
    );
  }
}
