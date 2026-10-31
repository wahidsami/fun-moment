import 'dart:math';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

/// Reusable cover artwork widget for music tracks and albums across the FUN MOMENT app.
///
/// Features:
/// - Absolute and resolved HTTPS/HTTP image loading via CachedNetworkImage with bounded memory caching and disk cache.
/// - Graceful loading indicator and placeholder state.
/// - Fallback to music-note icon on error or missing/invalid URL.
/// - Diagnostic logging on image loading failure.
/// - Configurable dimensions, border radius, styling, and decorations.
import 'package:funmoments/model/buyer_music_model.dart';

class MusicArtworkWidget extends StatelessWidget {
  final String? imageUrl;
  final Map<String, String>? imageVariants;
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
    this.imageUrl,
    this.imageVariants,
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

  factory MusicArtworkWidget.fromTrack(
    CatalogTrackModel track, {
    Key? key,
    double? width,
    double? height,
    double borderRadius = FMRadii.md,
    BoxFit fit = BoxFit.cover,
    IconData placeholderIcon = Icons.music_note_rounded,
    double placeholderIconSize = 28,
    Color? placeholderBackgroundColor,
    Color? placeholderIconColor,
    bool showLoadingIndicator = false,
    BoxBorder? border,
    List<BoxShadow>? boxShadow,
  }) {
    return MusicArtworkWidget(
      key: key,
      imageUrl: track.coverImageUrl,
      imageVariants: track.imageVariants,
      width: width,
      height: height,
      borderRadius: borderRadius,
      fit: fit,
      placeholderIcon: placeholderIcon,
      placeholderIconSize: placeholderIconSize,
      placeholderBackgroundColor: placeholderBackgroundColor,
      placeholderIconColor: placeholderIconColor,
      showLoadingIndicator: showLoadingIndicator,
      border: border,
      boxShadow: boxShadow,
    );
  }

  factory MusicArtworkWidget.fromAlbum(
    CatalogAlbumModel album, {
    Key? key,
    double? width,
    double? height,
    double borderRadius = FMRadii.md,
    BoxFit fit = BoxFit.cover,
    IconData placeholderIcon = Icons.music_note_rounded,
    double placeholderIconSize = 28,
    Color? placeholderBackgroundColor,
    Color? placeholderIconColor,
    bool showLoadingIndicator = false,
    BoxBorder? border,
    List<BoxShadow>? boxShadow,
  }) {
    return MusicArtworkWidget(
      key: key,
      imageUrl: album.coverImageUrl,
      imageVariants: album.imageVariants,
      width: width,
      height: height,
      borderRadius: borderRadius,
      fit: fit,
      placeholderIcon: placeholderIcon,
      placeholderIconSize: placeholderIconSize,
      placeholderBackgroundColor: placeholderBackgroundColor,
      placeholderIconColor: placeholderIconColor,
      showLoadingIndicator: showLoadingIndicator,
      border: border,
      boxShadow: boxShadow,
    );
  }

  String? get _resolvedUrl {
    if (imageVariants != null && imageVariants!.isNotEmpty) {
      final double effectiveDim = max(width ?? 0, height ?? 0);
      if (effectiveDim > 0 && effectiveDim <= 160) {
        if (imageVariants!['thumbnail']?.isNotEmpty == true) {
          return imageVariants!['thumbnail'];
        }
      } else if (effectiveDim > 0 && effectiveDim <= 600) {
        if (imageVariants!['medium']?.isNotEmpty == true) {
          return imageVariants!['medium'];
        }
      } else if (effectiveDim > 600) {
        if (imageVariants!['large']?.isNotEmpty == true) {
          return imageVariants!['large'];
        }
      }
      return imageVariants!['medium'] ?? imageVariants!['thumbnail'] ?? imageVariants!['large'] ?? imageUrl;
    }
    return imageUrl;
  }

  bool get _hasValidUrl {
    final url = _resolvedUrl;
    if (url == null) return false;
    final trimmed = url.trim();
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
      final validUrl = _resolvedUrl!.trim();
      final cacheW = (width != null && width! > 0)
          ? (width! * 2).toInt().clamp(60, 800)
          : 600;
      final cacheH = (height != null && height! > 0)
          ? (height! * 2).toInt().clamp(60, 800)
          : 600;

      content = ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius),
        child: Container(
          width: width,
          height: height,
          color: placeholderBackgroundColor ?? FMColors.surfaceElevated,
          child: CachedNetworkImage(
            imageUrl: validUrl,
            width: width,
            height: height,
            fit: fit,
            memCacheWidth: cacheW,
            memCacheHeight: cacheH,
            maxWidthDiskCache: 1200,
            maxHeightDiskCache: 1200,
            placeholder: (context, url) {
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
            errorWidget: (context, url, error) {
              debugPrint('[MusicArtworkWidget] Failed to load "$url": $error');
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
