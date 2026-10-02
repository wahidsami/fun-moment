import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/provider_registration_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class ProviderSubtypeSelector extends StatelessWidget {
  const ProviderSubtypeSelector({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) => Consumer<ProviderRegistrationService>(
        builder: (context, provider, child) {
          final isIndividual = provider.model.sellerType == 1;

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                asProvider.getString("Provider Type"),
                style: const TextStyle(
                  color: FMColors.textPrimary,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: GestureDetector(
                      onTap: () => provider.setSellerType(1),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
                        decoration: BoxDecoration(
                          color: isIndividual ? FMColors.cyan.withOpacity(0.15) : FMColors.surface,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: isIndividual ? FMColors.cyan : FMColors.border,
                            width: isIndividual ? 2 : 1,
                          ),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              Icons.person_rounded,
                              color: isIndividual ? FMColors.cyan : FMColors.textMuted,
                              size: 26,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              asProvider.getString("Individual"),
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: isIndividual ? FMColors.textPrimary : FMColors.textMuted,
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              asProvider.getString("Performer, Solo, Specialist"),
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: FMColors.textMuted,
                                fontSize: 10,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: GestureDetector(
                      onTap: () => provider.setSellerType(2),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
                        decoration: BoxDecoration(
                          color: !isIndividual ? FMColors.magenta.withOpacity(0.15) : FMColors.surface,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: !isIndividual ? FMColors.magenta : FMColors.border,
                            width: !isIndividual ? 2 : 1,
                          ),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              Icons.business_rounded,
                              color: !isIndividual ? FMColors.magenta : FMColors.textMuted,
                              size: 26,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              asProvider.getString("Company"),
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: !isIndividual ? FMColors.textPrimary : FMColors.textMuted,
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              asProvider.getString("Agency, Business, Est."),
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: FMColors.textMuted,
                                fontSize: 10,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }
}
