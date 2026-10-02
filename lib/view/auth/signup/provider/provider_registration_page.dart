import 'package:flutter/material.dart';
import 'package:intl_phone_field/intl_phone_field.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/provider_registration_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/signup/components/country_states_dropdowns.dart';
import 'package:funmoments/view/auth/signup/provider/components/category_multi_select.dart';
import 'package:funmoments/view/auth/signup/provider/components/document_upload_tile.dart';
import 'package:funmoments/view/auth/signup/provider/components/provider_subtype_selector.dart';
import 'package:funmoments/view/auth/signup/signup_helper.dart';
import 'package:funmoments/view/utils/others_helper.dart';

class ProviderRegistrationPage extends StatefulWidget {
  final String? fullName;
  final String? userName;
  final String? email;

  const ProviderRegistrationPage({
    Key? key,
    this.fullName,
    this.userName,
    this.email,
  }) : super(key: key);

  @override
  State<ProviderRegistrationPage> createState() => _ProviderRegistrationPageState();
}

class _ProviderRegistrationPageState extends State<ProviderRegistrationPage> {
  final _formKey = GlobalKey<FormState>();

  late TextEditingController fullNameController;
  late TextEditingController userNameController;
  late TextEditingController emailController;
  final TextEditingController phoneController = TextEditingController();
  final TextEditingController passController = TextEditingController();
  final TextEditingController repeatPassController = TextEditingController();
  final TextEditingController addressController = TextEditingController();
  final TextEditingController postCodeController = TextEditingController();

  // Individual controllers
  final TextEditingController nationalIdController = TextEditingController();
  final TextEditingController licenseController = TextEditingController();
  final TextEditingController bandNameController = TextEditingController();
  final TextEditingController bandMembersController = TextEditingController();

  // Company controllers
  final TextEditingController companyNameController = TextEditingController();
  final TextEditingController crNumberController = TextEditingController();
  final TextEditingController contactNameController = TextEditingController();
  final TextEditingController contactEmailController = TextEditingController();
  final TextEditingController contactPhoneController = TextEditingController();

  bool _passVisible = false;
  bool _repeatPassVisible = false;
  String _countryISOCode = 'SA';

