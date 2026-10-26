import 'dart:math';
import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

/// Reusable cover artwork widget for music tracks and albums across the FUN MOMENT app.
///
/// Features:
/// - Absolute and resolved HTTPS/HTTP image loading via Image.network.
/// - Graceful loading indicator and placeholder state.
/// - Fallback to music-note icon on error or missing/invalid URL.
/// - Configurable dimensions, border radius, styling, and decorations.
class MusicArtworkWidget extends StatelessWidget {
  final String? imageUrl;
  final double? width;
  final double? height;
  final double borderRadius;
  final BoxFit fit;
  final IconData placeholderIcon;
  final double placeholderIconSize;
  final Color? placeholderBackgroundColor;
  final Color? placeholderIconColor;
  final bool showLoadingIndicator;
  final BoxBorder? border;
  final List<BoxShadow>? boxShadow;

  const MusicArtworkWidget({
    Key? key,
    required this.imageUrl,
    this.width,
    this.height,
    this.borderRadius = FMRadii.md,
    this.fit = BoxFit.cover,
    this.placeholderIcon = Icons.music_note_rounded,
    this.placeholderIconSize = 28,
    this.placeholderBackgroundColor,
    this.placeholderIconColor,
    this.showLoadingIndicator = false,
    this.border,
    this.boxShadow,
  }) : super(key: key);

  bool get _hasValidUrl {
    if (imageUrl == null) return false;
    final trimmed = imageUrl!.trim();
    if (trimmed.isEmpty) return false;
    final uri = Uri.tryParse(trimmed);
    if (uri == null || !uri.hasScheme || (uri.scheme != 'http' && uri.scheme != 'https')) {
      return false;
    }
    return true;
  }

  Widget _buildPlaceholder() {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: placeholderBackgroundColor ?? FMColors.surfaceElevated,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
      alignment: Alignment.center,
      child: Icon(
        placeholderIcon,
        color: placeholderIconColor ?? FMColors.cyan,
        size: placeholderIconSize,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    Widget content;

    if (!_hasValidUrl) {
      content = _buildPlaceholder();
    } else {
      content = ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius),
        child: Container(
          width: width,
          height: height,
          color: placeholderBackgroundColor ?? FMColors.surfaceElevated,
          child: Image.network(
            imageUrl!.trim(),
            width: width,
            height: height,
            fit: fit,
            loadingBuilder: (context, child, loadingProgress) {
              if (loadingProgress == null) {
                return child;
              }
              if (showLoadingIndicator) {
                final indicatorSize = width != null && height != null
                    ? min(min(width!, height!) * 0.35, 32.0)
                    : 24.0;
                return Center(
                  child: SizedBox(
                    width: indicatorSize,
                    height: indicatorSize,
                    child: const CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(FMColors.cyan),
                    ),
                  ),
                );
              }
              return _buildPlaceholder();
            },
            errorBuilder: (context, error, stackTrace) {
              return _buildPlaceholder();
            },
          ),
        ),
      );
    }

    if (border != null || boxShadow != null) {
      return Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(borderRadius),
          border: border,
          boxShadow: boxShadow,
        ),
        child: content,
      );
    }

    return content;
  }
}
