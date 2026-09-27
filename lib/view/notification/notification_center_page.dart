import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/tabs/orders/order_details_page.dart';

class NotificationCenterPage extends StatefulWidget {
  const NotificationCenterPage({Key? key}) : super(key: key);

  @override
  State<NotificationCenterPage> createState() => _NotificationCenterPageState();
}

class _NotificationCenterPageState extends State<NotificationCenterPage> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<PushNotificationService>(context, listen: false)
          .fetchNotifications(refresh: true);
    });

    _scrollController.addListener(() {
      if (_scrollController.position.pixels >=
          _scrollController.position.maxScrollExtent - 200) {
        Provider.of<PushNotificationService>(context, listen: false)
            .fetchNotifications(refresh: false);
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  IconData _getNotificationIcon(String? type) {
    switch (type) {
      case 'new_booking':
        return Icons.bookmark_add_rounded;
      case 'booking_accepted':
        return Icons.check_circle_rounded;
      case 'booking_cancelled':
        return Icons.cancel_rounded;
      case 'booking_completed':
        return Icons.task_alt_rounded;
      case 'payment_confirmed':
        return Icons.payment_rounded;
      default:
        return Icons.notifications_active_rounded;
    }
  }

  Color _getNotificationColor(String? type) {
    switch (type) {
      case 'new_booking':
        return FMColors.magenta;
      case 'booking_accepted':
      case 'booking_completed':
        return FMColors.success;
      case 'booking_cancelled':
        return Colors.redAccent;
      case 'payment_confirmed':
        return Colors.amber;
      default:
        return FMColors.magenta;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, _) {
        return Scaffold(
          backgroundColor: FMColors.background,
          appBar: AppBar(
            backgroundColor: FMColors.surfaceDark,
            elevation: 0,
            title: Text(
              asProvider.getString('Notifications'),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            actions: [
              Consumer<PushNotificationService>(
                builder: (context, pushService, _) {
                  if (pushService.unreadCount == 0) return const SizedBox.shrink();
                  return TextButton(
                    onPressed: () {
                      pushService.markAllAsRead();
                    },
                    child: Text(
                      asProvider.getString('Mark all read'),
                      style: const TextStyle(
                        color: FMColors.magenta,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
          body: Consumer<PushNotificationService>(
            builder: (context, pushService, _) {
              if (pushService.isLoading && pushService.notifications.isEmpty) {
                return const Center(
                  child: CircularProgressIndicator(color: FMColors.magenta),
                );
              }

              if (pushService.notifications.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.notifications_none_rounded,
                        size: 72,
                        color: FMColors.textMuted.withOpacity(0.5),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        asProvider.getString('No notifications yet'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        asProvider.getString('Updates about your bookings and orders will appear here'),
                        style: const TextStyle(
                          color: FMColors.textMuted,
                          fontSize: 14,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                );
              }

              return RefreshIndicator(
                color: FMColors.magenta,
                backgroundColor: FMColors.surfaceDark,
                onRefresh: () async {
                  await pushService.fetchNotifications(refresh: true);
                },
                child: ListView.separated(
                  controller: _scrollController,
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  itemCount: pushService.notifications.length +
                      (pushService.hasMore ? 1 : 0),
                  separatorBuilder: (context, index) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    if (index == pushService.notifications.length) {
                      return const Center(
                        child: Padding(
                          padding: EdgeInsets.all(16.0),
                          child: CircularProgressIndicator(color: FMColors.magenta),
                        ),
                      );
                    }

                    final item = pushService.notifications[index];
                    final bool isRead = item['is_read'] == true;
                    final String type = item['notification_type']?.toString() ?? 'order_alert';
                    final String message = item['order_message']?.toString() ?? '';
                    final String time = item['created_at_human']?.toString() ?? '';
                    final dynamic rawOrderId = item['order_id'];

                    return InkWell(
                      borderRadius: BorderRadius.circular(14),
                      onTap: () {
                        if (!isRead) {
                          pushService.markAsRead(item['id'].toString());
                        }

                        if (rawOrderId != null) {
                          final orderId = int.tryParse(rawOrderId.toString()) ?? rawOrderId;
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => OrderDetailsPage(orderId: orderId),
                            ),
                          );
                        }
                      },
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: isRead
                              ? FMColors.surfaceDark.withOpacity(0.6)
                              : FMColors.surfaceElevated,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: isRead
                                ? FMColors.border.withOpacity(0.3)
                                : FMColors.magenta.withOpacity(0.5),
                          ),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: _getNotificationColor(type).withOpacity(0.15),
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                _getNotificationIcon(type),
                                color: _getNotificationColor(type),
                                size: 22,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Expanded(
                                        child: Text(
                                          _formatTitle(type, asProvider),
                                          style: TextStyle(
                                            color: Colors.white,
                                            fontSize: 14,
                                            fontWeight: isRead
                                                ? FontWeight.w500
                                                : FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                      if (!isRead)
                                        Container(
                                          width: 8,
                                          height: 8,
                                          decoration: const BoxDecoration(
                                            color: FMColors.magenta,
                                            shape: BoxShape.circle,
                                          ),
                                        ),
                                    ],
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    message,
                                    style: TextStyle(
                                      color: isRead ? FMColors.textMuted : Colors.white70,
                                      fontSize: 13,
                                      height: 1.3,
                                    ),
                                  ),
                                  if (time.isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Text(
                                      time,
                                      style: TextStyle(
                                        color: FMColors.textMuted.withOpacity(0.8),
                                        fontSize: 11,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              );
            },
          ),
        );
      },
    );
  }

  String _formatTitle(String type, AppStringService asProvider) {
    switch (type) {
      case 'new_booking':
        return asProvider.getString('New Booking Alert');
      case 'booking_accepted':
        return asProvider.getString('Booking Confirmed');
      case 'booking_cancelled':
        return asProvider.getString('Booking Cancelled');
      case 'booking_completed':
        return asProvider.getString('Booking Completed');
      case 'payment_confirmed':
        return asProvider.getString('Payment Confirmed');
      default:
        return asProvider.getString('Booking Update');
    }
  }
}