  @override
  void initState() {
    super.initState();
    fullNameController = TextEditingController(text: widget.fullName ?? '');
    userNameController = TextEditingController(text: widget.userName ?? '');
    emailController = TextEditingController(text: widget.email ?? '');

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final prs = Provider.of<ProviderRegistrationService>(context, listen: false);
      prs.model.name = fullNameController.text;
      prs.model.username = userNameController.text;
      prs.model.email = emailController.text;
    });
  }

  @override
  void dispose() {
    fullNameController.dispose();
    userNameController.dispose();
    emailController.dispose();
    phoneController.dispose();
    passController.dispose();
    repeatPassController.dispose();
    addressController.dispose();
    postCodeController.dispose();
    nationalIdController.dispose();
    licenseController.dispose();
    bandNameController.dispose();
    bandMembersController.dispose();
    companyNameController.dispose();
    crNumberController.dispose();
    contactNameController.dispose();
    contactEmailController.dispose();
    contactPhoneController.dispose();
    super.dispose();
  }

  void _syncControllersToModel(ProviderRegistrationService prs) {
    prs.model.name = fullNameController.text.trim();
    prs.model.username = userNameController.text.trim();
    prs.model.email = emailController.text.trim();
    prs.model.phone = phoneController.text.trim();
    prs.model.password = passController.text;
    prs.model.repeatPassword = repeatPassController.text;
    prs.model.address = addressController.text.trim();
    prs.model.postCode = postCodeController.text.trim();
    prs.model.countryCode = _countryISOCode;

    final stateId = Provider.of<StateDropdownService>(context, listen: false).selectedStateId;
    final areaId = Provider.of<AreaDropdownService>(context, listen: false).selectedAreaId;
    final countryId = Provider.of<CountryDropdownService>(context, listen: false).selectedCountryId;

    prs.model.serviceCity = int.tryParse(stateId?.toString() ?? '') ?? 0;
    prs.model.serviceArea = int.tryParse(areaId?.toString() ?? '') ?? 0;
    prs.model.countryId = int.tryParse(countryId?.toString() ?? '') ?? saudiCountryId;

    if (prs.model.sellerType == 1) {
      prs.model.nationalIdNumber = nationalIdController.text.trim();
      prs.model.licenseNumber = licenseController.text.trim();
      if (prs.model.isBandOrGroup) {
        prs.model.bandName = bandNameController.text.trim();
        prs.model.bandMembersCount = int.tryParse(bandMembersController.text.trim());
      }
    } else if (prs.model.sellerType == 2) {
      prs.model.companyName = companyNameController.text.trim();
      prs.model.crNumber = crNumberController.text.trim();
      prs.model.contactPersonName = contactNameController.text.trim();
      prs.model.contactPersonEmail = contactEmailController.text.trim();
      prs.model.contactPersonPhone = contactPhoneController.text.trim();
    }
  }

  @override
  Widget build(BuildContext context) {
    final asProvider = Provider.of<AppStringService>(context);
    final rtl = Provider.of<RtlService>(context);

    return Consumer<ProviderRegistrationService>(
      builder: (context, prs, child) {
        final isIndividual = prs.model.sellerType == 1;

        return Scaffold(
          backgroundColor: FMColors.background,
          appBar: AppBar(
            backgroundColor: Colors.transparent,
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_ios_new_rounded),
              onPressed: () => Navigator.pop(context),
            ),
            title: Text(
              asProvider.getString("Provider Registration"),
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
            ),
            centerTitle: true,
          ),
          body: SafeArea(
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Banner Card
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [FMColors.surfaceDark, FMColors.cyan.withOpacity(0.18)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: FMColors.cyan.withOpacity(0.4)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.verified_user_rounded, color: FMColors.cyan, size: 22),
                              const SizedBox(width: 8),
                              Text(
                                asProvider.getString("Join as Service Provider"),
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            asProvider.getString(
                              "Offer your talents and services on FUN MOMENT. Account verification is reviewed by administration.",
                            ),
                            style: const TextStyle(color: FMColors.textMuted, fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Section 1: Provider Subtype
                    const ProviderSubtypeSelector(),
                    const SizedBox(height: 22),

                    // Section 2: Account Information
                    Text(
                      asProvider.getString("Account Information"),
                      style: const TextStyle(color: FMColors.textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    const SizedBox(height: 12),
                    FMTextField(
                      controller: fullNameController,
                      label: asProvider.getString("Full Name"),
                      hintText: asProvider.getString("Enter full name"),
                      prefixIcon: const Icon(Icons.person_outline_rounded),
                      validator: (v) => v == null || v.isEmpty ? asProvider.getString("Please enter full name") : null,
                    ),
                    const SizedBox(height: 14),
                    FMTextField(
                      controller: userNameController,
                      label: asProvider.getString("Username"),
                      hintText: asProvider.getString("Enter username"),
                      prefixIcon: const Icon(Icons.alternate_email_rounded),
                      validator: (v) => v == null || v.isEmpty ? asProvider.getString("Please enter username") : null,
                    ),
                    const SizedBox(height: 14),
                    FMTextField(
                      controller: emailController,
                      label: asProvider.getString("Email Address"),
                      hintText: asProvider.getString("Enter email address"),
                      keyboardType: TextInputType.emailAddress,
                      prefixIcon: const Icon(Icons.mail_outline_rounded),
                      validator: (v) => v == null || !v.contains('@') ? asProvider.getString("Valid email is required") : null,
                    ),
                    const SizedBox(height: 14),
                    Text(
                      asProvider.getString("Phone Number"),
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                    const SizedBox(height: 8),
                    IntlPhoneField(
                      decoration: SignupHelper().phoneFieldDecoration(),
                      initialCountryCode: 'SA',
                      disableLengthCheck: true,
                      textAlign: rtl.direction == 'ltr' ? TextAlign.left : TextAlign.right,
                      onChanged: (phone) {
                        _countryISOCode = phone.countryISOCode;
                        phoneController.text = phone.completeNumber;
                      },
                    ),
                    const SizedBox(height: 14),
                    FMTextField(
                      controller: passController,
                      label: asProvider.getString("Password"),
                      hintText: asProvider.getString("Enter password (min 8 chars)"),
                      obscureText: !_passVisible,
                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                      suffixIcon: IconButton(
                        icon: Icon(_passVisible ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                        onPressed: () => setState(() => _passVisible = !_passVisible),
                      ),
                      validator: (v) => v.toString().validPass,
                    ),
                    const SizedBox(height: 14),
                    FMTextField(
                      controller: repeatPassController,
                      label: asProvider.getString("Repeat Password"),
                      hintText: asProvider.getString("Re-enter password"),
                      obscureText: !_repeatPassVisible,
                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                      suffixIcon: IconButton(
                        icon: Icon(_repeatPassVisible ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                        onPressed: () => setState(() => _repeatPassVisible = !_repeatPassVisible),
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty) return asProvider.getString("Please retype your password");
                        if (v != passController.text) return asProvider.getString("Password did not match");
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),

                    // Section 3: Subtype-Specific Identity / Business Data
                    if (isIndividual) ...[
                      Text(
                        asProvider.getString("Identity & Qualifications"),
                        style: const TextStyle(color: FMColors.textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 12),
                      FMTextField(
                        controller: nationalIdController,
                        label: asProvider.getString("National ID / Iqama Number"),
                        hintText: asProvider.getString("10-digit National ID or Iqama"),
                        keyboardType: TextInputType.number,
                        prefixIcon: const Icon(Icons.badge_outlined),
                      ),
                      const SizedBox(height: 14),
                      DocumentUploadTile(
                        title: asProvider.getString("National ID / Iqama Document"),
                        subtitle: asProvider.getString("Upload a clear photo or PDF copy"),
                        isRequired: true,
                        filePath: prs.model.nationalIdDocumentPath,
                        errorMessage: prs.fieldErrors['national_id_document'],
                        onFilePicked: (path) => prs.setNationalIdDocument(path),
                      ),
                      const SizedBox(height: 18),
                      FMTextField(
                        controller: licenseController,
                        label: asProvider.getString("Professional License Number"),
                        hintText: asProvider.getString("Optional professional/freelance permit"),
                        prefixIcon: const Icon(Icons.card_membership_rounded),
                      ),
                      const SizedBox(height: 14),
                      DocumentUploadTile(
                        title: asProvider.getString("Professional License Document"),
                        subtitle: asProvider.getString("Upload if applicable"),
                        isRequired: false,
                        filePath: prs.model.licenseDocumentPath,
                        errorMessage: prs.fieldErrors['license_document'],
                        onFilePicked: (path) => prs.setLicenseDocument(path),
                      ),
                      const SizedBox(height: 18),

                      // Band / Group Option
                      FMSurfaceCard(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        child: Column(
                          children: [
                            SwitchListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(
                                asProvider.getString("Register as Band / Ensemble"),
                                style: const TextStyle(color: FMColors.textPrimary, fontWeight: FontWeight.w600, fontSize: 13),
                              ),
                              subtitle: Text(
                                asProvider.getString("Enable if you represent a musical band or group"),
                                style: const TextStyle(color: FMColors.textMuted, fontSize: 11),
                              ),
                              value: prs.model.isBandOrGroup,
                              activeColor: FMColors.cyan,
                              onChanged: (val) => prs.setBandFlag(val),
                            ),
                            if (prs.model.isBandOrGroup) ...[
                              const Divider(color: FMColors.border),
                              const SizedBox(height: 8),
                              FMTextField(
                                controller: bandNameController,
                                label: asProvider.getString("Band / Group Name"),
                                hintText: asProvider.getString("Enter band name"),
                              ),
                              const SizedBox(height: 12),
                              FMTextField(
                                controller: bandMembersController,
                                label: asProvider.getString("Number of Members"),
                                hintText: asProvider.getString("Minimum 2 members"),
                                keyboardType: TextInputType.number,
                              ),
                              const SizedBox(height: 8),
                            ],
                          ],
                        ),
                      ),
                    ] else ...[
                      // Company Section
                      Text(
                        asProvider.getString("Company & Commercial Registration"),
                        style: const TextStyle(color: FMColors.textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 12),
                      FMTextField(
                        controller: companyNameController,
                        label: asProvider.getString("Company / Agency Name"),
                        hintText: asProvider.getString("Registered company name"),
                        prefixIcon: const Icon(Icons.business_rounded),
                      ),
                      const SizedBox(height: 14),
                      FMTextField(
                        controller: crNumberController,
                        label: asProvider.getString("Commercial Registration (CR) Number"),
                        hintText: asProvider.getString("10-digit CR number"),
                        keyboardType: TextInputType.number,
                        prefixIcon: const Icon(Icons.numbers_rounded),
                      ),
                      const SizedBox(height: 14),
                      DocumentUploadTile(
                        title: asProvider.getString("CR Certificate Document"),
                        subtitle: asProvider.getString("Official CR document (PDF or image)"),
                        isRequired: true,
                        filePath: prs.model.crDocumentPath,
                        errorMessage: prs.fieldErrors['cr_document'],
                        onFilePicked: (path) => prs.setCrDocument(path),
                      ),
                      const SizedBox(height: 18),
                      Text(
                        asProvider.getString("Contact Person Details"),
                        style: const TextStyle(color: FMColors.textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 12),
                      FMTextField(
                        controller: contactNameController,
                        label: asProvider.getString("Contact Person Name"),
                        hintText: asProvider.getString("Authorized representative"),
                        prefixIcon: const Icon(Icons.person_outline_rounded),
                      ),
                      const SizedBox(height: 14),
                      FMTextField(
                        controller: contactEmailController,
                        label: asProvider.getString("Contact Person Email"),
                        hintText: asProvider.getString("representative@company.com"),
                        keyboardType: TextInputType.emailAddress,
                        prefixIcon: const Icon(Icons.email_outlined),
                      ),
                      const SizedBox(height: 14),
                      FMTextField(
                        controller: contactPhoneController,
                        label: asProvider.getString("Contact Person Phone"),
                        hintText: asProvider.getString("+9665xxxxxxxx"),
                        keyboardType: TextInputType.phone,
                        prefixIcon: const Icon(Icons.phone_outlined),
                      ),
                    ],

                    const SizedBox(height: 24),

                    // Section 4: Service Categories
                    const CategoryMultiSelect(),

                    const SizedBox(height: 24),

                    // Section 5: Location & Address
                    Text(
                      asProvider.getString("Location & Operating Area"),
                      style: const TextStyle(color: FMColors.textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    const SizedBox(height: 12),
                    const CountryStatesDropdowns(),
                    const SizedBox(height: 14),
                    FMTextField(
                      controller: addressController,
                      label: asProvider.getString("Address"),
                      hintText: asProvider.getString("Street, District, Building"),
                      prefixIcon: const Icon(Icons.location_on_outlined),
                    ),
                    const SizedBox(height: 14),
                    FMTextField(
                      controller: postCodeController,
                      label: asProvider.getString("Postal Code"),
                      hintText: asProvider.getString("Postal code (optional)"),
                      keyboardType: TextInputType.number,
                      prefixIcon: const Icon(Icons.markunread_mailbox_outlined),
                    ),
                    const SizedBox(height: 20),

                    // Section 6: Terms and Conditions
                    CheckboxListTile(
                      checkColor: Colors.white,
                      activeColor: FMColors.cyan,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        asProvider.getString("I agree with the terms and conditions and provider policies"),
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      value: prs.model.termsAgree,
                      onChanged: (val) => prs.setTermsAgree(val ?? false),
                      controlAffinity: ListTileControlAffinity.leading,
                    ),
                    const SizedBox(height: 20),

                    // Submit Button
                    FMPrimaryButton(
                      label: asProvider.getString("Submit Provider Application"),
                      isLoading: prs.isLoading,
                      onPressed: () {
                        if (_formKey.currentState?.validate() ?? false) {
                          _syncControllersToModel(prs);
                          prs.registerProvider(context);
                        }
                      },
                    ),
                    const SizedBox(height: 16),
                    SignupHelper().haveAccount(context),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
