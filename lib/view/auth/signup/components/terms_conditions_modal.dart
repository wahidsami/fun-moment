import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/terms_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';

/// Shows the Terms & Conditions bottom-sheet modal.
///
/// Returns the accepted [PublishedTerms] if the user explicitly taps "I Agree",
/// or `null` if the user closes, cancels, or dismisses the modal.
Future<PublishedTerms?> showTermsConditionsModal(
  BuildContext context, {
  required String role,
  TermsService? termsService,
  String? langOverride,
}) async {
  if (TermsConditionsModal.isModalOpen) {
    debugPrint('[TermsModal] Modal already in flight, ignoring duplicate open.');
    return null;
  }

  TermsConditionsModal.isModalOpen = true;
  try {
    return await showModalBottomSheet<PublishedTerms>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      isDismissible: true,
      enableDrag: true,
      builder: (ctx) => TermsConditionsModal(
        role: role,
        termsService: termsService,
        langOverride: langOverride,
      ),
    );
  } finally {
    TermsConditionsModal.isModalOpen = false;
  }
}

class TermsConditionsModal extends StatefulWidget {
  static bool isModalOpen = false;

  final String role; // 'buyer' or 'seller'
  final TermsService? termsService;
  final String? langOverride;

  const TermsConditionsModal({
    Key? key,
    required this.role,
    this.termsService,
    this.langOverride,
  }) : super(key: key);

  @override
  State<TermsConditionsModal> createState() => _TermsConditionsModalState();
}

class _TermsConditionsModalState extends State<TermsConditionsModal> {
  late final TermsService _termsService;
  PublishedTerms? _terms;
  bool _isLoading = true;
  String? _errorMessage;
  bool _isUnavailable = false;

  @override
  void initState() {
    super.initState();
    _termsService = widget.termsService ?? TermsService();
    _loadTerms();
  }

  String _resolveLanguage(BuildContext context) {
    if (widget.langOverride != null && widget.langOverride!.isNotEmpty) {
      return widget.langOverride!.startsWith('ar') ? 'ar' : 'en';
    }
    final rtl = Provider.of<RtlService>(context, listen: false);
    return rtl.isArabic ? 'ar' : 'en';
  }

