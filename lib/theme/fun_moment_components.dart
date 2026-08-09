import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'fun_moment_theme.dart';

class FMBrandLogo extends StatelessWidget {
  const FMBrandLogo({
    super.key,
    this.height = 72,
    this.fit = BoxFit.contain,
    this.glow = true,
  });

  final double height;
  final BoxFit fit;
  final bool glow;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: FMColors.overlay,
        borderRadius: BorderRadius.circular(FMRadii.xl),
        boxShadow: glow ? FMShadows.subtleGlow : const [],
        border: Border.all(color: FMColors.border),
      ),
      child: Image.asset(
        FMAssets.logo,
        height: height,
        fit: fit,
        filterQuality: FilterQuality.high,
      ),
    );
  }
}

class FMAppSectionHeader extends StatelessWidget {
  const FMAppSectionHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context).textTheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: theme.headlineSmall),
              if (subtitle != null) ...[
                const SizedBox(height: 4),
                Text(subtitle!, style: theme.bodySmall),
              ],
            ],
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

class FMPrimaryButton extends StatelessWidget {
  const FMPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.isLoading = false,
    this.icon,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;
  final Widget? icon;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        child: isLoading
            ? const SizedBox(
                height: 18,
                width: 18,
                child: CircularProgressIndicator(
                  strokeWidth: 2.3,
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                ),
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[
                    icon!,
                    const SizedBox(width: 10),
                  ],
                  Text(label),
                ],
              ),
      ),
    );
  }
}

class FMSecondaryButton extends StatelessWidget {
  const FMSecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton(
        onPressed: onPressed,
        child: Text(label),
      ),
    );
  }
}

class FMTextField extends StatelessWidget {
  const FMTextField({
    super.key,
    required this.controller,
    required this.label,
    this.hintText,
    this.obscureText = false,
    this.keyboardType,
    this.textInputAction,
    this.prefixIcon,
    this.suffixIcon,
    this.validator,
    this.onSubmitted,
    this.onChanged,
    this.autofillHints,
  });

  final TextEditingController controller;
  final String label;
  final String? hintText;
  final bool obscureText;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Widget? prefixIcon;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final List<String>? autofillHints;

  @override
  Widget build(BuildContext context) {
    final labelStyle = Theme.of(context).textTheme.labelLarge;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: labelStyle),
        const SizedBox(height: 8),
        TextFormField(
          controller: controller,
          obscureText: obscureText,
          keyboardType: keyboardType,
          textInputAction: textInputAction,
          autofillHints: autofillHints,
          validator: validator,
          onChanged: onChanged,
          onFieldSubmitted: onSubmitted,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: FMColors.textPrimary,
                fontWeight: FontWeight.w500,
              ),
          decoration: InputDecoration(
            hintText: hintText,
            prefixIcon: prefixIcon,
            suffixIcon: suffixIcon,
          ),
        ),
      ],
    );
  }
}

class FMSurfaceCard extends StatelessWidget {
  const FMSurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(FMSpacing.card),
    this.gradient,
    this.borderRadius,
    this.margin,
    this.borderColor = FMColors.border,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Gradient? gradient;
  final BorderRadiusGeometry? borderRadius;
  final EdgeInsetsGeometry? margin;
  final Color borderColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: gradient == null ? FMColors.card : null,
        gradient: gradient,
        borderRadius: borderRadius ?? BorderRadius.circular(FMRadii.lg),
        border: Border.all(color: borderColor),
        boxShadow: const [
          BoxShadow(
            color: Color(0x22000000),
            blurRadius: 18,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: child,
    );
  }
}

class FMScreenState extends StatelessWidget {
  const FMScreenState.empty({
    super.key,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  }) : isError = false;

  const FMScreenState.error({
    super.key,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  }) : isError = true;

