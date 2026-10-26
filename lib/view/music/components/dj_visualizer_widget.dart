import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

enum DJVisualizerMode {
  neonSpectrum,
  beatRing,
  clubLights,
  off,
}

extension DJVisualizerModeExtension on DJVisualizerMode {
  String get nameEn {
    switch (this) {
      case DJVisualizerMode.neonSpectrum:
        return 'Neon Spectrum';
      case DJVisualizerMode.beatRing:
        return 'Beat Ring';
      case DJVisualizerMode.clubLights:
        return 'Club Lights';
      case DJVisualizerMode.off:
        return 'Off';
    }
  }

  String get nameAr {
    switch (this) {
      case DJVisualizerMode.neonSpectrum:
        return 'طيف النيون';
      case DJVisualizerMode.beatRing:
        return 'حلقات الإيقاع';
      case DJVisualizerMode.clubLights:
        return 'أضواء الحفلة';
      case DJVisualizerMode.off:
        return 'إيقاف';
    }
  }

  IconData get icon {
    switch (this) {
      case DJVisualizerMode.neonSpectrum:
        return Icons.equalizer_rounded;
      case DJVisualizerMode.beatRing:
        return Icons.radar_rounded;
      case DJVisualizerMode.clubLights:
        return Icons.flare_rounded;
      case DJVisualizerMode.off:
        return Icons.visibility_off_rounded;
    }
  }
}

/// A high-performance procedural DJ visualizer for the FUN MOMENT player experience.
///
/// Features:
/// - Layered depth with multi-harmonic wave synthesis.
/// - Multicolor neon palette: Electric Cyan, Electric Violet, Hot Magenta, Restrained Amber.
/// - Artwork-driven ambient glow behind the centerpiece.
/// - Subdued resting state when paused.
/// - Lifecycle-aware animation controller (pauses when backgrounded).
/// - 100% procedural: no microphone permission, no competing audio sessions.
class DJVisualizerWidget extends StatefulWidget {
  final DJVisualizerMode mode;
  final bool isPlaying;
  final int bpm;
  final Duration position;
  final Duration duration;
  final bool isCompact;
  final Widget? child;

  const DJVisualizerWidget({
    Key? key,
    required this.mode,
    required this.isPlaying,
    this.bpm = 124,
    this.position = Duration.zero,
    this.duration = Duration.zero,
    this.isCompact = false,
    this.child,
  }) : super(key: key);

  @override
  State<DJVisualizerWidget> createState() => _DJVisualizerWidgetState();
}

class _DJVisualizerWidgetState extends State<DJVisualizerWidget>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  late AnimationController _animController;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 4),
    )..repeat();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden) {
      _animController.stop();
    } else if (state == AppLifecycleState.resumed) {
      if (mounted) _animController.repeat();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.mode == DJVisualizerMode.off) {
      return widget.child ?? const SizedBox.shrink();
    }

    return AnimatedBuilder(
      animation: _animController,
      builder: (context, child) {
        return CustomPaint(
          painter: _DJVisualizerPainter(
            mode: widget.mode,
            animValue: _animController.value,
            isPlaying: widget.isPlaying,
            bpm: widget.bpm > 0 ? widget.bpm : 124,
            progressSeconds: widget.position.inMilliseconds / 1000.0,
            isCompact: widget.isCompact,
          ),
          child: child,
        );
      },
      child: widget.child,
    );
  }
}

class _DJVisualizerPainter extends CustomPainter {
  final DJVisualizerMode mode;
  final double animValue;
  final bool isPlaying;
  final int bpm;
  final double progressSeconds;
  final bool isCompact;

  _DJVisualizerPainter({
    required this.mode,
    required this.animValue,
    required this.isPlaying,
    required this.bpm,
    required this.progressSeconds,
    required this.isCompact,
  });

  // DJ Multicolor Palette Tokens
  static const Color colorCyan = Color(0xFF2DE2FF);
  static const Color colorViolet = Color(0xFF8A2BE2);
  static const Color colorMagenta = Color(0xFFFF4FB3);
  static const Color colorAmber = Color(0xFFFFB703);

