import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/jobs/job_request_page.dart';
import 'package:funmoments/view/jobs/my_jobs_page.dart';
import 'package:funmoments/view/provider/provider_add_service_page.dart';
import 'package:funmoments/view/provider/provider_services_page.dart';
import 'package:funmoments/view/tabs/orders/orders_page.dart';
import 'package:funmoments/view/tabs/settings/supports/my_tickets_page.dart';
import 'package:funmoments/view/wallet/wallet_page.dart';

class ProviderDashboardPage extends StatefulWidget {
  const ProviderDashboardPage({Key? key}) : super(key: key);

  @override
  State<ProviderDashboardPage> createState() => _ProviderDashboardPageState();
}

class _ProviderDashboardPageState extends State<ProviderDashboardPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final psm = Provider.of<ProviderServiceManagementService>(context, listen: false);
      psm.fetchDashboardInfo();
      psm.fetchMyServices();
    });
  }

  @override
  Widget build(BuildContext context) {
    final lnProvider = Provider.of<AppStringService>(context);
    final rtl = Provider.of<RtlService>(context);
    final profileService = Provider.of<ProfileService>(context);
    final userName = profileService.profileDetails?.userDetails?.name ?? 'Provider';

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        title: Text(
          lnProvider.getString('Provider Workspace'),
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: FMColors.surfaceDark,
        elevation: 0,
        actions: [
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: FMColors.magenta.withOpacity(0.18),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: FMColors.magenta),
            ),
            alignment: Alignment.center,
            child: Text(
              lnProvider.getString('SELLER'),
              style: const TextStyle(color: FMColors.magenta, fontWeight: FontWeight.bold, fontSize: 11),
            ),
          ),
        ],
      ),
      body: Consumer<ProviderServiceManagementService>(
        builder: (context, psm, child) {
          final data = psm.dashboardData;

          return RefreshIndicator(
            color: FMColors.magenta,
            onRefresh: () async {
              await Future.wait([
                psm.fetchDashboardInfo(),
                psm.fetchMyServices(isRefresh: true),
              ]);
            },
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Greeting Card
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [FMColors.surfaceDark, FMColors.magenta.withOpacity(0.2)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: FMColors.border.withOpacity(0.6)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${lnProvider.getString("Welcome back")}, $userName',
                          style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          lnProvider.getString('Manage your services, client orders, and earnings'),
                          style: const TextStyle(color: FMColors.textMuted, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // KPI Cards
                  Row(
                    children: [
                      Expanded(
                        child: _kpiCard(
                          title: lnProvider.getString('Pending Orders'),
                          value: '${data?.pendingOrders ?? 0}',
                          icon: Icons.hourglass_top_rounded,
                          color: Colors.amberAccent,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _kpiCard(
                          title: lnProvider.getString('Completed'),
                          value: '${data?.completedOrders ?? 0}',
                          icon: Icons.check_circle_outline,
                          color: Colors.greenAccent,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: _kpiCard(
                          title: lnProvider.getString('Balance'),
                          value: '${rtl.currency}${data?.remainingBalance ?? 0}',
                          icon: Icons.account_balance_wallet_outlined,
                          color: FMColors.magenta,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _kpiCard(
                          title: lnProvider.getString('My Services'),
                          value: '${psm.services.length}',
                          icon: Icons.miscellaneous_services_outlined,
                          color: Colors.cyanAccent,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // Quick Actions Grid
                  Text(
                    lnProvider.getString('Provider Tools'),
                    style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 1.6,
                    children: [
                      _actionTile(
                        icon: Icons.list_alt_rounded,
                        title: lnProvider.getString('My Services'),
                        subtitle: '${psm.services.length} ${lnProvider.getString("active")}',
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const ProviderServicesPage()),
                        ),
                      ),
                      _actionTile(
                        icon: Icons.add_box_outlined,
                        title: lnProvider.getString('Add Service'),
                        subtitle: lnProvider.getString('Create listing'),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const ProviderAddServicePage()),
                        ),
                      ),
                      _actionTile(
                        icon: Icons.shopping_bag_outlined,
                        title: lnProvider.getString('Orders'),
                        subtitle: lnProvider.getString('Client bookings'),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const OrdersPage()),
                        ),
                      ),
                      _actionTile(
                        icon: Icons.work_outline_rounded,
                        title: lnProvider.getString('My Jobs'),
                        subtitle: lnProvider.getString('Applied jobs'),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const MyJobsPage()),
                        ),
                      ),
                      _actionTile(
                        icon: Icons.mark_chat_unread_outlined,
                        title: lnProvider.getString('Job Requests'),
                        subtitle: lnProvider.getString('Direct invites'),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const JobRequestPage()),
                        ),
                      ),
                      _actionTile(
                        icon: Icons.account_balance_wallet_outlined,
                        title: lnProvider.getString('Wallet'),
                        subtitle: lnProvider.getString('Payouts & balance'),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const WalletPage()),
                        ),
                      ),
                      _actionTile(
                        icon: Icons.support_agent_rounded,
                        title: lnProvider.getString('Support'),
                        subtitle: lnProvider.getString('Help tickets'),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const MyTicketsPage()),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _kpiCard({required String title, required String value, required IconData icon, required Color color}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: FMColors.surfaceDark,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: FMColors.border.withOpacity(0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(height: 10),
          Text(
            value,
            style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(
            title,
            style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _actionTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: FMColors.surfaceDark,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: FMColors.border.withOpacity(0.5)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: FMColors.magenta, size: 22),
            const SizedBox(height: 6),
            Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
            ),
          ],
        ),
      ),
    );
  }
}
