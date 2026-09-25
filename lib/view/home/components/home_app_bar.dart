import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/tabs/settings/profile_edit.dart';
import 'package:funmoments/view/utils/language_selector_sheet.dart';

class HomeAppBar extends StatelessWidget {
  const HomeAppBar({
    Key? key,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, _) => Consumer<ProfileService>(
        builder: (context, profileProvider, child) {
          final name = profileProvider.profileDetails?.userDetails.name;
          final image = profileProvider.profileImage;

          return FMSurfaceCard(
            gradient: LinearGradient(
              colors: [
                FMColors.surfaceElevated,
                FMColors.card.withOpacity(.88),
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            child: Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (BuildContext context) => const ProfileEditPage(),
                        ),
                      );
                    },
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name == null
                              ? asProvider.getString('Welcome to FUN MOMENT')
                              : asProvider.getString('Welcome back'),
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: FMColors.textMuted,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          name ?? asProvider.getString('Guest'),
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                const LanguageSwitchPill(compact: true),
                const SizedBox(width: 10),
                InkWell(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (BuildContext context) => const ProfileEditPage(),
                      ),
                    );
                  },
                  child: image != null
                      ? ClipRRect(
                          borderRadius: BorderRadius.circular(18),
                          child: Image.network(
                            image,
                            height: 52,
                            width: 52,
                            fit: BoxFit.cover,
                          ),
                        )
                      : const FMBrandLogo(height: 34, glow: false),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

