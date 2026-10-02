import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class CountryDropdown extends StatelessWidget {
  final textWidth;
  const CountryDropdown({this.textWidth, Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Consumer<CountryDropdownService>(
      builder: (context, p, child) {
        if (p.selectedCountryId != saudiCountryId) {
          p.preselectSaudi();
        }
        return Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
          decoration: BoxDecoration(
            color: FMColors.surfaceElevated.withOpacity(0.5),
            border: Border.all(
              color: FMColors.border,
            ),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(
                    Icons.flag_outlined,
                    size: 18,
                    color: FMColors.cyan,
                  ),
                  const SizedBox(width: 10),
                  Text(
                    lnProvider.getString('Saudi Arabia'),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: FMColors.textPrimary,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: FMColors.cyan.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  lnProvider.getString('Fixed'),
                  style: const TextStyle(
                    color: FMColors.cyan,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
