import 'package:flutter/material.dart';
import 'package:funmoments/view/utils/saudi_phone_input.dart';
import 'package:page_transition/page_transition.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/booking_services/book_service.dart';
import 'package:funmoments/service/booking_services/personalization_service.dart';
import 'package:funmoments/service/dropdowns_services/area_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/country_dropdown_service.dart';
import 'package:funmoments/service/dropdowns_services/state_dropdown_services.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/auth/signup/signup_helper.dart';
import 'package:funmoments/view/booking/book_confirmation_page.dart';
import 'package:funmoments/view/booking/booking_helper.dart';
import 'package:funmoments/view/utils/common_helper.dart';
import 'package:funmoments/view/utils/constant_colors.dart';
import 'package:funmoments/view/utils/constant_styles.dart';
import 'package:funmoments/view/utils/responsive.dart';

import '../../service/book_steps_service.dart';
import '../utils/custom_input.dart';
import 'components/steps.dart';

class DeliveryAddressPage extends StatefulWidget {
  const DeliveryAddressPage({Key? key}) : super(key: key);

  @override
  _DeliveryAddressPageState createState() => _DeliveryAddressPageState();
}

class _DeliveryAddressPageState extends State<DeliveryAddressPage> {
  final _formKey = GlobalKey<FormState>();

  TextEditingController emailController = TextEditingController();
  TextEditingController userNameController = TextEditingController();
  TextEditingController phoneController = TextEditingController();
  TextEditingController postCodeController = TextEditingController();
  TextEditingController addressController = TextEditingController();
  TextEditingController buildingNumberController = TextEditingController();
  TextEditingController notesController = TextEditingController();

  String? countryCode;

  @override
  void initState() {
    super.initState();

    final profile = Provider.of<ProfileService>(context, listen: false).profileDetails?.userDetails;
    countryCode = profile?.countryCode ?? 'SA';

    userNameController.text = profile?.name ?? '';
    emailController.text = profile?.email ?? '';
    phoneController.text = normalizeToLocalSaudiPhone(profile?.phone);
    postCodeController.text = profile?.postCode ?? '';
    addressController.text = profile?.address ?? '';
  }