  Color _getSpectrumColor(double normalizedPos) {
    if (normalizedPos < 0.35) {
      return Color.lerp(colorCyan, colorViolet, normalizedPos / 0.35)!;
    } else if (normalizedPos < 0.75) {
      return Color.lerp(colorViolet, colorMagenta, (normalizedPos - 0.35) / 0.40)!;
    } else {
      return Color.lerp(colorMagenta, colorAmber, (normalizedPos - 0.75) / 0.25)!;
    }
  }

  @override
  void paint(Canvas canvas, Size size) {
    if (size.width <= 0 || size.height <= 0) return;

    final double tempoHz = bpm / 60.0;
    final double time = progressSeconds > 0 ? progressSeconds : animValue * 4.0;

    // Sharp rise on beat, smooth quadratic decay
    final double beatPhase = (time * tempoHz) % 1.0;
    final double beatPulse = isPlaying
        ? math.pow(1.0 - beatPhase, 3.2).toDouble()
        : 0.12;

    switch (mode) {
      case DJVisualizerMode.neonSpectrum:
        _paintNeonSpectrum(canvas, size, time, beatPulse);
        break;
      case DJVisualizerMode.beatRing:
        _paintBeatRing(canvas, size, time, beatPulse);
        break;
      case DJVisualizerMode.clubLights:
        _paintClubLights(canvas, size, time, beatPulse);
        break;
      case DJVisualizerMode.off:
        break;
    }
  }

  /// Neon Spectrum Mode:
  /// Dual-layer depth (blurred reflection backdrop + crisp foreground bars)
  /// with multi-harmonic wave oscillation, multicolor gradient, and floating peak caps.
  void _paintNeonSpectrum(Canvas canvas, Size size, double time, double beatPulse) {
    final int barCount = isCompact ? 20 : 32;
    final double spacing = size.width / (barCount * 1.55);
    final double barWidth = spacing * 0.88;
    final double totalWidth = barCount * spacing;
    final double startX = (size.width - totalWidth) / 2;
    final double baseY = isCompact ? size.height * 0.94 : size.height * 0.90;
    final double maxBarHeight = isCompact ? size.height * 0.75 : size.height * 0.36;

    final Paint barPaint = Paint()..style = PaintingStyle.fill;
    final Paint glowPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = barWidth * 1.6
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6);

    final Paint depthShadowPaint = Paint()..style = PaintingStyle.fill;

