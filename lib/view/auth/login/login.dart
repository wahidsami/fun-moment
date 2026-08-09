import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform, TargetPlatform;
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/helper/extension/string_extension.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/auth_services/apple_sign_in_sevice.dart';
import 'package:funmoments/service/auth_services/facebook_login_service.dart';
import 'package:funmoments/service/auth_services/google_sign_service.dart';
import 'package:funmoments/service/auth_services/login_service.dart';
import 'package:funmoments/service/profile_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/auth/reset_password/reset_pass_email_page.dart';
import 'package:funmoments/view/auth/signup/signup.dart';
import 'package:funmoments/view/utils/others_helper.dart';
import 'package:funmoments/view/utils/responsive.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({Key? key, this.hasBackButton = true}) : super(key: key);

  final hasBackButton;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  late bool _passwordVisible;
  final _formKey = GlobalKey<FormState>();
  final TextEditingController emailController = TextEditingController();
  final TextEditingController passwordController = TextEditingController();
  bool keepLoggedIn = true;

  @override
  void initState() {
    super.initState();
    _passwordVisible = false;
    _initPassword();
  }

  Future<void> _initPassword() async {
    final prefs = await SharedPreferences.getInstance();
    keepLoggedIn = prefs.getBool('keepLoggedIn') ?? true;
    if (!keepLoggedIn) return;
    emailController.text = prefs.getString('email') ?? '';
    passwordController.text = prefs.getString('pass') ?? '';
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    emailController.dispose();
    passwordController.dispose();
    super.dispose();
  }

  Widget _socialButton({
    required String asset,
    required String label,
    required VoidCallback onTap,
    bool loading = false,
  }) {
    return InkWell(
      onTap: loading ? null : onTap,
      borderRadius: BorderRadius.circular(FMRadii.md),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: FMColors.surface,
          borderRadius: BorderRadius.circular(FMRadii.md),
          border: Border.all(color: FMColors.border),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Image.asset(asset, height: 18, width: 18),
            const SizedBox(width: 10),
            Text(
              label,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: FMColors.textPrimary,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FMColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.all(20),
          child: Column(
            children: [
              Stack(
                children: [
                  Positioned.fill(
                    child: FMAssetImageFrame(
                      assetPath: FMAssets.djHero,
                      overlay: true,
                      borderRadius: BorderRadius.circular(FMRadii.xl),
                    ),
                  ),
                  Container(
                    height: 220,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(FMRadii.xl),
                      gradient: RadialGradient(
                        colors: [
                          FMColors.magenta.withOpacity(.14),
                          FMColors.background.withOpacity(.72),
                          FMColors.cyan.withOpacity(.08),
                        ],
                        radius: 1.1,
                      ),
                      border: Border.all(color: FMColors.border),
                    ),
                  ),
                  Positioned.fill(
                    child: Center(
                      child: Padding(
                        padding: const EdgeInsets.all(26),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const FMBrandLogo(height: 74),
                            const SizedBox(height: 18),
                            Text(
                              lnProvider.getString('Welcome back'),
                              style: Theme.of(context)
                                  .textTheme
                                  .headlineMedium
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                  ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              lnProvider.getString(
                                  'Login to continue your FUN MOMENT journey'),
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: FMColors.magentaLight,
                                  ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  if (widget.hasBackButton == true)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: IconButton(
                        onPressed: () => context.popFalse,
                        icon: const Icon(Icons.arrow_back_ios_new_rounded),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 18),
              FMSurfaceCard(
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Consumer<AppStringService>(
                        builder: (context, asProvider, child) => Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            FMTextField(
                              controller: emailController,
                              label: asProvider.getString('Email or username'),
                              hintText: asProvider.getString('Email'),
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              autofillHints: const [
                                AutofillHints.username,
                                AutofillHints.email,
                              ],
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return asProvider.getString(
                                      'Please enter your email or username');
                                }
                                return null;
                              },
                              prefixIcon: const Icon(Icons.person_rounded),
                            ),
                            const SizedBox(height: 18),
                            Text(
                              asProvider.getString('Password'),
                              style: Theme.of(context).textTheme.labelLarge,
                            ),
                            const SizedBox(height: 8),
                            TextFormField(
                              controller: passwordController,
                              obscureText: !_passwordVisible,
                              textInputAction: TextInputAction.done,
                              autofillHints: const [AutofillHints.password],
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return asProvider
                                      .getString('Please enter your password');
                                }
                                return null;
                              },
                              decoration: InputDecoration(
                                hintText: asProvider.getString('Enter password'),
                                prefixIcon: const Icon(Icons.lock_rounded),
                                suffixIcon: IconButton(
                                  onPressed: () {
                                    setState(() {
                                      _passwordVisible = !_passwordVisible;
                                    });
                                  },
                                  icon: Icon(
                                    _passwordVisible
                                        ? Icons.visibility_off_outlined
                                        : Icons.visibility_outlined,
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: CheckboxListTile(
                                    value: keepLoggedIn,
                                    onChanged: (newValue) {
                                      setState(() {
                                        keepLoggedIn = !keepLoggedIn;
                                      });
                                    },
                                    controlAffinity:
                                        ListTileControlAffinity.leading,
                                    contentPadding: EdgeInsets.zero,
                                    title: Text(
                                      asProvider.getString('Remember me'),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium
                                          ?.copyWith(color: FMColors.textSecondary),
                                    ),
                                  ),
                                ),
                                TextButton(
                                  onPressed: () {
                                    Navigator.push(
                                      context,
                                      MaterialPageRoute<void>(
                                        builder: (BuildContext context) =>
                                            const ResetPassEmailPage(),
                                      ),
                                    );
                                  },
                                  child: Text(asProvider
                                      .getString('Forgot Password?')),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            Consumer<LoginService>(
                              builder: (context, provider, child) =>
                                  FMPrimaryButton(
                                label: asProvider.getString('Login'),
                                isLoading: provider.isloading,
                                onPressed: () {
                                  if (provider.isloading == false &&
                                      _formKey.currentState!.validate()) {
                                    provider
                                        .login(
                                      emailController.text.trim(),
                                      passwordController.text,
                                      context,
                                      keepLoggedIn,
                                    )
                                        .then((value) {
                                      if (value == true) {
                                        context.popTrue;
                                      }
                                    });
                                  }
                                },
                              ),
                            ),
                            const SizedBox(height: 16),
                            Center(
                              child: RichText(
                                text: TextSpan(
                                  text: asProvider.getString(
                                          "Don't have account?") +
                                      '  ',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(color: FMColors.textMuted),
                                  children: [
                                    TextSpan(
                                      recognizer: TapGestureRecognizer()
                                        ..onTap = () {
                                          Navigator.push(
                                            context,
                                            MaterialPageRoute(
                                              builder: (context) =>
                                                  const SignupPage(),
                                            ),
                                          );
                                        },
                                      text: asProvider.getString('Sign up'),
                                      style: Theme.of(context)
                                          .textTheme
                                          .labelLarge
                                          ?.copyWith(color: FMColors.magenta),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(height: 18),
                            Row(
                              children: [
                                const Expanded(child: Divider()),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 12),
                                  child: Text(
                                    asProvider.getString('Or'),
                                    style: Theme.of(context)
                                        .textTheme
                                        .labelLarge
                                        ?.copyWith(color: FMColors.textMuted),
                                  ),
                                ),
                                const Expanded(child: Divider()),
                              ],
                            ),
                            const SizedBox(height: 18),
                            Consumer<GoogleSignInService>(
                              builder: (context, gProvider, child) =>
                                  _socialButton(
                                asset: 'assets/icons/google.png',
                                label: asProvider.getString('Login with Google'),
                                loading: gProvider.isloading,
                                onTap: () {
                                  if (gProvider.isloading == false) {
                                    gProvider.googleLogin(context);
                                  }
                                },
                              ),
                            ),
                            if (!kIsWeb && defaultTargetPlatform == TargetPlatform.iOS) ...[
                              const SizedBox(height: 14),
                              Consumer<AppleSignInService>(
                                builder: (context, aProvider, child) =>
                                    _socialButton(
                                  asset: 'assets/icons/apple.png',
                                  label: 'Sign in with Apple'.tr(),
                                  loading: aProvider.isloading,
                                  onTap: () async {
                                    if (aProvider.isloading == false) {
                                      aProvider.setLoadingTrue();
                                      await aProvider
                                          .appleLogin(context, autoLogin: true)
                                          .then((value) async {
                                        if (value == true) {
                                          await Provider.of<ProfileService>(
                                                  context,
                                                  listen: false)
                                              .fetchData();
                                          context.popTrue;
                                        }
                                      }).onError((error, stackTrace) =>
                                              aProvider.setLoadingFalse());
                                      aProvider.setLoadingFalse();
                                    }
                                  },
                                ),
                              ),
                            ],
                            const SizedBox(height: 14),
                            Consumer<FacebookLoginService>(
                              builder: (context, fProvider, child) =>
                                  _socialButton(
                                asset: 'assets/icons/facebook.png',
                                label: asProvider.getString('Login with Facebook'),
                                loading: fProvider.isloading,
                                onTap: () {
                                  if (fProvider.isloading == false) {
                                    fProvider.checkIfLoggedIn(context);
                                  }
                                },
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
