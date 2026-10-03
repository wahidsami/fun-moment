import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:funmoments/model/provider_registration_model.dart';
import 'package:funmoments/service/auth_services/email_verify_service.dart';
import 'package:funmoments/service/common_service.dart';
import 'package:funmoments/view/auth/signup/components/email_verify_page.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ProviderRegistrationService with ChangeNotifier {
  ProviderRegistrationModel model = ProviderRegistrationModel();

  bool isLoading = false;
  bool isRegistered = false;
  bool isOtpSending = false;
  String? registeredEmail;
  String? registeredToken;
  int? registeredUserId;
  String? registeredState;
  String? registeredCountryId;

  Map<String, String> fieldErrors = {};

  void reset() {
    model = ProviderRegistrationModel();
    isLoading = false;
    isRegistered = false;
    isOtpSending = false;
    registeredEmail = null;
    registeredToken = null;
    registeredUserId = null;
    registeredState = null;
    registeredCountryId = null;
    fieldErrors = {};
    notifyListeners();
  }

  void setLoading(bool val) {
    isLoading = val;
    notifyListeners();
  }

  void setSellerType(int type) {
    if (model.sellerType != type) {
      model.sellerType = type;
      // Reset subtype-specific fields to prevent leakage
      if (type == 1) {
        model.companyName = null;
        model.crNumber = null;
        model.crDocumentPath = null;
        model.contactPersonName = null;
        model.contactPersonEmail = null;
        model.contactPersonPhone = null;
      } else if (type == 2) {
        model.nationalIdNumber = null;
        model.nationalIdDocumentPath = null;
        model.licenseNumber = null;
        model.licenseDocumentPath = null;
        model.isBandOrGroup = false;
        model.bandName = null;
        model.bandMembersCount = null;
      }
      fieldErrors.clear();
      notifyListeners();
    }
  }

  void toggleCategoryId(int id) {
    if (model.categoryIds.contains(id)) {
      model.categoryIds.remove(id);
    } else {
      model.categoryIds.add(id);
    }
    fieldErrors.remove('category_ids');
    notifyListeners();
  }

  void setCategoryIds(Set<int> ids) {
    model.categoryIds = Set.from(ids);
    fieldErrors.remove('category_ids');
    notifyListeners();
  }

  void setNationalIdDocument(String? path) {
    model.nationalIdDocumentPath = path;
    fieldErrors.remove('national_id_document');
    notifyListeners();
  }

  void setLicenseDocument(String? path) {
    model.licenseDocumentPath = path;
    fieldErrors.remove('license_document');
    notifyListeners();
  }

  void setCrDocument(String? path) {
    model.crDocumentPath = path;
    fieldErrors.remove('cr_document');
    notifyListeners();
  }

  void setBandFlag(bool isBand) {
    model.isBandOrGroup = isBand;
    notifyListeners();
  }

  void setTermsAgree(bool agree) {
    model.termsAgree = agree;
    notifyListeners();
  }

  /// Client-side validation before dispatching to backend
  String? validateClientSide() {
    fieldErrors.clear();

    if (model.name.trim().isEmpty) {
      fieldErrors['name'] = 'Full name is required';
      return 'Please enter your full name';
    }
    if (model.email.trim().isEmpty) {
      fieldErrors['email'] = 'Email is required';
      return 'Please enter your email';
    }
    if (model.username.trim().isEmpty) {
      fieldErrors['username'] = 'Username is required';
      return 'Please enter a username';
    }
    if (model.phone.trim().isEmpty) {
      fieldErrors['phone'] = 'Phone number is required';
      return 'Please enter your phone number';
    }
    if (model.password.length < 8) {
      fieldErrors['password'] = 'Password must be at least 8 characters';
      return 'Password must be at least 8 characters';
    }
    if (model.password != model.repeatPassword) {
      fieldErrors['repeat_password'] = 'Passwords do not match';
      return 'Passwords do not match';
    }
    if (model.serviceCity == 0) {
      fieldErrors['service_city'] = 'City is required';
      return 'Please select a city';
    }
    if (model.serviceArea == 0) {
      fieldErrors['service_area'] = 'Area is required';
      return 'Please select an area';
    }
    if (model.categoryIds.isEmpty) {
      fieldErrors['category_ids'] = 'At least one service category is required';
      return 'Please select at least one service category';
    }

    if (model.sellerType == 1) {
      // Individual validations
      if (model.nationalIdNumber == null || model.nationalIdNumber!.trim().isEmpty) {
        fieldErrors['national_id_number'] = 'National ID / Iqama number is required';
        return 'Please enter your National ID or Iqama number';
      }
      if (model.nationalIdDocumentPath == null || model.nationalIdDocumentPath!.isEmpty) {
        fieldErrors['national_id_document'] = 'National ID document is required';
        return 'Please upload your National ID or Iqama document';
      }
      if (!ProviderRegistrationModel.isValidDocumentExtension(model.nationalIdDocumentPath!)) {
        fieldErrors['national_id_document'] = 'Document must be PDF, JPG, JPEG, or PNG';
        return 'National ID must be a PDF, JPG, or PNG file';
      }
      if (model.licenseDocumentPath != null && model.licenseDocumentPath!.isNotEmpty) {
        if (!ProviderRegistrationModel.isValidDocumentExtension(model.licenseDocumentPath!)) {
          fieldErrors['license_document'] = 'License must be PDF, JPG, JPEG, or PNG';
          return 'License must be a PDF, JPG, or PNG file';
        }
      }
      if (model.isBandOrGroup) {
        if (model.bandName == null || model.bandName!.trim().isEmpty) {
          fieldErrors['band_name'] = 'Band / Group name is required';
          return 'Please enter your band or group name';
        }
        if (model.bandMembersCount == null || model.bandMembersCount! < 2) {
          fieldErrors['band_members_count'] = 'Band must have at least 2 members';
          return 'Band must have at least 2 members';
        }
      }
    } else if (model.sellerType == 2) {
      // Company validations
      if (model.companyName == null || model.companyName!.trim().isEmpty) {
        fieldErrors['company_name'] = 'Company name is required';
        return 'Please enter the registered company name';
      }
      if (model.crNumber == null || model.crNumber!.trim().isEmpty) {
        fieldErrors['cr_number'] = 'Commercial Registration (CR) number is required';
        return 'Please enter the CR number';
      }
      if (model.crDocumentPath == null || model.crDocumentPath!.isEmpty) {
        fieldErrors['cr_document'] = 'CR certificate document is required';
        return 'Please upload the CR document';
      }
      if (!ProviderRegistrationModel.isValidDocumentExtension(model.crDocumentPath!)) {
        fieldErrors['cr_document'] = 'CR document must be PDF, JPG, JPEG, or PNG';
        return 'CR document must be a PDF, JPG, or PNG file';
      }
      if (model.contactPersonName == null || model.contactPersonName!.trim().isEmpty) {
        fieldErrors['contact_person_name'] = 'Contact person name is required';
        return 'Please enter the contact person name';
      }
      if (model.contactPersonEmail == null || model.contactPersonEmail!.trim().isEmpty) {
        fieldErrors['contact_person_email'] = 'Contact person email is required';
        return 'Please enter the contact person email';
      }
      if (model.contactPersonPhone == null || model.contactPersonPhone!.trim().isEmpty) {
        fieldErrors['contact_person_phone'] = 'Contact person phone is required';
        return 'Please enter the contact person phone';
      }
    }

    if (!model.termsAgree) {
      return 'You must agree to the terms and conditions';
    }

    return null; // Valid
  }

  /// Submit Provider Registration
  Future<bool> registerProvider(BuildContext context, {http.Client? client}) async {
    if (isRegistered) {
      OthersHelper().showToast(
        'Account already created. Please verify your email.',
        ConstantColors().successColor,
      );
      if (context.mounted) {
        Navigator.pushReplacement<void, void>(
          context,
          MaterialPageRoute<void>(
            builder: (BuildContext context) => EmailVerifyPage(
              email: registeredEmail ?? model.email.trim(),
              token: registeredToken ?? '',
              userId: registeredUserId ?? 0,
              state: registeredState ?? '',
              countryId: registeredCountryId ?? '',
              userType: 0, // Provider
            ),
          ),
        );
      }
      return true;
    }

    if (isLoading) return false;

    final validationError = validateClientSide();
    if (validationError != null) {
      OthersHelper().showToast(validationError, Colors.black);
      notifyListeners();
      return false;
    }

    final hasConnection = await checkConnection();
    if (!hasConnection) {
      OthersHelper().showToast('Please check your internet connection', Colors.black);
      return false;
    }

    setLoading(true);

    try {
      final uri = Uri.parse('$baseApi/provider/register');
      final request = http.MultipartRequest('POST', uri);

      request.headers.addAll({
        'Accept': 'application/json',
      });

      // Add fields
      request.fields.addAll(model.toFieldsMap());

      // Attach documents
      if (model.sellerType == 1) {
        if (model.nationalIdDocumentPath != null && File(model.nationalIdDocumentPath!).existsSync()) {
          request.files.add(await http.MultipartFile.fromPath(
            'national_id_document',
            model.nationalIdDocumentPath!,
          ));
        }
        if (model.licenseDocumentPath != null && File(model.licenseDocumentPath!).existsSync()) {
          request.files.add(await http.MultipartFile.fromPath(
            'license_document',
            model.licenseDocumentPath!,
          ));
        }
      } else if (model.sellerType == 2) {
        if (model.crDocumentPath != null && File(model.crDocumentPath!).existsSync()) {
          request.files.add(await http.MultipartFile.fromPath(
            'cr_document',
            model.crDocumentPath!,
          ));
        }
      }

      final streamedResponse = client != null
          ? await client.send(request)
          : await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      debugPrint('Provider registration HTTP ${response.statusCode}: ${response.body}');

      if (response.statusCode == 201) {
        final responseData = jsonDecode(response.body);
        final token = responseData['token']?.toString() ?? '';
        final userId = int.tryParse(responseData['users']?['id']?.toString() ?? '') ?? 0;
        final state = responseData['users']?['state']?.toString() ?? '';
        final countryId = responseData['users']?['country_id']?.toString() ?? '';
        final sellerType = int.tryParse(responseData['users']?['seller_type']?.toString() ?? '') ?? model.sellerType;

        // Cache sellerType in SharedPreferences for provider session
        try {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setInt('sellerType', sellerType);
        } catch (e) {
          debugPrint('Error caching sellerType: $e');
        }

        // 1. REGISTRATION SUCCEEDED:
        // Immediately terminate registration button loading state
        isLoading = false;
        isRegistered = true;
        registeredEmail = model.email.trim();
        registeredToken = token;
        registeredUserId = userId;
        registeredState = state;
        registeredCountryId = countryId;
        notifyListeners();

        OthersHelper().showToast(
          'Registration successful. Please verify your email.',
          ConstantColors().successColor,
        );

        // 2. SEPARATE OTP DISPATCH:
        isOtpSending = true;
        notifyListeners();

        bool isOtpSent = false;
        try {
          final emailVerifyService = Provider.of<EmailVerifyService>(context, listen: false);
          isOtpSent = await emailVerifyService.sendOtpForEmailValidation(
            model.email.trim(),
            context,
            token,
            client: client,
          );
        } catch (otpErr) {
          debugPrint('Suppressed OTP dispatch exception in ProviderRegistrationService: $otpErr');
          isOtpSent = false;
        } finally {
          isOtpSending = false;
          notifyListeners();
        }

        if (context.mounted) {
          if (isOtpSent) {
            Navigator.pushReplacement<void, void>(
              context,
              MaterialPageRoute<void>(
                builder: (BuildContext context) => EmailVerifyPage(
                  email: model.email.trim(),
                  token: token,
                  userId: userId,
                  state: state,
                  countryId: countryId,
                  userType: 0, // Provider
                ),
              ),
            );
          } else {
            // OTP failed or timed out:
            // Provider registration REMAINS SUCCESSFUL!
            // Give clear, OTP-specific guidance and navigate to EmailVerifyPage where user can tap "Send again"
            OthersHelper().showToast(
              "Your account was created successfully, but we couldn't send the verification code. Please tap 'Send again'.",
              Colors.black,
            );

            Navigator.pushReplacement<void, void>(
              context,
              MaterialPageRoute<void>(
                builder: (BuildContext context) => EmailVerifyPage(
                  email: model.email.trim(),
                  token: token,
                  userId: userId,
                  state: state,
                  countryId: countryId,
                  userType: 0, // Provider
                ),
              ),
            );
          }
        }

        return true;
      } else {
        _parseAndSurfaceErrors(response);
        setLoading(false);
        return false;
      }
    } catch (e) {
      debugPrint('Provider registration exception: $e');
      OthersHelper().showToast('Registration failed: $e', Colors.black);
      setLoading(false);
      return false;
    }
  }

  /// Resend OTP for the already registered provider without re-submitting registration
  Future<bool> resendRegistrationOtp(BuildContext context, {http.Client? client}) async {
    if (!isRegistered || registeredEmail == null || registeredToken == null) {
      return false;
    }
    isOtpSending = true;
    notifyListeners();
    try {
      EmailVerifyService? emailVerifyService;
      if (context.mounted) {
        try {
          emailVerifyService = Provider.of<EmailVerifyService>(context, listen: false);
        } catch (_) {}
      }
      emailVerifyService ??= EmailVerifyService();

      final sent = await emailVerifyService.sendOtpForEmailValidation(
        registeredEmail!,
        context.mounted ? context : null,
        registeredToken!,
        client: client,
      );
      return sent;
    } catch (e) {
      debugPrint('Resend OTP error: $e');
      return false;
    } finally {
      isOtpSending = false;
      notifyListeners();
    }
  }

  void _parseAndSurfaceErrors(http.Response response) {
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map && decoded['errors'] is Map) {
        final errors = decoded['errors'] as Map;
        final List<String> errorMessages = [];

        errors.forEach((key, val) {
          final fieldKey = key.toString();
          String fieldMsg = '';
          if (val is List && val.isNotEmpty) {
            fieldMsg = val.first.toString();
          } else if (val is String) {
            fieldMsg = val;
          }
          if (fieldMsg.isNotEmpty) {
            fieldErrors[fieldKey] = fieldMsg;
            errorMessages.add(fieldMsg);
          }
        });

        if (errorMessages.isNotEmpty) {
          OthersHelper().showToast(errorMessages.first, Colors.black);
        } else {
          OthersHelper().showToast('Validation failed. Please check your data.', Colors.black);
        }
      } else if (decoded is Map && decoded.containsKey('message')) {
        OthersHelper().showToast(decoded['message']?.toString() ?? 'Registration failed', Colors.black);
      } else {
        OthersHelper().showToast('Registration failed (${response.statusCode})', Colors.black);
      }
    } catch (_) {
      OthersHelper().showToast('Registration failed (${response.statusCode})', Colors.black);
    }
    notifyListeners();
  }
}