    for (int i = 0; i < barCount; i++) {
      final double normalizedIdx = (i - (barCount / 2)).abs() / (barCount / 2);
      final double freqFactor = 1.0 - (normalizedIdx * 0.35);
      final double colorPos = i / (barCount - 1);

      // Multi-harmonic natural wave synthesis
      final double wave1 = math.sin((time * 3.8) + (i * 0.38));
      final double wave2 = math.cos((time * 2.1) - (i * 0.27));
      final double wave3 = math.sin((time * 5.9) + (i * 0.71)) * 0.45;
      final double wave4 = math.cos((time * 1.3) + (i * 0.15)) * 0.35;

      double heightFactor = ((wave1 + wave2 + wave3 + wave4) / 2.8).abs();
      if (isPlaying) {
        heightFactor = (heightFactor * 0.62) + (beatPulse * 0.58 * freqFactor);
      } else {
        // Resting ambient state: smooth, subdued breathing wave
        heightFactor = 0.10 + 0.06 * math.sin(time * 1.8 + i * 0.25);
      }
      heightFactor = heightFactor.clamp(0.06, 1.0);

      final double barHeight = maxBarHeight * heightFactor;
      final double x = startX + (i * spacing) + (spacing - barWidth) / 2;
      final double top = baseY - barHeight;

      final Color barColor = _getSpectrumColor(colorPos);

      // Layer 1: Background Depth Shadow / Reflection
      if (!isCompact) {
        final double shadowTop = baseY - (barHeight * 0.7);
        final Rect shadowRect = Rect.fromLTWH(x - 1, shadowTop, barWidth + 2, barHeight * 0.7);
        final RRect roundedShadow = RRect.fromRectAndRadius(shadowRect, const Radius.circular(3));
        depthShadowPaint.color = barColor.withOpacity(0.12 * heightFactor);
        canvas.drawRRect(roundedShadow, depthShadowPaint);
      }

      // Layer 2: Neon Aura Glow
      final Rect barRect = Rect.fromLTWH(x, top, barWidth, barHeight);
      final RRect roundedBar = RRect.fromRectAndRadius(barRect, const Radius.circular(3));
      glowPaint.color = barColor.withOpacity(0.24 * heightFactor);
      canvas.drawRRect(roundedBar, glowPaint);

      // Layer 3: Foreground Solid Neon Gradient
      barPaint.shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          barColor,
          barColor.withOpacity(0.45),
        ],
      ).createShader(barRect);
      canvas.drawRRect(roundedBar, barPaint);

      // Layer 4: Specular Peak Cap
      if (isPlaying && heightFactor > 0.38) {
        final Paint capPaint = Paint()
          ..color = Colors.white.withOpacity(0.92)
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(x + barWidth / 2, top - 2.8), 1.6, capPaint);
      }
    }
  }

  /// Beat Ring Mode:
  /// Concentric expanding neon soundwaves, ambient artwork-framing halo,
  /// and rotating orbital energy particles.
  void _paintBeatRing(Canvas canvas, Size size, double time, double beatPulse) {
    final Offset center = Offset(size.width / 2, size.height * (isCompact ? 0.5 : 0.44));
    final double maxRadius = math.min(size.width, size.height) * (isCompact ? 0.46 : 0.45);

    // 1. Artwork Ambient Backdrop Glow
    final Paint centerGlow = Paint()
      ..shader = RadialGradient(
        colors: [
          colorCyan.withOpacity(0.24 * (0.6 + 0.4 * beatPulse)),
          colorViolet.withOpacity(0.16 * (0.5 + 0.5 * beatPulse)),
          colorMagenta.withOpacity(0.08),
          Colors.transparent,
        ],
        stops: const [0.0, 0.45, 0.75, 1.0],
      ).createShader(Rect.fromCircle(center: center, radius: maxRadius * 0.95));
    canvas.drawCircle(center, maxRadius * 0.95, centerGlow);

    // 2. Concentric Beat Rings
    const int ringCount = 4;
    for (int r = 0; r < ringCount; r++) {
      final double phaseOffset = (time * (bpm / 60.0) + (r * 0.25)) % 1.0;
      final double ringRadius = (maxRadius * 0.38) + (phaseOffset * maxRadius * 0.58);
      final double ringAlpha = (1.0 - phaseOffset).clamp(0.0, 1.0);

      final Color ringColor = Color.lerp(
        colorCyan,
        colorMagenta,
        (r / ringCount).clamp(0.0, 1.0),
      )!;

      // Soft Ring Glow
      final Paint ringGlow = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.6
        ..color = ringColor.withOpacity(ringAlpha * 0.32 * (0.5 + 0.5 * beatPulse))
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 5);
      canvas.drawCircle(center, ringRadius, ringGlow);

      // Core Laser Ring
      final Paint ringStroke = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.6
        ..color = ringColor.withOpacity(ringAlpha * 0.80);
      canvas.drawCircle(center, ringRadius, ringStroke);

      // Orbital Energy Particles along ring
      if (!isCompact && isPlaying) {
        const int ticks = 8;
        final double rotAngle = time * (0.45 + (r * 0.25));
        for (int t = 0; t < ticks; t++) {
          final double theta = rotAngle + (t * (2 * math.pi / ticks));
          final double px = center.dx + ringRadius * math.cos(theta);
          final double py = center.dy + ringRadius * math.sin(theta);

          final Paint sparkPaint = Paint()
            ..color = Colors.white.withOpacity(ringAlpha * (0.6 + 0.4 * beatPulse))
            ..style = PaintingStyle.fill;
          canvas.drawCircle(Offset(px, py), 2.0, sparkPaint);
        }
      }
    }
  }

  /// Club Lights Mode:
  /// Dynamic triple-source angled lasers, atmospheric club haze, and soft downbeat strobe.
  void _paintClubLights(Canvas canvas, Size size, double time, double beatPulse) {
    // 1. Atmospheric Ambient Club Fog
    final Paint fogPaint = Paint()
      ..shader = RadialGradient(
        center: const Alignment(0, -0.25),
        radius: 1.25,
        colors: [
          colorViolet.withOpacity(0.18 * (0.5 + 0.5 * beatPulse)),
          colorMagenta.withOpacity(0.09),
          Colors.transparent,
        ],
        stops: const [0.0, 0.65, 1.0],
      ).createShader(Rect.fromLTWH(0, 0, size.width, size.height));
    canvas.drawRect(Rect.fromLTWH(0, 0, size.width, size.height), fogPaint);

    // 2. Projector Sources
    final Offset leftSource = Offset(size.width * 0.10, size.height * 0.04);
    final Offset rightSource = Offset(size.width * 0.90, size.height * 0.04);
    final Offset centerSource = Offset(size.width * 0.50, size.height * 0.02);

    // Laser Trigonometric Sweeps
    final double sweepLeft = math.sin(time * 1.7) * (size.width * 0.40);
    final double sweepRight = math.cos(time * 2.1) * (size.width * 0.40);
    final double sweepCenter = math.sin(time * 0.9) * (size.width * 0.28);
    final double targetY = size.height * 0.88;

    // Left Laser Beam (Electric Cyan)
    _drawLaserBeam(
      canvas: canvas,
      start: leftSource,
      end: Offset((size.width * 0.52) + sweepLeft, targetY),
      color: colorCyan,
      beamWidth: 52.0 + (28.0 * beatPulse),
      intensity: 0.44 * (0.6 + 0.4 * beatPulse),
    );

    // Right Laser Beam (Hot Magenta)
    _drawLaserBeam(
      canvas: canvas,
      start: rightSource,
      end: Offset((size.width * 0.48) - sweepRight, targetY),
      color: colorMagenta,
      beamWidth: 52.0 + (28.0 * beatPulse),
      intensity: 0.44 * (0.6 + 0.4 * beatPulse),
    );

    // Center Overhead Beam (Electric Violet)
    if (!isCompact) {
      _drawLaserBeam(
        canvas: canvas,
        start: centerSource,
        end: Offset((size.width * 0.50) + sweepCenter, targetY),
        color: colorViolet,
        beamWidth: 42.0 + (20.0 * beatPulse),
        intensity: 0.36 * (0.5 + 0.5 * beatPulse),
      );
    }

    // Downbeat Strobe Burst (Soft, elegant ceiling flare on downbeat)
    if (isPlaying && beatPulse > 0.68) {
      final double burstAlpha = ((beatPulse - 0.68) / 0.32).clamp(0.0, 1.0);
      final Paint strobePaint = Paint()
        ..shader = RadialGradient(
          colors: [
            Colors.white.withOpacity(burstAlpha * 0.30),
            colorCyan.withOpacity(burstAlpha * 0.15),
            Colors.transparent,
          ],
        ).createShader(Rect.fromCircle(
            center: Offset(size.width / 2, size.height * 0.08), radius: size.width * 0.45));
      canvas.drawCircle(Offset(size.width / 2, size.height * 0.08), size.width * 0.45, strobePaint);
    }
  }

  void _drawLaserBeam({
    required Canvas canvas,
    required Offset start,
    required Offset end,
    required Color color,
    required double beamWidth,
    required double intensity,
  }) {
    final Path conePath = Path()
      ..moveTo(start.dx, start.dy)
      ..lineTo(end.dx - beamWidth / 2, end.dy)
      ..lineTo(end.dx + beamWidth / 2, end.dy)
      ..close();

    final Paint beamPaint = Paint()
      ..shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          color.withOpacity(intensity * 0.85),
          color.withOpacity(intensity * 0.22),
          Colors.transparent,
        ],
        stops: const [0.0, 0.62, 1.0],
      ).createShader(conePath.getBounds());

    canvas.drawPath(conePath, beamPaint);

    // Sharp Core Laser Filament
    final Paint coreLaser = Paint()
      ..color = Colors.white.withOpacity(intensity * 0.88)
      ..strokeWidth = 1.7
      ..style = PaintingStyle.stroke;
    canvas.drawLine(start, end, coreLaser);
  }

  @override
  bool shouldRepaint(covariant _DJVisualizerPainter oldDelegate) {
    return oldDelegate.animValue != animValue ||
        oldDelegate.isPlaying != isPlaying ||
        oldDelegate.mode != mode ||
        oldDelegate.progressSeconds != progressSeconds ||
        oldDelegate.isCompact != isCompact;
  }
}
