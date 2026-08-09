import 'package:flutter/material.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/home/landing_page.dart';
import 'package:funmoments/view/intro/introduction_page.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../home/homepage_helper.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({Key? key}) : super(key: key);

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  bool _started = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_started) {
        _started = true;
        _startInitialization();
      }
    });
  }

  Future<void> _startInitialization() async {
    await runAtstart(context);
    initializeLNProvider(context);
    final prefs = await SharedPreferences.getInstance();
    final intro = prefs.getBool('intro');
    if (!mounted) return;

    if (intro == null) {
      Navigator.pushReplacement<void, void>(
        context,
        MaterialPageRoute<void>(
          builder: (BuildContext context) => const IntroductionPage(),
        ),
      );
      return;
    }

    context.toUntilPage(const LandingPage());
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return Scaffold(
      backgroundColor: FMColors.background,
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: const Alignment(-0.7, -0.6),
                  radius: 1.25,
                  colors: [
                    FMColors.magenta.withOpacity(.16),
                    FMColors.background,
                    FMColors.cyan.withOpacity(.08),
                  ],
                  stops: const [0.0, 0.62, 1.0],
                ),
              ),
            ),
          ),
          Positioned(
            top: -size.height * 0.08,
            right: -size.width * 0.22,
            child: _GlowOrb(color: FMColors.magenta.withOpacity(.22)),
          ),
          Positioned(
            bottom: -size.height * 0.07,
            left: -size.width * 0.16,
            child: _GlowOrb(color: FMColors.cyan.withOpacity(.16)),
          ),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const FMBrandLogo(height: 88),
                  const SizedBox(height: 28),
                  Text(
                    'Discover your next moment',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          color: FMColors.textPrimary,
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Premium marketplace experiences for customers and providers',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: FMColors.textMuted,
                        ),
                  ),
                  const SizedBox(height: 28),
                  const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.2,
                      valueColor:
                          AlwaysStoppedAnimation<Color>(FMColors.magenta),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    appVersion,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: FMColors.textMuted,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GlowOrb extends StatelessWidget {
  const _GlowOrb({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 260,
      height: 260,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(
          colors: [color, Colors.transparent],
          radius: 0.7,
        ),
      ),
    );
  }
}