  final bool isError;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context).textTheme;
    final accent = isError ? FMColors.error : FMColors.cyan;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(FMSpacing.xxl),
        child: FMSurfaceCard(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: FMGradients.funGradient,
                  boxShadow: [BoxShadow(color: accent.withOpacity(.26), blurRadius: 22)],
                ),
                child: Icon(
                  isError ? Icons.error_outline_rounded : Icons.inbox_outlined,
                  color: Colors.white,
                  size: 30,
                ),
              ),
              const SizedBox(height: FMSpacing.lg),
              Text(title, style: theme.headlineSmall, textAlign: TextAlign.center),
              const SizedBox(height: FMSpacing.sm),
              Text(
                message,
                style: theme.bodyMedium,
                textAlign: TextAlign.center,
              ),
              if (actionLabel != null) ...[
                const SizedBox(height: FMSpacing.lg),
                FMPrimaryButton(
                  label: actionLabel!,
                  onPressed: onAction,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class FMSkeletonBlock extends StatefulWidget {
  const FMSkeletonBlock({
    super.key,
    this.width,
    this.height = 18,
    this.radius = FMRadii.sm,
  });

  final double? width;
  final double height;
  final double radius;

  @override
  State<FMSkeletonBlock> createState() => _FMSkeletonBlockState();
}

class _FMSkeletonBlockState extends State<FMSkeletonBlock>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1600),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (_, child) {
        final opacity = 0.35 + (_controller.value * 0.28);
        return Opacity(opacity: opacity, child: child);
      },
      child: Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: FMColors.surfaceElevated,
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

class FMNetworkImageFrame extends StatelessWidget {
  const FMNetworkImageFrame({
    super.key,
    required this.imageUrl,
    this.borderRadius,
    this.height,
    this.width,
    this.fit = BoxFit.cover,
    this.overlay = false,
    this.placeholder,
  });

  final String imageUrl;
  final BorderRadiusGeometry? borderRadius;
  final double? height;
  final double? width;
  final BoxFit fit;
  final bool overlay;
  final Widget? placeholder;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: borderRadius ?? BorderRadius.circular(FMRadii.lg),
      child: Stack(
        fit: StackFit.expand,
        children: [
          CachedNetworkImage(
            imageUrl: imageUrl,
            height: height,
            width: width,
            fit: fit,
            placeholder: (_, __) =>
                placeholder ?? const ColoredBox(color: FMColors.surfaceElevated),
            errorWidget: (_, __, ___) =>
                placeholder ?? const ColoredBox(color: FMColors.surfaceElevated),
          ),
          if (overlay)
            const DecoratedBox(decoration: BoxDecoration(gradient: FMGradients.darkCinematic)),
        ],
      ),
    );
  }
}

class FMAssetImageFrame extends StatelessWidget {
  const FMAssetImageFrame({
    super.key,
    required this.assetPath,
    this.borderRadius,
    this.height,
    this.width,
    this.fit = BoxFit.cover,
    this.overlay = false,
  });

  final String assetPath;
  final BorderRadiusGeometry? borderRadius;
  final double? height;
  final double? width;
  final BoxFit fit;
  final bool overlay;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: borderRadius ?? BorderRadius.circular(FMRadii.lg),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset(
            assetPath,
            height: height,
            width: width,
            fit: fit,
            filterQuality: FilterQuality.high,
          ),
          if (overlay)
            const DecoratedBox(
              decoration: BoxDecoration(gradient: FMGradients.darkCinematic),
            ),
        ],
      ),
    );
  }
}

class FMEditorialBanner extends StatelessWidget {
  const FMEditorialBanner({
    super.key,
    required this.assetPath,
    required this.title,
    required this.subtitle,
    this.ctaLabel,
    this.onTap,
    this.height = 190,
  });

  final String assetPath;
  final String title;
  final String subtitle;
  final String? ctaLabel;
  final VoidCallback? onTap;
  final double height;

  @override
  Widget build(BuildContext context) {
    return FMSurfaceCard(
      padding: EdgeInsets.zero,
      child: SizedBox(
        height: height,
        child: Stack(
          fit: StackFit.expand,
          children: [
            FMAssetImageFrame(
              assetPath: assetPath,
              overlay: true,
              borderRadius: BorderRadius.circular(FMRadii.lg),
            ),
            Container(
              decoration: const BoxDecoration(gradient: FMGradients.darkCinematic),
            ),
            Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: FMColors.overlay,
                      borderRadius: BorderRadius.circular(FMRadii.pill),
                      border: Border.all(color: FMColors.border),
                    ),
                    child: Text(
                      title,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    subtitle,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: FMColors.magentaLight,
                          fontWeight: FontWeight.w500,
                        ),
                  ),
                  if (ctaLabel != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      ctaLabel!,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: FMColors.cyanLight,
                          ),
                    ),
                  ],
                ],
              ),
            ),
            if (onTap != null)
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: onTap,
                ),
              ),
          ],
        ),
      ),
    );
  }
}
