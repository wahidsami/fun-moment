import 'package:flutter/material.dart';
import 'package:funmoments/helper/extension/context_extension.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';

import '../auth/login/login.dart';
import '../utils/custom_button.dart';
import 'common_helper.dart';

class LoginOrRegister extends StatelessWidget {
  const LoginOrRegister({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      height: MediaQuery.of(context).size.height - 150,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          FMSurfaceCard(
            padding: EdgeInsets.zero,
            child: SizedBox(
              height: context.height / 5,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  FMAssetImageFrame(
                    assetPath: FMAssets.partyEnergy,
                    overlay: true,
                    borderRadius: BorderRadius.circular(FMRadii.lg),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(18),
                      child: Align(
                        alignment: Alignment.bottomLeft,
                        child: Text(
                        lnProvider.getString(
                            'Login or register to unlock your FUN MOMENT profile'),
                        style:
                            Theme.of(context).textTheme.headlineSmall?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          Padding(
              padding: const EdgeInsets.symmetric(horizontal: 30),
              child: CommonHelper().titleCommon(
                  "You'll have to login/register to edit or see your profile info.",
                  fontsize: 16,
                  textAlign: TextAlign.center)),
          const SizedBox(height: 20),
          CustomButton(
              btText: lnProvider.getString('Sign-In/Sign-Up'),
              onPressed: () {
                context.toPage(const LoginPage(hasBackButton: true));
              },
              isLoading: false,
              width: context.width / 2)
        ],
      ),
    );
  }
}
