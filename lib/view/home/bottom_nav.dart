import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:provider/provider.dart';

class BottomNav extends StatelessWidget {
  const BottomNav({
    Key? key,
    required this.currentIndex,
    required this.onTabTapped,
    this.isSeller = false,
  }) : super(key: key);

  final int currentIndex;
  final Function(int) onTabTapped;
  final bool isSeller;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) {
        final items = isSeller
            ? <_NavItem>[
                _NavItem('assets/svg/home-icon.svg', asProvider.getString('Dashboard')),
                _NavItem('assets/svg/search-icon.svg', asProvider.getString('Services')),
                _NavItem('assets/svg/orders-icon.svg', asProvider.getString('Orders')),
                _NavItem('assets/svg/menu_job.svg', asProvider.getString('Jobs')),
                _NavItem('assets/svg/user.svg', asProvider.getString('Profile')),
              ]
            : <_NavItem>[
                _NavItem('assets/svg/home-icon.svg', asProvider.getString('Home')),
                _NavItem('assets/svg/search-icon.svg', asProvider.getString('Discover')),
                _NavItem('assets/svg/orders-icon.svg', asProvider.getString('Bookings')),
                _NavItem('assets/svg/saved-icon.svg', asProvider.getString('Saved')),
                _NavItem('assets/svg/user.svg', asProvider.getString('Profile')),
              ];

        return SafeArea(
          top: false,
          child: Container(
            margin: const EdgeInsets.fromLTRB(4, 4, 4, 8),
            padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 2),
            decoration: BoxDecoration(
              color: FMColors.navigationSurface.withOpacity(.96),
              borderRadius: BorderRadius.circular(FMRadii.xl),
              border: Border.all(color: FMColors.border.withOpacity(.75)),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x66000000),
                  blurRadius: 20,
                  offset: Offset(0, 12),
                ),
              ],
            ),
            child: BottomNavigationBar(
              type: BottomNavigationBarType.fixed,
              elevation: 0,
              backgroundColor: Colors.transparent,
              selectedItemColor: FMColors.magenta,
              unselectedItemColor: FMColors.textMuted,
              selectedFontSize: 9.5,
              unselectedFontSize: 8.5,
              selectedLabelStyle: const TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w700,
                letterSpacing: -0.5,
                height: 1.2,
              ),
              unselectedLabelStyle: const TextStyle(
                fontSize: 8.5,
                fontWeight: FontWeight.w500,
                letterSpacing: -0.5,
                height: 1.2,
              ),
              onTap: onTabTapped,
              currentIndex: currentIndex,
              items: List.generate(items.length, (index) {
                final item = items[index];
                final active = index == currentIndex;
                return BottomNavigationBarItem(
                  label: item.label,
                  icon: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    curve: Curves.easeOut,
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
                    decoration: BoxDecoration(
                      color: active ? FMColors.magenta.withOpacity(.12) : Colors.transparent,
                      borderRadius: BorderRadius.circular(FMRadii.pill),
                      boxShadow: active ? FMShadows.subtleGlow : const [],
                    ),
                    child: SvgPicture.asset(
                      item.asset,
                      color: active ? FMColors.magenta : FMColors.textMuted,
                      height: 18,
                      width: 18,
                    ),
                  ),
                );
              }),
            ),
          ),
        );
      },
    );
  }
}

class _NavItem {
  const _NavItem(this.asset, this.label);

  final String asset;
  final String label;
}