  Future<void> _loadTerms() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _isUnavailable = false;
    });

    final lang = _resolveLanguage(context);

    try {
      final terms = await _termsService.fetchCurrentTerms(
        role: widget.role,
        lang: lang,
      );
      if (mounted) {
        setState(() {
          _terms = terms;
          _isLoading = false;
        });
      }
    } on TermsUnavailableException catch (e) {
      if (mounted) {
        setState(() {
          _isUnavailable = true;
          _errorMessage = e.message;
          _isLoading = false;
        });
      }
    } on TermsNetworkException catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.message;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final asProvider = Provider.of<AppStringService>(context);
    final rtl = Provider.of<RtlService>(context);
    final lang = widget.langOverride ?? (rtl.isArabic ? 'ar' : 'en');
    final isArabic = lang.startsWith('ar');
    final isSeller = widget.role.toLowerCase() == 'seller';
    final brandAccent = isSeller ? FMColors.cyan : FMColors.magenta;

    final defaultTitle = isSeller
        ? asProvider.getString("Seller Terms & Conditions")
        : asProvider.getString("Buyer Terms & Conditions");

    final title = _terms?.title.isNotEmpty == true ? _terms!.title : defaultTitle;

    return Directionality(
      textDirection: isArabic ? TextDirection.rtl : TextDirection.ltr,
      child: SafeArea(
        child: Container(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.88,
            minHeight: MediaQuery.of(context).size.height * 0.50,
          ),
          decoration: BoxDecoration(
            color: FMColors.surfaceDark,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
            border: Border.all(color: FMColors.border.withOpacity(0.8)),
            boxShadow: const [
              BoxShadow(
                color: Colors.black87,
                blurRadius: 24,
                offset: Offset(0, -6),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // 1. Drag Handle
              const SizedBox(height: 12),
              Center(
                child: Container(
                  width: 44,
                  height: 5,
                  decoration: BoxDecoration(
                    color: FMColors.textMuted.withOpacity(0.4),
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // 2. Header Bar
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: brandAccent.withOpacity(0.14),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        Icons.gavel_rounded,
                        color: brandAccent,
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            title,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 17,
                              fontWeight: FontWeight.bold,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          if (_terms != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              'v${_terms!.versionNumber} • ${isArabic ? "العربية" : "English"}',
                              style: const TextStyle(
                                color: FMColors.textMuted,
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded, color: FMColors.textMuted),
                      tooltip: asProvider.getString('Close'),
                      onPressed: () => Navigator.of(context).pop(null),
                    ),
                  ],
                ),
              ),
              const Divider(color: FMColors.border, height: 16),

              // 3. Scrollable Body
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: _buildBody(context, asProvider, brandAccent, isArabic),
                ),
              ),

              // 4. Sticky Bottom Action Bar
              const Divider(color: FMColors.border, height: 1),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 16),
                child: Row(
                  children: [
                    // Cancel / Close Button
                    Expanded(
                      flex: 1,
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size(double.infinity, 48),
                          side: const BorderSide(color: FMColors.border),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        onPressed: () => Navigator.of(context).pop(null),
                        child: Text(
                          asProvider.getString("Close"),
                          style: const TextStyle(
                            color: FMColors.textSecondary,
                            fontWeight: FontWeight.w600,
                            fontSize: 15,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),

                    // "I Agree" Explicit Action Button
                    Expanded(
                      flex: 2,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: brandAccent,
                          foregroundColor: Colors.white,
                          disabledBackgroundColor: FMColors.border,
                          disabledForegroundColor: FMColors.textMuted,
                          minimumSize: const Size(double.infinity, 48),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                          elevation: _terms != null ? 3 : 0,
                        ),
                        onPressed: (_terms != null && !_isLoading)
                            ? () => Navigator.of(context).pop(_terms)
                            : null,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.check_circle_outline_rounded, size: 18),
                            const SizedBox(width: 8),
                            Text(
                              asProvider.getString("I Agree"),
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody(
    BuildContext context,
    AppStringService asProvider,
    Color brandAccent,
    bool isArabic,
  ) {
    if (_isLoading) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: brandAccent),
            const SizedBox(height: 16),
            Text(
              asProvider.getString("Loading Terms & Conditions..."),
              style: const TextStyle(color: FMColors.textMuted, fontSize: 14),
            ),
          ],
        ),
      );
    }

    if (_isUnavailable) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.warning.withOpacity(0.12),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.info_outline_rounded,
                  color: FMColors.warning,
                  size: 40,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                asProvider.getString("Terms & Conditions are not currently available."),
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                asProvider.getString(
                  "Registration is temporarily unavailable because the terms have not been published yet.",
                ),
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: FMColors.textMuted,
                  fontSize: 13,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (_errorMessage != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: FMColors.error.withOpacity(0.12),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.error_outline_rounded,
                  color: FMColors.error,
                  size: 40,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                asProvider.getString("Failed to load Terms & Conditions. Please try again."),
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                _errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: FMColors.textMuted,
                  fontSize: 12,
                ),
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: brandAccent,
                  foregroundColor: Colors.white,
                ),
                onPressed: _loadTerms,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: Text(asProvider.getString("Retry")),
              ),
            ],
          ),
        ),
      );
    }

    if (_terms == null || _terms!.content.isEmpty) {
      return Center(
        child: Text(
          asProvider.getString("Terms & Conditions are not currently available."),
          style: const TextStyle(color: FMColors.textMuted),
        ),
      );
    }

    return SingleChildScrollView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: HtmlWidget(
        _terms!.content,
        textStyle: TextStyle(
          color: FMColors.textSecondary,
          fontSize: 14.5,
          height: 1.6,
          fontFamily: isArabic ? 'Cairo' : null,
        ),
        onErrorBuilder: (context, element, error) => Text(
          'Error loading formatted content: $error',
          style: const TextStyle(color: FMColors.error, fontSize: 12),
        ),
        onLoadingBuilder: (context, element, loadingProgress) => Center(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: CircularProgressIndicator(color: brandAccent),
          ),
        ),
      ),
    );
  }
}
