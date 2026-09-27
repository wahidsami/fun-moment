import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/provider_availability_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ProviderAvailabilityPage extends StatefulWidget {
  /// When [serviceId] is provided, availability is scoped to that specific service.
  /// When null, the legacy seller-level availability is shown.
  final int? serviceId;
  final String? serviceTitle;

  const ProviderAvailabilityPage({Key? key, this.serviceId, this.serviceTitle}) : super(key: key);

  @override
  State<ProviderAvailabilityPage> createState() => _ProviderAvailabilityPageState();
}

class _ProviderAvailabilityPageState extends State<ProviderAvailabilityPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<ProviderAvailabilityService>(context, listen: false)
          .fetchDaysAndSchedules(serviceId: widget.serviceId);
    });
  }

  void _showAddSlotDialog(BuildContext context, ProviderWorkingDay day) {
    TimeOfDay startTime = const TimeOfDay(hour: 9, minute: 0);
    TimeOfDay endTime = const TimeOfDay(hour: 10, minute: 0);
    bool applyToAllDays = false;
    final lnProvider = Provider.of<AppStringService>(context, listen: false);

    showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (ctx, setDialogState) {
          String formatTime(TimeOfDay t) {
            final period = t.period == DayPeriod.am ? 'AM' : 'PM';
            final hour = t.hourOfPeriod == 0 ? 12 : t.hourOfPeriod;
            final minute = t.minute.toString().padLeft(2, '0');
            final formattedHour = hour.toString().padLeft(2, '0');
            return '$formattedHour:$minute $period';
          }

          final slotString = '${formatTime(startTime)} - ${formatTime(endTime)}';

          return AlertDialog(
            backgroundColor: FMColors.surfaceDark,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
              side: BorderSide(color: FMColors.magenta.withOpacity(0.4), width: 1.5),
            ),
            title: Text(
              '${lnProvider.getString('Add Time Slot')} — ${day.fullName}',
              style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold),
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  lnProvider.getString('Select start and end time for client bookings:'),
                  style: const TextStyle(color: FMColors.textMuted, fontSize: 13),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            lnProvider.getString('Start Time'),
                            style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600),
                          ),
                          const SizedBox(height: 6),
                          InkWell(
                            onTap: () async {
                              final picked = await showTimePicker(
                                context: context,
                                initialTime: startTime,
                              );
                              if (picked != null) {
                                setDialogState(() => startTime = picked);
                              }
                            },
                            borderRadius: BorderRadius.circular(12),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                              decoration: BoxDecoration(
                                color: FMColors.background,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: FMColors.border),
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    formatTime(startTime),
                                    style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                                  ),
                                  const Icon(Icons.access_time_rounded, color: FMColors.magenta, size: 16),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            lnProvider.getString('End Time'),
                            style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600),
                          ),
                          const SizedBox(height: 6),
                          InkWell(
                            onTap: () async {
                              final picked = await showTimePicker(
                                context: context,
                                initialTime: endTime,
                              );
                              if (picked != null) {
                                setDialogState(() => endTime = picked);
                              }
                            },
                            borderRadius: BorderRadius.circular(12),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                              decoration: BoxDecoration(
                                color: FMColors.background,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: FMColors.border),
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    formatTime(endTime),
                                    style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                                  ),
                                  const Icon(Icons.access_time_rounded, color: FMColors.magenta, size: 16),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: FMColors.magenta.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: FMColors.magenta.withOpacity(0.2)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.calendar_today_rounded, color: FMColors.magenta, size: 16),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${lnProvider.getString('Slot')}: $slotString',
                          style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Checkbox(
                      value: applyToAllDays,
                      activeColor: FMColors.magenta,
                      onChanged: (val) => setDialogState(() => applyToAllDays = val ?? false),
                    ),
                    Expanded(
                      child: Text(
                        lnProvider.getString('Apply to all active working days'),
                        style: const TextStyle(color: Colors.white70, fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogCtx),
                child: Text(
                  lnProvider.getString('Cancel'),
                  style: const TextStyle(color: FMColors.textMuted),
                ),
              ),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: FMColors.magenta,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                onPressed: () async {
                  final startMinutes = startTime.hour * 60 + startTime.minute;
                  final endMinutes = endTime.hour * 60 + endTime.minute;

                  if (endMinutes <= startMinutes) {
                    OthersHelper().showToast(
                      lnProvider.getString('End time must be after start time'),
                      Colors.black,
                    );
                    return;
                  }

                  Navigator.pop(dialogCtx);
                  final availService = Provider.of<ProviderAvailabilityService>(context, listen: false);
                  await availService.addTimeSlot(
                    dayId: day.id,
                    schedule: slotString,
                    allDays: applyToAllDays,
                    serviceId: widget.serviceId,
                  );
                },
                child: Text(
                  lnProvider.getString('Add Slot'),
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lnProvider = Provider.of<AppStringService>(context);
    final rtlProvider = Provider.of<RtlService>(context);
    final isRtl = rtlProvider.isRtl;

    return Scaffold(
      backgroundColor: FMColors.background,
      appBar: AppBar(
        backgroundColor: FMColors.surfaceDark,
        elevation: 0,
        leading: IconButton(
          icon: Icon(
            isRtl ? Icons.arrow_forward_ios_rounded : Icons.arrow_back_ios_rounded,
            color: Colors.white,
            size: 20,
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              lnProvider.getString('Working Days & Availability'),
              style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
            ),
            if (widget.serviceTitle != null)
              Text(
                widget.serviceTitle!,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: FMColors.magenta, fontSize: 11, fontWeight: FontWeight.w600),
              ),
          ],
        ),
      ),
      body: Consumer<ProviderAvailabilityService>(
        builder: (context, availService, child) {
          if (availService.isLoading && availService.days.isEmpty) {
            return const Center(child: CircularProgressIndicator(color: FMColors.magenta));
          }

          // Build complete 7-day display
          final allWeekDays = [
            {'short': 'Sun', 'full': 'Sunday', 'ar': 'الأحد'},
            {'short': 'Mon', 'full': 'Monday', 'ar': 'الاثنين'},
            {'short': 'Tue', 'full': 'Tuesday', 'ar': 'الثلاثاء'},
            {'short': 'Wed', 'full': 'Wednesday', 'ar': 'الأربعاء'},
            {'short': 'Thu', 'full': 'Thursday', 'ar': 'الخميس'},
            {'short': 'Fri', 'full': 'Friday', 'ar': 'الجمعة'},
            {'short': 'Sat', 'full': 'Saturday', 'ar': 'السبت'},
          ];

          return RefreshIndicator(
            color: FMColors.magenta,
            backgroundColor: FMColors.surfaceDark,
            onRefresh: () => availService.fetchDaysAndSchedules(serviceId: widget.serviceId),
            child: ListView(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
              children: [
                // Info Banner
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: FMColors.surfaceDark,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: FMColors.border),
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: FMColors.magenta.withOpacity(0.12),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.schedule_rounded, color: FMColors.magenta, size: 20),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              lnProvider.getString('Configure Availability'),
                              style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              lnProvider.getString('Enable working days and set time slots so buyers can book your services accurately.'),
                              style: const TextStyle(color: FMColors.textMuted, fontSize: 12, height: 1.3),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                // 7 Days List
                ...allWeekDays.map((dayDef) {
                  final shortName = dayDef['short']!;
                  final fullName = dayDef['full']!;
                  final arName = dayDef['ar']!;
                  final displayName = isRtl ? arName : fullName;

                  // Find configured day record if exists
                  final existing = availService.days.firstWhere(
                    (d) => d.shortName.toLowerCase() == shortName.toLowerCase(),
                    orElse: () => ProviderWorkingDay(
                      id: 0,
                      day: shortName,
                      status: 0,
                      totalDay: 7,
                      schedules: [],
                    ),
                  );

                  final isConfigured = existing.id > 0;
                  final isEnabled = existing.isEnabled;

                  return Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(
                      color: FMColors.surfaceDark,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(
                        color: isEnabled ? FMColors.magenta.withOpacity(0.3) : FMColors.border,
                      ),
                    ),
                    child: ExpansionTile(
                      key: Key('day_${shortName}_${existing.id}_$isEnabled'),
                      initiallyExpanded: isEnabled,
                      tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                      shape: const Border(),
                      leading: Container(
                        width: 38,
                        height: 38,
                        decoration: BoxDecoration(
                          color: isEnabled ? FMColors.magenta.withOpacity(0.15) : Colors.white.withOpacity(0.04),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: isEnabled ? FMColors.magenta : Colors.transparent,
                            width: 1.2,
                          ),
                        ),
                        child: Center(
                          child: Text(
                            shortName,
                            style: TextStyle(
                              color: isEnabled ? FMColors.magenta : FMColors.textMuted,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                      title: Text(
                        displayName,
                        style: TextStyle(
                          color: isEnabled ? Colors.white : FMColors.textMuted,
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      subtitle: Text(
                        isEnabled
                            ? '${existing.schedules.length} ${lnProvider.getString('Slots Active')}'
                            : lnProvider.getString('Day Disabled'),
                        style: TextStyle(
                          color: isEnabled ? FMColors.success : FMColors.textMuted,
                          fontSize: 12,
                        ),
                      ),
                      trailing: Switch(
                        value: isEnabled,
                        activeColor: FMColors.magenta,
                        onChanged: (bool newVal) async {
                          if (!isConfigured) {
                            // Day doesn't exist yet in DB: create it
                            await availService.createWorkingDay(shortName, serviceId: widget.serviceId);
                          } else {
                            // Toggle existing day
                            await availService.toggleWorkingDay(existing.id, serviceId: widget.serviceId);
                          }
                        },
                      ),
                      children: [
                        if (isEnabled) ...[
                          const Divider(color: FMColors.border, height: 1),
                          Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (existing.schedules.isEmpty)
                                  Container(
                                    width: double.infinity,
                                    padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
                                    decoration: BoxDecoration(
                                      color: FMColors.background,
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Center(
                                      child: Text(
                                        lnProvider.getString('No time slots for this day yet. Add one below.'),
                                        style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                                      ),
                                    ),
                                  )
                                else
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    children: existing.schedules.map((slot) {
                                      return Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                        decoration: BoxDecoration(
                                          color: FMColors.background,
                                          borderRadius: BorderRadius.circular(10),
                                          border: Border.all(color: FMColors.magenta.withOpacity(0.35)),
                                        ),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const Icon(Icons.access_time_rounded, color: FMColors.magenta, size: 14),
                                            const SizedBox(width: 6),
                                            Text(
                                              slot.schedule,
                                              style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                                            ),
                                            const SizedBox(width: 8),
                                            InkWell(
                                              onTap: () async {
                                                await availService.deleteTimeSlot(slot.id, serviceId: widget.serviceId);
                                              },
                                              child: const Icon(Icons.close_rounded, color: Colors.redAccent, size: 16),
                                            ),
                                          ],
                                        ),
                                      );
                                    }).toList(),
                                  ),
                                const SizedBox(height: 12),
                                SizedBox(
                                  width: double.infinity,
                                  child: OutlinedButton.icon(
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: FMColors.magenta,
                                      side: const BorderSide(color: FMColors.magenta),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                      padding: const EdgeInsets.symmetric(vertical: 10),
                                    ),
                                    onPressed: () => _showAddSlotDialog(context, existing),
                                    icon: const Icon(Icons.add_rounded, size: 18),
                                    label: Text(
                                      lnProvider.getString('Add Time Slot'),
                                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  );
                }).toList(),
              ],
            ),
          );
        },
      ),
    );
  }
}
