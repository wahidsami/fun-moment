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

/// A high-performance procedural DJ visualizer for FUN MOMENT Now Playing experience.
///
/// Note: Audio analysis is mathematically simulated based on track tempo (BPM),
/// playback state, and timeline progress. No microphone or recording permissions
/// are required.
class DJVisualizerWidget extends StatefulWidget {
  final DJVisualizerMode mode;
  final bool isPlaying;
  final int bpm;
  final Duration position;
  final Duration duration;
  final Widget? child;

  const DJVisualizerWidget({
    Key? key,
    required this.mode,
    required this.isPlaying,
    this.bpm = 124,
    this.position = Duration.zero,
    this.duration = Duration.zero,
    this.child,
  }) : super(key: key);

  @override
  State<DJVisualizerWidget> createState() => _DJVisualizerWidgetState();
}

class _DJVisualizerWidgetState extends State<DJVisualizerWidget>
    with SingleTickerProviderStateMixin {
  late AnimationController _animController;

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 4),
    )..repeat();
  }

  @override
  void dispose() {
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

  _DJVisualizerPainter({
    required this.mode,
    required this.animValue,
    required this.isPlaying,
    required this.bpm,
    required this.progressSeconds,
  });

  @override
  void paint(Canvas canvas, Size size) {
    if (size.width <= 0 || size.height <= 0) return;

    final double tempoHz = bpm / 60.0;
    final double time = progressSeconds > 0
        ? progressSeconds
        : animValue * 4.0;

    // Beat pulse: sharp rise on each beat, smooth decay
    final double beatPhase = (time * tempoHz) % 1.0;
    final double beatPulse = isPlaying
        ? math.pow(1.0 - beatPhase, 3.5).toDouble()
        : 0.15;

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
  /// Vibrant dancing equalizer bars with gradient glow and peak caps.
  void _paintNeonSpectrum(Canvas canvas, Size size, double time, double beatPulse) {
    const int barCount = 32;
    final double spacing = size.width / (barCount * 1.6);
    final double barWidth = spacing * 0.9;
    final double totalWidth = barCount * spacing;
    final double startX = (size.width - totalWidth) / 2;
    final double baseY = size.height * 0.88;
    final double maxBarHeight = size.height * 0.38;

    final Paint barPaint = Paint()..style = PaintingStyle.fill;
    final Paint glowPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = barWidth * 1.5
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 8);

    for (int i = 0; i < barCount; i++) {
      // Symmetrical frequency curve (bass in middle, treble at ends)
      final double normalizedIdx = (i - (barCount / 2)).abs() / (barCount / 2);
      final double freqFactor = 1.0 - (normalizedIdx * 0.4);

      // Multi-harmonic sine wave simulation
      final double wave1 = math.sin((time * 4.5) + (i * 0.45));
      final double wave2 = math.cos((time * 2.8) - (i * 0.32));
      final double wave3 = math.sin((time * 7.2) + (i * 0.85)) * 0.5;

      double heightFactor = ((wave1 + wave2 + wave3) / 2.5).abs();
      if (isPlaying) {
        heightFactor = (heightFactor * 0.65) + (beatPulse * 0.55 * freqFactor);
      } else {
        heightFactor = 0.12 + 0.08 * math.sin(time * 2.0 + i * 0.3);
      }
      heightFactor = heightFactor.clamp(0.06, 1.0);

      final double barHeight = maxBarHeight * heightFactor;
      final double x = startX + (i * spacing) + (spacing - barWidth) / 2;
      final double top = baseY - barHeight;

      final Rect barRect = Rect.fromLTWH(x, top, barWidth, barHeight);
      final RRect roundedBar = RRect.fromRectAndRadius(barRect, const Radius.circular(3));

      // Color interpolation: Cyan (bass/center) -> Magenta -> Orange/Gold (treble)
      final Color color = Color.lerp(
        FMColors.cyan,
        FMColors.magenta,
        (i / barCount).clamp(0.0, 1.0),
      )!;

      // Draw subtle neon glow
      glowPaint.color = color.withOpacity(0.3 * heightFactor);
      canvas.drawRRect(roundedBar, glowPaint);

      // Draw bar gradient fill
      barPaint.shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          color,
          color.withOpacity(0.4),
        ],
      ).createShader(barRect);
      canvas.drawRRect(roundedBar, barPaint);

      // Draw peak dot / cap
      if (isPlaying && heightFactor > 0.4) {
        final Paint capPaint = Paint()
          ..color = Colors.white.withOpacity(0.9)
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(x + barWidth / 2, top - 3), 1.5, capPaint);
      }
    }
  }

  /// Beat Ring Mode:
  /// Concentric neon soundwave rings and orbital energy pulses radiating outward.
  void _paintBeatRing(Canvas canvas, Size size, double time, double beatPulse) {
    final Offset center = Offset(size.width / 2, size.height * 0.45);
    final double maxRadius = math.min(size.width, size.height) * 0.44;

    // Ambient central glow
    final Paint centerGlow = Paint()
      ..shader = RadialGradient(
        colors: [
          FMColors.cyan.withOpacity(0.25 * (0.6 + 0.4 * beatPulse)),
          FMColors.magenta.withOpacity(0.12 * (0.5 + 0.5 * beatPulse)),
          Colors.transparent,
        ],
        stops: const [0.0, 0.55, 1.0],
      ).createShader(Rect.fromCircle(center: center, radius: maxRadius * 0.9));
    canvas.drawCircle(center, maxRadius * 0.9, centerGlow);

    const int ringCount = 4;
    for (int r = 0; r < ringCount; r++) {
      final double phaseOffset = (time * (bpm / 60.0) + (r * 0.25)) % 1.0;
      final double ringRadius = (maxRadius * 0.42) + (phaseOffset * maxRadius * 0.55);
      final double ringAlpha = (1.0 - phaseOffset).clamp(0.0, 1.0);

      final Color ringColor = Color.lerp(
        FMColors.cyan,
        FMColors.magenta,
        (r / ringCount).clamp(0.0, 1.0),
      )!;

      // Ring glow
      final Paint ringGlow = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.5
        ..color = ringColor.withOpacity(ringAlpha * 0.35 * (0.5 + 0.5 * beatPulse))
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6);
      canvas.drawCircle(center, ringRadius, ringGlow);

      // Core ring stroke
      final Paint ringStroke = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.6
        ..color = ringColor.withOpacity(ringAlpha * 0.75);
      canvas.drawCircle(center, ringRadius, ringStroke);

      // Orbital energy ticks around ring
      const int ticks = 8;
      final double rotAngle = time * (0.5 + (r * 0.3));
      for (int t = 0; t < ticks; t++) {
        final double theta = rotAngle + (t * (2 * math.pi / ticks));
        final double px = center.dx + ringRadius * math.cos(theta);
        final double py = center.dy + ringRadius * math.sin(theta);

        final Paint tickPaint = Paint()
          ..color = Colors.white.withOpacity(ringAlpha * (0.5 + 0.5 * beatPulse))
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(px, py), 2.2, tickPaint);
      }
    }
  }

  /// Club Lights Mode:
  /// Dynamic DJ laser sweeps cutting through dark space with neon color shifts and strobe pulse.
  void _paintClubLights(Canvas canvas, Size size, double time, double beatPulse) {
    // Ambient dark club fog
    final Paint fogPaint = Paint()
      ..shader = RadialGradient(
        center: const Alignment(0, -0.2),
        radius: 1.2,
        colors: [
          FMColors.magenta.withOpacity(0.18 * (0.4 + 0.6 * beatPulse)),
          FMColors.cyan.withOpacity(0.08),
          Colors.transparent,
        ],
      ).createShader(Rect.fromLTWH(0, 0, size.width, size.height));
    canvas.drawRect(Rect.fromLTWH(0, 0, size.width, size.height), fogPaint);

    // Left and Right top laser sources
    final Offset leftSource = Offset(size.width * 0.1, size.height * 0.05);
    final Offset rightSource = Offset(size.width * 0.9, size.height * 0.05);

    // Laser sweeps
    final double sweep1 = math.sin(time * 1.8) * (size.width * 0.42);
    final double sweep2 = math.cos(time * 2.2) * (size.width * 0.42);
    final double targetY = size.height * 0.85;

    _drawLaserBeam(
      canvas: canvas,
      start: leftSource,
      end: Offset((size.width * 0.5) + sweep1, targetY),
      color: FMColors.cyan,
      beamWidth: 50.0 + (30.0 * beatPulse),
      intensity: 0.45 * (0.6 + 0.4 * beatPulse),
    );

    _drawLaserBeam(
      canvas: canvas,
      start: rightSource,
      end: Offset((size.width * 0.5) - sweep2, targetY),
      color: FMColors.magenta,
      beamWidth: 50.0 + (30.0 * beatPulse),
      intensity: 0.45 * (0.6 + 0.4 * beatPulse),
    );

    // Center ceiling strobe burst on downbeats
    if (isPlaying && beatPulse > 0.65) {
      final double burstAlpha = ((beatPulse - 0.65) / 0.35).clamp(0.0, 1.0);
      final Paint strobePaint = Paint()
        ..shader = RadialGradient(
          colors: [
            Colors.white.withOpacity(burstAlpha * 0.35),
            FMColors.cyanLight.withOpacity(burstAlpha * 0.15),
            Colors.transparent,
          ],
        ).createShader(Rect.fromCircle(center: Offset(size.width / 2, size.height * 0.08), radius: size.width * 0.45));
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
          color.withOpacity(intensity * 0.25),
          Colors.transparent,
        ],
        stops: const [0.0, 0.6, 1.0],
      ).createShader(conePath.getBounds());

    canvas.drawPath(conePath, beamPaint);

    // Sharp central laser line
    final Paint coreLaser = Paint()
      ..color = Colors.white.withOpacity(intensity * 0.9)
      ..strokeWidth = 1.8
      ..style = PaintingStyle.stroke;
    canvas.drawLine(start, end, coreLaser);
  }

  @override
  bool shouldRepaint(covariant _DJVisualizerPainter oldDelegate) {
    return oldDelegate.animValue != animValue ||
        oldDelegate.isPlaying != isPlaying ||
        oldDelegate.mode != mode ||
        oldDelegate.progressSeconds != progressSeconds;
  }
}
