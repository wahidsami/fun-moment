import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/country_states_service.dart';
import 'package:funmoments/view/auth/signup/dropdowns/country_dropdown.dart';
import 'package:funmoments/view/auth/signup/dropdowns/state_dropdown.dart';
import 'package:funmoments/view/auth/signup/dropdowns/area_dropdown.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class CountryStatesDropdowns extends StatefulWidget {
  const CountryStatesDropdowns({Key? key}) : super(key: key);

  @override
  State<CountryStatesDropdowns> createState() => _CountryStatesDropdownsState();
}

class _CountryStatesDropdownsState extends State<CountryStatesDropdowns> {
  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<CountryStatesService>(
        builder: (context, provider, child) => Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                //dropdown and search box
                const SizedBox(
                  width: 17,
                ),

                // Country dropdown ===============>
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      lnProvider.getString("Choose country"),
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                    const CountryDropdown(),
                  ],
                ),

                const SizedBox(
                  height: 25,
                ),
                // States dropdown ===============>
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      lnProvider.getString("Choose city"),
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                    const StateDropdown(),
                  ],
                ),

                const SizedBox(
                  height: 25,
                ),

                // Area dropdown ===============>
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      lnProvider.getString("Choose Area"),
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                    const AreaDropdown(),
                  ],
                )
              ],
            ));
  }

  dropdownPlaceholder({required String hintText}) {
    return Builder(
      builder: (context) => FMSurfaceCard(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Text(
                hintText,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: FMColors.textPrimary,
                    ),
              ),
            ),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: FMColors.textMuted,
            ),
          ],
        ),
      ),
    );
  }
}
