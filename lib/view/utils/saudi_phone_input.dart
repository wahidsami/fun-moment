import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:provider/provider.dart';

/// Normalizes any raw phone input (from DB, profile, or user input) into the clean 9-digit Saudi local format: 5XXXXXXXX.
/// Strips non-digits, `+966`, `00966`, `966`, leading `0`, and clamps to 9 digits.
String normalizeToLocalSaudiPhone(String? input) {
  if (input == null || input.isEmpty) return '';
  String digits = input.replaceAll(RegExp(r'[^0-9]'), '');
  if (digits.isEmpty) return '';

  if (digits.startsWith('00966')) {
    digits = digits.substring(5);
  } else if (digits.startsWith('966')) {
    digits = digits.substring(3);
  }

  if (digits.startsWith('0')) {
    digits = digits.substring(1);
  }

  if (digits.length > 9) {
    digits = digits.substring(0, 9);
  }

  return digits;
}

/// Normalizes a 9-digit Saudi local number (5XXXXXXXX) into the standard backend international format: +9665XXXXXXXX.
String normalizeToBackendSaudiPhone(String? input) {
  final local = normalizeToLocalSaudiPhone(input);
  if (local.isEmpty) return '';
  return '+966$local';
}

/// Validates a Saudi mobile number:
/// - Must not be empty -> 'Phone field is required'
/// - Must be exactly 9 digits -> 'Please enter a valid phone number'
/// - Must start with '5' -> 'Please enter a valid phone number'
String? validateSaudiPhone(String? value, {AppStringService? asProvider, BuildContext? context}) {
  final as = asProvider ?? (context != null ? Provider.of<AppStringService>(context, listen: false) : null);
  final requiredMsg = as?.getString('Phone field is required') ?? 'Phone field is required';
  final invalidMsg = as?.getString('Please enter a valid phone number') ?? 'Please enter a valid phone number';

  if (value == null || value.trim().isEmpty) {
    return requiredMsg;
  }

  final digits = value.trim().replaceAll(RegExp(r'[^0-9]'), '');
  if (digits.length != 9 || !digits.startsWith('5')) {
    return invalidMsg;
  }

  return null;
}

/// TextInputFormatter enforcing:
/// - Digits only
/// - Max 9 digits
/// - Auto-stripping pasted `+966`, `00966`, `966`, or leading `0` when pasting numbers
class SaudiPhoneInputFormatter extends TextInputFormatter {
  const SaudiPhoneInputFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    if (newValue.text.isEmpty) {
      return newValue;
    }

    String text = newValue.text;
    String digits = text.replaceAll(RegExp(r'[^0-9]'), '');

    // Handle pasted prefixes
    if (digits.startsWith('00966')) {
      digits = digits.substring(5);
    } else if (digits.startsWith('966') && digits.length > 9) {
      digits = digits.substring(3);
    } else if (digits.startsWith('0') && digits.length > 9 && digits.startsWith('05')) {
      digits = digits.substring(1);
    }

    // Clamp to 9 digits max
    if (digits.length > 9) {
      digits = digits.substring(0, 9);
    }

    int newOffset = digits.length;
    if (newValue.selection.end <= digits.length && newValue.text == digits) {
      newOffset = newValue.selection.end;
    }

    return TextEditingValue(
      text: digits,
      selection: TextSelection.collapsed(offset: newOffset),
    );
  }
}

/// Reusable clean Saudi-specific mobile phone input field.
/// - Fixed Saudi country code (+966) visually isolated with flag 🇸🇦.
/// - Arabic RTL friendly with isolated LTR text direction so numbers and +966 maintain perfect visual order.
/// - FormField integration with standard Flutter Form validation.
class SaudiPhoneInput extends StatefulWidget {
  final TextEditingController? controller;
  final FocusNode? focusNode;
  final FormFieldValidator<String>? validator;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onFieldSubmitted;
  final TextInputAction? textInputAction;
  final AppStringService? asProvider;
  final bool enabled;

  const SaudiPhoneInput({
    Key? key,
    this.controller,
    this.focusNode,
    this.validator,
    this.onChanged,
    this.onFieldSubmitted,
    this.textInputAction,
    this.asProvider,
    this.enabled = true,
  }) : super(key: key);

