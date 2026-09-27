import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/push_notification_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/notification/notification_center_page.dart';

class NotificationBellButton extends StatelessWidget {
  const NotificationBellButton({Key? key, this.color = Colors.white}) : super(key: key);

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Consumer<PushNotificationService>(
      builder: (context, pushService, _) {
        final unreadCount = pushService.unreadCount;

        return Stack(
          alignment: Alignment.center,
          children: [
            IconButton(
              icon: Icon(Icons.notifications_outlined, color: color, size: 24),
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const NotificationCenterPage(),
                  ),
                );
              },
            ),
            if (unreadCount > 0)
              Positioned(
                top: 8,
                right: 8,
                child: Container(
                  padding: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(
                    color: FMColors.magenta,
                    shape: BoxShape.circle,
                  ),
                  constraints: const BoxConstraints(
                    minWidth: 16,
                    minHeight: 16,
                  ),
                  child: Text(
                    unreadCount > 99 ? '99+' : unreadCount.toString(),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
