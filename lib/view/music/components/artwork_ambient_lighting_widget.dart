import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/music/components/music_artwork_widget.dart';

/// Artwork-driven ambient lighting widget.
///
/// Features:
/// - Smooth cross-fade transition when tracks or artwork change.
/// - Dynamic multi-layered ambient aura radiating behind the centerpiece.
/// - Graceful fallback when artwork is missing or loading.
/// - High-performance: purely GPU-accelerated gradient shadows and blur.
class ArtworkAmbientLightingWidget extends StatelessWidget {
  final String? imageUrl;
  final double size;
  final double borderRadius;
  final bool isPlaying;
  final Widget? overlayChild;

  const ArtworkAmbientLightingWidget({
    Key? key,
    required this.imageUrl,
    required this.size,
    this.borderRadius = 22.0,
    this.isPlaying = true,
    this.overlayChild,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 500),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      transitionBuilder: (child, animation) {
        return FadeTransition(
          opacity: animation,
          child: child,
        );
      },
      child: Container(
        key: ValueKey<String>(imageUrl ?? 'no-artwork-placeholder'),
        width: size,
        height: size,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(borderRadius),
          boxShadow: [
            // Layer 1: Expansive Deep Cyan Aura
            BoxShadow(
              color: isPlaying
                  ? const Color(0xFF2DE2FF).withOpacity(0.32)
                  : const Color(0xFF2DE2FF).withOpacity(0.12),
              blurRadius: 40,
              spreadRadius: 4,
              offset: const Offset(0, 8),
            ),
            // Layer 2: Electric Magenta Ambient Rim
            BoxShadow(
              color: isPlaying
                  ? const Color(0xFFFF4FB3).withOpacity(0.24)
                  : const Color(0xFFFF4FB3).withOpacity(0.08),
              blurRadius: 28,
              spreadRadius: 2,
              offset: const Offset(0, -4),
            ),
            // Layer 3: Violet Sub-surface Glow
            BoxShadow(
              color: isPlaying
                  ? const Color(0xFF8A2BE2).withOpacity(0.20)
                  : Colors.black45,
              blurRadius: 18,
              spreadRadius: 1,
            ),
          ],
        ),
        child: Stack(
          children: [
            // Centerpiece Artwork
            MusicArtworkWidget(
              imageUrl: imageUrl,
              width: size,
              height: size,
              borderRadius: borderRadius,
              placeholderIconSize: size * 0.28,
              showLoadingIndicator: true,
              border: Border.all(
                color: isPlaying
                    ? const Color(0xFF2DE2FF).withOpacity(0.55)
                    : FMColors.border,
                width: 1.5,
              ),
            ),
            if (overlayChild != null) overlayChild!,
          ],
        ),
      ),
    );
  }
}