  @override
  State<SaudiPhoneInput> createState() => _SaudiPhoneInputState();
}

class _SaudiPhoneInputState extends State<SaudiPhoneInput> {
  late FocusNode _focusNode;
  bool _isFocused = false;

  @override
  void initState() {
    super.initState();
    _focusNode = widget.focusNode ?? FocusNode();
    _focusNode.addListener(_handleFocusChange);
  }

  @override
  void dispose() {
    if (widget.focusNode == null) {
      _focusNode.dispose();
    } else {
      _focusNode.removeListener(_handleFocusChange);
    }
    super.dispose();
  }

  void _handleFocusChange() {
    if (mounted) {
      setState(() {
        _isFocused = _focusNode.hasFocus;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    AppStringService? asProvider = widget.asProvider;
    if (asProvider == null) {
      try {
        asProvider = Provider.of<AppStringService>(context, listen: false);
      } catch (_) {
        asProvider = null;
      }
    }

    return FormField<String>(
      initialValue: widget.controller?.text ?? '',
      validator: (val) {
        final currentText = widget.controller?.text ?? val;
        if (widget.validator != null) {
          return widget.validator!(currentText);
        }
        return validateSaudiPhone(currentText, asProvider: asProvider);
      },
      builder: (FormFieldState<String> field) {
        final hasError = field.hasError;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            GestureDetector(
              onTap: () {
                if (widget.enabled) {
                  _focusNode.requestFocus();
                }
              },
              child: Container(
                height: 54,
                decoration: BoxDecoration(
                  color: FMColors.inputSurface,
                  borderRadius: BorderRadius.circular(FMRadii.md),
                  border: Border.all(
                    color: hasError
                        ? FMColors.error
                        : (_isFocused ? FMColors.magenta : FMColors.border),
                    width: (_isFocused || hasError) ? 1.5 : 1.0,
                  ),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(FMRadii.md - 1),
                  child: Directionality(
                    textDirection: TextDirection.ltr,
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Fixed Saudi Country Code Badge
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          color: FMColors.surfaceElevated,
                          alignment: Alignment.center,
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                '🇸🇦',
                                style: TextStyle(fontSize: 18),
                              ),
                              SizedBox(width: 8),
                              Text(
                                '+966',
                                style: TextStyle(
                                  fontFamily: 'Cairo',
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: FMColors.textPrimary,
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                        // Separator Divider
                        Container(
                          width: 1,
                          color: FMColors.border,
                        ),
                        // Local 9-digit editable input
                        Expanded(
                          child: Center(
                            child: TextField(
                              controller: widget.controller,
                              focusNode: _focusNode,
                              enabled: widget.enabled,
                              keyboardType: TextInputType.number,
                              textInputAction: widget.textInputAction ?? TextInputAction.next,
                              inputFormatters: [
                                FilteringTextInputFormatter.digitsOnly,
                                const SaudiPhoneInputFormatter(),
                              ],
                              style: const TextStyle(
                                fontFamily: 'Cairo',
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                                color: FMColors.textPrimary,
                                letterSpacing: 1.0,
                              ),
                              textAlign: TextAlign.left,
                              textDirection: TextDirection.ltr,
                              decoration: const InputDecoration(
                                hintText: '5XXXXXXXX',
                                hintStyle: TextStyle(
                                  fontFamily: 'Cairo',
                                  fontSize: 15,
                                  color: FMColors.textMuted,
                                  letterSpacing: 1.0,
                                ),
                                border: InputBorder.none,
                                isDense: true,
                                contentPadding: EdgeInsets.symmetric(
                                  horizontal: 14,
                                  vertical: 14,
                                ),
                              ),
                              onChanged: (val) {
                                field.didChange(val);
                                if (widget.onChanged != null) {
                                  widget.onChanged!(val);
                                }
                              },
                              onSubmitted: (val) {
                                if (widget.onFieldSubmitted != null) {
                                  widget.onFieldSubmitted!(val);
                                }
                              },
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            if (hasError)
              Padding(
                padding: const EdgeInsets.only(top: 6, left: 4, right: 4),
                child: Text(
                  field.errorText ?? '',
                  style: const TextStyle(
                    fontFamily: 'Cairo',
                    fontSize: 12,
                    color: FMColors.error,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
