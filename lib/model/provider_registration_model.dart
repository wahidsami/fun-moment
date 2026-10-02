class ProviderRegistrationModel {
  // Common Account & Contact
  String name;
  String email;
  String username;
  String phone;
  String password;
  String repeatPassword;

  // Address
  int countryId;
  int serviceCity;
  int serviceArea;
  String? address;
  String? postCode;
  String? countryCode;

  // Provider Subtype: 1 = Individual, 2 = Company
  int sellerType;

  // Registered Service Categories
  Set<int> categoryIds;

  // Individual Provider Fields (seller_type = 1)
  String? nationalIdNumber;
  String? nationalIdDocumentPath;
  String? licenseNumber;
  String? licenseDocumentPath;
  bool isBandOrGroup;
  String? bandName;
  int? bandMembersCount;

  // Company Provider Fields (seller_type = 2)
  String? companyName;
  String? crNumber;
  String? crDocumentPath;
  String? contactPersonName;
  String? contactPersonEmail;
  String? contactPersonPhone;

  // Terms & Conditions
  bool termsAgree;

  ProviderRegistrationModel({
    this.name = '',
    this.email = '',
    this.username = '',
    this.phone = '',
    this.password = '',
    this.repeatPassword = '',
    this.countryId = 166, // Saudi Arabia default
    this.serviceCity = 0,
    this.serviceArea = 0,
    this.address,
    this.postCode,
    this.countryCode = 'SA',
    this.sellerType = 1,
    Set<int>? categoryIds,
    this.nationalIdNumber,
    this.nationalIdDocumentPath,
    this.licenseNumber,
    this.licenseDocumentPath,
    this.isBandOrGroup = false,
    this.bandName,
    this.bandMembersCount,
    this.companyName,
    this.crNumber,
    this.crDocumentPath,
    this.contactPersonName,
    this.contactPersonEmail,
    this.contactPersonPhone,
    this.termsAgree = false,
  }) : categoryIds = categoryIds ?? <int>{};

  /// Validate document file extension against allowed types
  static bool isValidDocumentExtension(String filePath) {
    if (filePath.isEmpty) return false;
    final ext = filePath.split('.').last.toLowerCase();
    return const ['pdf', 'jpg', 'jpeg', 'png'].contains(ext);
  }

  /// Serialize non-file fields to Map<String, String> for multipart/form-data
  Map<String, String> toFieldsMap() {
    final fields = <String, String>{
      'name': name.trim(),
      'email': email.trim(),
      'username': username.trim(),
      'phone': phone.trim(),
      'password': password,
      'country_id': countryId.toString(),
      'service_city': serviceCity.toString(),
      'service_area': serviceArea.toString(),
      'terms_conditions': '1',
      'user_type': '0', // Provider / Seller
      'seller_type': sellerType.toString(),
      'category_ids': categoryIds.join(','),
    };

    if (address != null && address!.trim().isNotEmpty) {
      fields['address'] = address!.trim();
    }
    if (postCode != null && postCode!.trim().isNotEmpty) {
      fields['post_code'] = postCode!.trim();
    }
    if (countryCode != null && countryCode!.trim().isNotEmpty) {
      fields['country_code'] = countryCode!.trim();
    }

    if (sellerType == 1) {
      // Individual fields
      if (nationalIdNumber != null && nationalIdNumber!.trim().isNotEmpty) {
        fields['national_id_number'] = nationalIdNumber!.trim();
      }
      if (licenseNumber != null && licenseNumber!.trim().isNotEmpty) {
        fields['license_number'] = licenseNumber!.trim();
      }
      fields['is_band_or_group'] = isBandOrGroup ? '1' : '0';
      if (isBandOrGroup) {
        if (bandName != null && bandName!.trim().isNotEmpty) {
          fields['band_name'] = bandName!.trim();
        }
        if (bandMembersCount != null && bandMembersCount! > 0) {
          fields['band_members_count'] = bandMembersCount.toString();
        }
      }
    } else if (sellerType == 2) {
      // Company fields
      if (companyName != null && companyName!.trim().isNotEmpty) {
        fields['company_name'] = companyName!.trim();
      }
      if (crNumber != null && crNumber!.trim().isNotEmpty) {
        fields['cr_number'] = crNumber!.trim();
      }
      if (contactPersonName != null && contactPersonName!.trim().isNotEmpty) {
        fields['contact_person_name'] = contactPersonName!.trim();
      }
      if (contactPersonEmail != null && contactPersonEmail!.trim().isNotEmpty) {
        fields['contact_person_email'] = contactPersonEmail!.trim();
      }
      if (contactPersonPhone != null && contactPersonPhone!.trim().isNotEmpty) {
        fields['contact_person_phone'] = contactPersonPhone!.trim();
      }
    }

    return fields;
  }
}
