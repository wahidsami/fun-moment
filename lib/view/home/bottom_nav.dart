import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

class BottomNav extends StatelessWidget {
  const BottomNav({
    Key? key,
    required this.currentIndex,
    required this.onTabTapped,
  }) : super(key: key);

  final int currentIndex;
  final Function(int) onTabTapped;

  @override
  Widget build(BuildContext context) {
    final items = <_NavItem>[
      _NavItem('assets/svg/home-icon.svg', lnProvider.getString('Home')),
      _NavItem('assets/svg/search-icon.svg', lnProvider.getString('Discover')),
      _NavItem('assets/svg/orders-icon.svg', lnProvider.getString('Bookings')),
      _NavItem('assets/svg/saved-icon.svg', lnProvider.getString('Saved')),
      _NavItem('assets/svg/user.svg', lnProvider.getString('Profile')),
    ];

    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
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
          selectedLabelStyle: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
          unselectedLabelStyle: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w500,
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
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                  color: active ? FMColors.magenta.withOpacity(.12) : Colors.transparent,
                  borderRadius: BorderRadius.circular(FMRadii.pill),
                  boxShadow: active ? FMShadows.subtleGlow : const [],
                ),
                child: SvgPicture.asset(
                  item.asset,
                  color: active ? FMColors.magenta : FMColors.textMuted,
                  height: 20,
                  width: 20,
                ),
              ),
            );
          }),
        ),
      ),
    );
  }
}

class _NavItem {
  const _NavItem(this.asset, this.label);

  final String asset;
  final String label;
}