  @override
  void dispose() {
    emailController.dispose();
    userNameController.dispose();
    phoneController.dispose();
    postCodeController.dispose();
    addressController.dispose();
    buildingNumberController.dispose();
    notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    ConstantColors cc = ConstantColors();
    return Listener(
      onPointerDown: (_) {
        FocusScopeNode currentFocus = FocusScope.of(context);
        if (!currentFocus.hasPrimaryFocus) {
          currentFocus.focusedChild?.unfocus();
        }
      },
      child: WillPopScope(
        onWillPop: () {
          BookStepsService().decreaseStep(context);
          return Future.value(true);
        },
        child: Scaffold(
          backgroundColor: cc.bgColor,
          appBar: CommonHelper()
              .appbarForBookingPages(lnProvider.getString('Address'), context),
          body: Consumer<AppStringService>(
            builder: (context, asProvider, child) =>
                Consumer<PersonalizationService>(
              builder: (context, personalizatioProvider, child) {
                // Resolve localized names for confirmed location display
                final stateP = Provider.of<StateDropdownService>(context, listen: false);
                final areaP = Provider.of<AreaDropdownService>(context, listen: false);
                final countryP = Provider.of<CountryDropdownService>(context, listen: false);

                String selectedCountry = countryP.selectedCountry;
                if (selectedCountry.toLowerCase() == 'saudi arabia') {
                  selectedCountry = asProvider.getString('Saudi Arabia');
                }
                String selectedCity = stateP.selectedState;
                if (selectedCity.toLowerCase() == 'riyadh') {
                  selectedCity = asProvider.getString('Riyadh');
                }
                String selectedArea = areaP.selectedArea;
                if (selectedArea.toLowerCase() == 'olaya') {
                  selectedArea = asProvider.getString('Olaya');
                }

                return Column(
                  children: [
                    Expanded(
                      child: SingleChildScrollView(
                        physics: physicsCommon,
                        child: Container(
                          padding: EdgeInsets.symmetric(
                            horizontal: screenPadding,
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Progress bar
                              personalizatioProvider.isOnline == 0
                                  ? Steps(cc: cc)
                                  : Container(),

                              CommonHelper().titleCommon(
                                  asProvider.getString('Booking Information')),

                              const SizedBox(height: 16),

                              // Confirmed Location Summary Card (Dark FUN MOMENT Theme)
                              if (personalizatioProvider.isOnline == 0) ...[
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 16, vertical: 14),
                                  decoration: BoxDecoration(
                                    color: cc.white.withOpacity(0.04),
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(
                                      color: cc.primaryColor.withOpacity(0.25),
                                      width: 1,
                                    ),
                                  ),
                                  child: Row(
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.all(8),
                                        decoration: BoxDecoration(
                                          color: cc.primaryColor.withOpacity(0.12),
                                          shape: BoxShape.circle,
                                        ),
                                        child: Icon(Icons.location_on,
                                            color: cc.primaryColor, size: 20),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              asProvider.getString('Selected Location'),
                                              style: TextStyle(
                                                fontSize: 12,
                                                color: cc.greyFour,
                                                fontFamily: 'Cairo',
                                              ),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              '$selectedCountry • $selectedCity • $selectedArea',
                                              style: const TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.bold,
                                                fontFamily: 'Cairo',
                                              ),
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ],
                                        ),
                                      ),
                                      InkWell(
                                        onTap: () {
                                          BookStepsService().decreaseStep(context);
                                          Navigator.pop(context);
                                        },
                                        borderRadius: BorderRadius.circular(8),
                                        child: Padding(
                                          padding: const EdgeInsets.symmetric(
                                              horizontal: 8, vertical: 4),
                                          child: Icon(
                                            Icons.edit_location_alt_outlined,
                                            color: cc.primaryColor,
                                            size: 20,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 20),
                              ],

                              Form(
                                key: _formKey,
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    // Name ============>
                                    CommonHelper().labelCommon(
                                        asProvider.getString('Name')),
                                    CustomInput(
                                      controller: userNameController,
                                      validation: (value) {
                                        if (value == null || value.trim().isEmpty) {
                                          return asProvider.getString(
                                              'Please enter your full name');
                                        }
                                        return null;
                                      },
                                      hintText: asProvider
                                          .getString('Enter your name'),
                                      icon: 'assets/icons/user.png',
                                      textInputAction: TextInputAction.next,
                                    ),
                                    const SizedBox(height: 18),

                                    // Phone number field ============>
                                    CommonHelper().labelCommon(
                                        asProvider.getString('Phone')),
                                    SaudiPhoneInput(
                                      controller: phoneController,
                                      asProvider: asProvider,
                                    ),
                                    const SizedBox(height: 18),

                                    // Email ============>
                                    CommonHelper().labelCommon(
                                        asProvider.getString('Email')),
                                    CustomInput(
                                      controller: emailController,
                                      validation: (value) {
                                        if (value == null || value.trim().isEmpty) {
                                          return asProvider.getString(
                                              'Please enter your email');
                                        }
                                        if (!value.contains('@')) {
                                          return asProvider.getString(
                                              'Please enter a valid email');
                                        }
                                        return null;
                                      },
                                      hintText: lnProvider
                                          .getString("Enter your email"),
                                      icon: 'assets/icons/email-grey.png',
                                      textInputAction: TextInputAction.next,
                                    ),
                                    const SizedBox(height: 18),

                                    // Physical Delivery Address Fields (Offline Service)
                                    if (personalizatioProvider.isOnline == 0) ...[
                                      // Street Address ============>
                                      CommonHelper().labelCommon(
                                          asProvider.getString('Street address')),
                                      CustomInput(
                                        controller: addressController,
                                        validation: (value) {
                                          if (value == null ||
                                              value.trim().isEmpty) {
                                            return asProvider.getString(
                                                'Enter street address');
                                          }
                                          return null;
                                        },
                                        hintText: asProvider.getString(
                                            'Enter street address'),
                                        icon: 'assets/icons/location.png',
                                        textInputAction: TextInputAction.next,
                                      ),
                                      const SizedBox(height: 18),

                                      // Building Number & Postal Code Row ============>
                                      Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          // Building Number (Saudi National Address 4-digit standard)
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                CommonHelper().labelCommon(
                                                    asProvider.getString(
                                                        'Building number')),
                                                CustomInput(
                                                  controller:
                                                      buildingNumberController,
                                                  isNumberField: true,
                                                  maxLength: 4,
                                                  hintText: asProvider.getString(
                                                      'Enter building number'),
                                                  icon: 'assets/icons/location.png',
                                                  textInputAction:
                                                      TextInputAction.next,
                                                ),
                                              ],
                                            ),
                                          ),
                                          const SizedBox(width: 14),

                                          // Postal Code (5 digits)
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                CommonHelper().labelCommon(
                                                    asProvider.getString(
                                                        'Post code')),
                                                CustomInput(
                                                  controller:
                                                      postCodeController,
                                                  isNumberField: true,
                                                  maxLength: 5,
                                                  hintText: asProvider.getString(
                                                      'Enter your post code'),
                                                  icon: 'assets/icons/location.png',
                                                  textInputAction:
                                                      TextInputAction.next,
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 18),

                                      // Additional details / Notes ============>
                                      CommonHelper().labelCommon(
                                          asProvider.getString(
                                              'Additional details / Notes')),
                                      Container(
                                        decoration: BoxDecoration(
                                          borderRadius:
                                              BorderRadius.circular(10),
                                        ),
                                        child: TextFormField(
                                          controller: notesController,
                                          maxLines: 3,
                                          style: const TextStyle(
                                            fontSize: 14,
                                            fontFamily: 'Cairo',
                                          ),
                                          decoration: InputDecoration(
                                            hintText: asProvider.getString(
                                                'Enter additional details (e.g. apartment, floor, landmark)'),
                                            hintStyle: TextStyle(
                                              fontSize: 13,
                                              color: cc.greyFour,
                                              fontFamily: 'Cairo',
                                            ),
                                            enabledBorder: OutlineInputBorder(
                                              borderSide: BorderSide(
                                                  color: cc.greyFive),
                                              borderRadius:
                                                  BorderRadius.circular(9),
                                            ),
                                            focusedBorder: OutlineInputBorder(
                                              borderSide: BorderSide(
                                                  color: cc.primaryColor),
                                            ),
                                            contentPadding: const EdgeInsets
                                                .symmetric(
                                                horizontal: 14, vertical: 14),
                                          ),
                                        ),
                                      ),
                                    ],

                                    const SizedBox(height: 120),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),

                    // Next Button Footer
                    Container(
                      height: 110,
                      padding: EdgeInsets.only(
                          left: screenPadding, top: 30, right: screenPadding),
                      decoration: BookingHelper().bottomSheetDecoration(),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CommonHelper()
                              .buttonOrange(asProvider.getString('Next'), () {
                            if (_formKey.currentState!.validate()) {
                              // Build full address combining street and building number
                              String street = addressController.text.trim();
                              String building =
                                  buildingNumberController.text.trim();
                              String fullAddress = street;
                              if (building.isNotEmpty) {
                                fullAddress = '$street, مبنى $building';
                              }

                              // Advance steps
                              BookStepsService().onNext(context);

                              // Normalize phone for backend / PayTabs
                              String normalizedPhone =
                                  normalizeToBackendSaudiPhone(phoneController.text);

                              // Save address information to BookService
                              Provider.of<BookService>(context, listen: false)
                                  .setAddress(
                                      userNameController.text.trim(),
                                      emailController.text.trim(),
                                      normalizedPhone,
                                      postCodeController.text.trim(),
                                      fullAddress,
                                      notesController.text.trim());

                              Navigator.push(
                                  context,
                                  PageTransition(
                                      type: PageTransitionType.rightToLeft,
                                      child: const BookConfirmationPage()));
                            }
                          }),
                        ],
                      ),
                    )
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}
