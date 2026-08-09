import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

class SectionTitle extends StatelessWidget {
  const SectionTitle({
    Key? key,
    required this.cc,
    required this.title,
    required this.pressed,
    this.hasSeeAllBtn = true,
  }) : super(key: key);

  final cc;
  final String title;
  final VoidCallback pressed;
  final bool hasSeeAllBtn;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) {
        final resolvedTitle = asProvider.getString(title);
        return Row(
          children: [
            Expanded(
              child: Text(
                resolvedTitle,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ),
            if (hasSeeAllBtn)
              InkWell(
                splashColor: Colors.transparent,
                highlightColor: Colors.transparent,
                onTap: pressed,
                child: Row(
                  children: [
                    Text(
                      asProvider.getString('See all'),
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: FMColors.magenta,
                          ),
                    ),
                    const SizedBox(width: 6),
                    const Icon(
                      Icons.arrow_forward_ios_rounded,
                      color: FMColors.magenta,
                      size: 14,
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}

