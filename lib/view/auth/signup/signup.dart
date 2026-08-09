import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/signup_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/signup/pages/signup_country_states.dart';
import 'package:funmoments/view/auth/signup/pages/signup_email_name.dart';
import 'package:funmoments/view/auth/signup/pages/signup_phone_pass.dart';

class SignupPage extends StatefulWidget {
  const SignupPage({Key? key}) : super(key: key);

  @override
  State<SignupPage> createState() => _SignupPageState();
}

class _SignupPageState extends State<SignupPage> {
  final PageController _pageController = PageController();
  final TextEditingController fullNameController = TextEditingController();
  final TextEditingController emailController = TextEditingController();
  final TextEditingController userNameController = TextEditingController();
  final TextEditingController newPasswordController = TextEditingController();
  final TextEditingController repeatNewPasswordController = TextEditingController();

  @override
  void initState() {
    super.initState();
    Provider.of<SignupService>(context, listen: false)
        .setPageController(_pageController);
    Provider.of<SignupService>(context, listen: false).setSelectedPageO(0);
  }

  @override
  void dispose() {
    _pageController.dispose();
    fullNameController.dispose();
    emailController.dispose();
    userNameController.dispose();
    newPasswordController.dispose();
    repeatNewPasswordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AppStringService>(
      builder: (context, asProvider, child) => Consumer<SignupService>(
        builder: (context, provider, child) => WillPopScope(
          onWillPop: () {
            if (provider.selectedPage == 0) {
              return Future.value(true);
            }
            _pageController.animateToPage(
              provider.selectedPage - 1,
              duration: const Duration(milliseconds: 300),
              curve: Curves.ease,
            );
            return Future.value(false);
          },
          child: Scaffold(
            backgroundColor: FMColors.background,
            appBar: AppBar(
              backgroundColor: Colors.transparent,
              surfaceTintColor: Colors.transparent,
              leading: IconButton(
                onPressed: () {
                  if (provider.selectedPage == 0) {
                    Navigator.pop(context);
                  } else {
                    _pageController.animateToPage(
                      provider.selectedPage - 1,
                      duration: const Duration(milliseconds: 300),
                      curve: Curves.ease,
                    );
                  }
                },
                icon: const Icon(Icons.arrow_back_ios_new_rounded),
              ),
            ),
            body: SafeArea(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const FMBrandLogo(height: 66),
                      const SizedBox(height: 20),
                      FMEditorialBanner(
                        assetPath: FMAssets.equipmentHero,
                        title: asProvider.getString('Rent Party Equipment'),
                        subtitle: asProvider.getString(
                          'Everything you need for music and events',
                        ),
                        ctaLabel: asProvider.getString('Book a DJ'),
                        height: 176,
                      ),
                      const SizedBox(height: 20),
                      Text(
                        asProvider.getString('Register to join us'),
                        style: Theme.of(context)
                            .textTheme
                            .headlineMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        asProvider.getString(
                            'Create your account to book or sell services.'),
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: 22),
                      Row(
                        children: List.generate(3, (i) {
                          final active = provider.selectedPage >= i;
                          return Expanded(
                            child: Row(
                              children: [
                                Container(
                                  height: 34,
                                  width: 34,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: active
                                        ? FMColors.magenta
                                        : FMColors.surface,
                                    border: Border.all(color: FMColors.border),
                                  ),
                                  child: Center(
                                    child: provider.selectedPage - 1 < i
                                        ? Text(
                                            '${i + 1}',
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          )
                                        : const Icon(
                                            Icons.check_rounded,
                                            color: Colors.white,
                                            size: 18,
                                          ),
                                  ),
                                ),
                                if (i < 2)
                                  Expanded(
                                    child: Container(
                                      height: 2,
                                      color: active
                                          ? FMColors.magenta
                                          : FMColors.border,
                                    ),
                                  ),
                              ],
                            ),
                          );
                        }),
                      ),
                      const SizedBox(height: 20),
                      FMSurfaceCard(
                        child: SizedBox(
                          height: 760,
                          child: PageView.builder(
                            controller: _pageController,
                            onPageChanged: provider.setSelectedPage,
                            itemCount: 3,
                            physics: const NeverScrollableScrollPhysics(),
                            itemBuilder: (context, i) {
                              if (i == 0) {
                                return SignupEmailName(
                                  fullNameController: fullNameController,
                                  userNameController: userNameController,
                                  emailController: emailController,
                                );
                              }
                              if (i == 1) {
                                return SignupPhonePass(
                                  passController: newPasswordController,
                                  repeatPassController:
                                      repeatNewPasswordController,
                                );
                              }
                              return SignupCountryStates(
                                emailController: emailController,
                                fullNameController: fullNameController,
                                passController: newPasswordController,
                                userNameController: userNameController,
                              );
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
        ),
      ),
    );
  }
}
