import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

void showLanguageBottomSheet(BuildContext context) {
  final asProvider = Provider.of<AppStringService>(context, listen: false);
  final rtlService = Provider.of<RtlService>(context, listen: false);

  showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (BuildContext sheetContext) {
      return Consumer<RtlService>(
        builder: (context, rtl, child) {
          final isArabic = rtl.isArabic;

          return Container(
            decoration: BoxDecoration(
              color: FMColors.surfaceDark,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
              border: Border.all(color: FMColors.border.withOpacity(0.8)),
              boxShadow: const [
                BoxShadow(
                  color: Colors.black54,
                  blurRadius: 24,
                  offset: Offset(0, -6),
                ),
              ],
            ),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Drag handle
                Center(
                  child: Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(
                      color: FMColors.textMuted.withOpacity(0.3),
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
                const SizedBox(height: 18),

                // Header
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: FMColors.magenta.withOpacity(0.14),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.language_rounded,
                        color: FMColors.magenta,
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            asProvider.getString('Choose language'),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Language / اللغة',
                            style: TextStyle(
                              color: FMColors.textMuted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded, color: FMColors.textMuted),
                      onPressed: () => Navigator.pop(sheetContext),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // English Option
                _LanguageOptionCard(
                  title: 'English',
                  subtitle: 'Left-to-Right (LTR)',
                  flagEmoji: '🇺🇸',
                  isSelected: !isArabic,
                  onTap: () {
                    Navigator.pop(sheetContext);
                    if (isArabic) {
                      rtlService.changeLanguage('en', context: context);
                    }
                  },
                ),
                const SizedBox(height: 12),

                // Arabic Option
                _LanguageOptionCard(
                  title: 'العربية',
                  subtitle: 'من اليمين إلى اليسار (RTL)',
                  flagEmoji: '🇸🇦',
                  isSelected: isArabic,
                  onTap: () {
                    Navigator.pop(sheetContext);
                    if (!isArabic) {
                      rtlService.changeLanguage('ar', context: context);
                    }
                  },
                ),
              ],
            ),
          );
        },
      );
    },
  );
}

class _LanguageOptionCard extends StatelessWidget {
  const _LanguageOptionCard({
    Key? key,
    required this.title,
    required this.subtitle,
    required this.flagEmoji,
    required this.isSelected,
    required this.onTap,
  }) : super(key: key);

  final String title;
  final String subtitle;
  final String flagEmoji;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: isSelected ? FMColors.magenta.withOpacity(0.12) : FMColors.surfaceElevated,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? FMColors.magenta : FMColors.border.withOpacity(0.6),
            width: isSelected ? 1.8 : 1.0,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: FMColors.surfaceDark,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                flagEmoji,
                style: const TextStyle(fontSize: 20),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: isSelected ? Colors.white : Colors.white70,
                      fontSize: 16,
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: FMColors.textMuted,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            Container(
              width: 24,
              height: 24,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isSelected ? FMColors.magenta : Colors.transparent,
                border: Border.all(
                  color: isSelected ? FMColors.magenta : FMColors.textMuted,
                  width: 1.5,
                ),
              ),
              child: isSelected
                  ? const Icon(
                      Icons.check,
                      size: 16,
                      color: Colors.white,
                    )
                  : null,
            ),
          ],
        ),
      ),
    );
  }
}

class LanguageSwitchPill extends StatelessWidget {
  const LanguageSwitchPill({
    Key? key,
    this.compact = false,
  }) : super(key: key);

  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Consumer<RtlService>(
      builder: (context, rtl, child) {
        final isArabic = rtl.isArabic;
        return InkWell(
          onTap: () => showLanguageBottomSheet(context),
          borderRadius: BorderRadius.circular(20),
          child: Container(
            padding: EdgeInsets.symmetric(
              horizontal: compact ? 10 : 12,
              vertical: compact ? 4 : 6,
            ),
            decoration: BoxDecoration(
              color: FMColors.surfaceDark.withOpacity(0.9),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: FMColors.magenta.withOpacity(0.4)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.language_rounded,
                  size: 15,
                  color: FMColors.magenta,
                ),
                const SizedBox(width: 5),
                Text(
                  isArabic ? 'العربية' : 'EN',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.3,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
