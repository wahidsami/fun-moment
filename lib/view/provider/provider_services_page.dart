import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/provider/provider_add_service_page.dart';
import 'package:funmoments/view/provider/provider_availability_page.dart';
import 'package:funmoments/view/provider/provider_edit_service_page.dart';

class ProviderServicesPage extends StatefulWidget {
  const ProviderServicesPage({Key? key}) : super(key: key);

  @override
  State<ProviderServicesPage> createState() => _ProviderServicesPageState();
}

class _ProviderServicesPageState extends State<ProviderServicesPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<ProviderServiceManagementService>(context, listen: false)
          .fetchMyServices(isRefresh: true);
    });
  }

  void _confirmDelete(BuildContext context, int serviceId, String title) {
    final lnProvider = Provider.of<AppStringService>(context, listen: false);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: FMColors.surfaceDark,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(lnProvider.getString('Delete Service'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        content: Text('${lnProvider.getString('Are you sure you want to delete')} "$title"?', style: const TextStyle(color: FMColors.textMuted)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(lnProvider.getString('Cancel'), style: const TextStyle(color: FMColors.textMuted)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.redAccent,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () async {
              Navigator.pop(ctx);
              await Provider.of<ProviderServiceManagementService>(context, listen: false)
                  .deleteService(serviceId, context);
            },
            child: Text(lnProvider.getString('Delete'), style: const TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lnProvider = Provider.of<AppStringService>(context);
    final rtl = Provider.of<RtlService>(context);

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        title: Text(
          lnProvider.getString('My Services'),
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: FMColors.surfaceDark,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.calendar_month_outlined, color: Colors.white),
            tooltip: lnProvider.getString('My Availability'),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ProviderAvailabilityPage()),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.add_circle_outline, color: FMColors.magenta),
            onPressed: () async {
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ProviderAddServicePage(fromMyServices: true)),
              );
              if (mounted) {
                Provider.of<ProviderServiceManagementService>(context, listen: false)
                    .fetchMyServices(isRefresh: true);
              }
            },
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: FMColors.magenta,
        onPressed: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const ProviderAddServicePage(fromMyServices: true)),
          );
          if (mounted) {
            Provider.of<ProviderServiceManagementService>(context, listen: false)
                .fetchMyServices(isRefresh: true);
          }
        },
        icon: const Icon(Icons.add, color: Colors.white),
        label: Text(
          lnProvider.getString('Add Service'),
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      ),
      body: Consumer<ProviderServiceManagementService>(
        builder: (context, provider, child) {
          if (provider.isLoading && provider.services.isEmpty) {
            return const Center(child: CircularProgressIndicator(color: FMColors.magenta));
          }

          if (provider.services.isEmpty) {
            return RefreshIndicator(
              color: FMColors.magenta,
              onRefresh: () => provider.fetchMyServices(isRefresh: true),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  SizedBox(height: MediaQuery.of(context).size.height * 0.25),
                  const Center(
                    child: Icon(Icons.miscellaneous_services_outlined, size: 64, color: FMColors.textMuted),
                  ),
                  const SizedBox(height: 16),
                  Center(
                    child: Text(
                      lnProvider.getString('No service found'),
                      style: const TextStyle(color: FMColors.textMuted, fontSize: 16),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Center(
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: FMColors.magenta,
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const ProviderAddServicePage(fromMyServices: true)),
                        );
                      },
                      icon: const Icon(Icons.add, color: Colors.white),
                      label: Text(lnProvider.getString('Create First Service'), style: const TextStyle(color: Colors.white)),
                    ),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            color: FMColors.magenta,
            onRefresh: () => provider.fetchMyServices(isRefresh: true),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
              itemCount: provider.services.length,
              separatorBuilder: (_, __) => const SizedBox(height: 14),
              itemBuilder: (context, index) {
                final service = provider.services[index];
                final isOn = service.isServiceOn == 1;

                return Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: FMColors.surfaceDark,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: FMColors.border.withOpacity(0.6)),
                  ),
                  child: Column(
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Thumbnail
                          ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: service.imageUrl != null && service.imageUrl!.isNotEmpty
                                ? CachedNetworkImage(
                                    imageUrl: service.imageUrl!,
                                    width: 86,
                                    height: 86,
                                    fit: BoxFit.cover,
                                    placeholder: (_, __) => Container(color: Colors.black26),
                                    errorWidget: (_, __, ___) => Container(
                                      width: 86,
                                      height: 86,
                                      color: Colors.black26,
                                      child: const Icon(Icons.image_not_supported, color: FMColors.textMuted),
                                    ),
                                  )
                                : Container(
                                    width: 86,
                                    height: 86,
                                    color: Colors.black26,
                                    child: const Icon(Icons.business_center, color: FMColors.textMuted),
                                  ),
                          ),
                          const SizedBox(width: 14),

                          // Details
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  service.title,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 15,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                // Approval Status Badge
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: service.isPendingApproval
                                        ? Colors.amber.withOpacity(0.12)
                                        : Colors.green.withOpacity(0.12),
                                    borderRadius: BorderRadius.circular(6),
                                    border: Border.all(
                                      color: service.isPendingApproval
                                          ? Colors.amber.withOpacity(0.5)
                                          : Colors.green.withOpacity(0.4),
                                    ),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        service.isPendingApproval
                                            ? Icons.hourglass_top_rounded
                                            : Icons.check_circle_outline_rounded,
                                        size: 12,
                                        color: service.isPendingApproval ? Colors.amber : Colors.greenAccent,
                                      ),
                                      const SizedBox(width: 4),
                                      Flexible(
                                        child: Text(
                                          service.isPendingApproval
                                              ? lnProvider.getString('Pending Admin Approval')
                                              : lnProvider.getString('Approved'),
                                          style: TextStyle(
                                            color: service.isPendingApproval ? Colors.amber : Colors.greenAccent,
                                            fontSize: 11,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  '${rtl.currency}${service.price}',
                                  style: const TextStyle(
                                    color: FMColors.magenta,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 15,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: [
                                    const Icon(Icons.visibility_outlined, size: 14, color: FMColors.textMuted),
                                    const SizedBox(width: 4),
                                    Text('${service.view ?? 0}', style: const TextStyle(color: FMColors.textMuted, fontSize: 12)),
                                    const SizedBox(width: 12),
                                    const Icon(Icons.shopping_bag_outlined, size: 14, color: FMColors.textMuted),
                                    const SizedBox(width: 4),
                                    Text(
                                      '${service.completeOrderCount ?? 0} done',
                                      style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      const Divider(color: FMColors.border, height: 1),
                      const SizedBox(height: 8),

                      // Bottom actions: Active switch, Edit button, Delete button
                      Row(
                        children: [
                          // Switch & label
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Transform.scale(
                                scale: 0.8,
                                child: Switch(
                                  value: isOn,
                                  activeColor: FMColors.magenta,
                                  onChanged: service.isPendingApproval
                                      ? null
                                      : (_) {
                                          provider.toggleServiceStatus(service.id);
                                        },
                                ),
                              ),
                              Text(
                                isOn ? lnProvider.getString('Active') : lnProvider.getString('Inactive'),
                                style: TextStyle(
                                  color: isOn ? Colors.white70 : FMColors.textMuted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                          const Spacer(),

                          // Edit Button
                          OutlinedButton.icon(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: FMColors.magenta,
                              side: const BorderSide(color: FMColors.magenta, width: 1.2),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                            ),
                            icon: const Icon(Icons.edit_outlined, size: 16),
                            label: Text(
                              lnProvider.getString('Edit'),
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                            ),
                            onPressed: () async {
                              final updated = await Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => ProviderEditServicePage(
                                    serviceId: service.id,
                                    initialItem: service,
                                  ),
                                ),
                              );
                              if (updated == true && mounted) {
                                provider.fetchMyServices(isRefresh: true);
                              }
                            },
                          ),
                          const SizedBox(width: 8),

                          // Delete Button
                          IconButton(
                            icon: const Icon(Icons.delete_outline, color: Colors.redAccent, size: 20),
                            tooltip: lnProvider.getString('Delete'),
                            onPressed: () => _confirmDelete(context, service.id, service.title),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
