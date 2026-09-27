import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/theme/fun_moment_theme.dart';
import 'package:funmoments/view/utils/responsive.dart';
import '../../service/app_string_service.dart';
import '../../service/auth_services/delete_account_service.dart';
import '../booking/components/textarea_field.dart';
import '../utils/common_helper.dart';
import '../utils/constant_colors.dart';
import '../utils/constant_styles.dart';
import '../utils/custom_input.dart';
import '../utils/others_helper.dart';

class DeleteAccountPage extends StatelessWidget {
  DeleteAccountPage({Key? key}) : super(key: key);

  TextEditingController descController = TextEditingController();
  TextEditingController passwordController = TextEditingController();
  @override
  Widget build(BuildContext context) {
    var screenHeight = MediaQuery.of(context).size.height;

    ConstantColors cc = ConstantColors();
    return Scaffold(
      appBar: CommonHelper()
          .appbarCommon(lnProvider.tStrings['Delete account'], context, () {
        Navigator.pop(context);
      }),
      backgroundColor: FMColors.background,
      body: SingleChildScrollView(
        child: Container(
          padding: EdgeInsets.symmetric(
            horizontal: screenPadding,
          ),
          height: screenHeight - 100,
          child: Consumer<AppStringService>(
            builder: (context, ln, child) => Consumer<DeleteAccountService>(
              builder: (context, provider, child) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // dropdown ======>
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CommonHelper().labelCommon(ln.getString("Choose Reason")),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(horizontal: 15),
                        decoration: BoxDecoration(
                          color: FMColors.inputSurface,
                          border: Border.all(color: FMColors.border),
                          borderRadius: BorderRadius.circular(FMRadii.md),
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String>(
                            dropdownColor: FMColors.surfaceElevated,
                            value: provider.selecteddeactivateReason,
                            icon: const Icon(Icons.keyboard_arrow_down_rounded,
                                color: FMColors.magenta),
                            iconSize: 26,
                            elevation: 4,
                            style: const TextStyle(color: FMColors.textPrimary, fontSize: 14),
                            onChanged: (newValue) {
                              provider.setdeactivateReasonValue(newValue);

                              //setting the id of selected value
                              provider.setSelecteddeactivateReasonId(
                                  provider.deactivateReasonDropdownList[provider
                                      .deactivateReasonDropdownList
                                      .indexOf(newValue!)]);
                            },
                            items: provider.deactivateReasonDropdownList
                                .map<DropdownMenuItem<String>>((value) {
                              return DropdownMenuItem(
                                value: value,
                                child: Text(
                                  ln.getString(value),
                                  style: const TextStyle(
                                      color: FMColors.textPrimary),
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                      )
                    ],
                  ),

                  const SizedBox(
                    height: 20,
                  ),
                  CommonHelper().labelCommon(ln.getString("Short description")),

                  TextareaField(
                    hintText: ln.getString('description'),
                    notesController: descController,
                  ),
                  const SizedBox(
                    height: 20,
                  ),
                  CommonHelper().labelCommon(ln.getString("Enter password")),

                  CustomInput(
                    hintText: ln.getString('Enter password'),
                    controller: passwordController,
                  ),

                  const SizedBox(
                    height: 30,
                  ),

                  Consumer<DeleteAccountService>(
                    builder: (context, provider, child) => CommonHelper()
                        .buttonOrange(ln.getString("Delete"), () {
                      if (provider.isloading == false) {
                        if (descController.text.isEmpty) {
                          OthersHelper().showToast(
                              ln.getString('Please enter a description'),
                              Colors.black);
                          return;
                        }
                        if (passwordController.text.length < 6) {
                          OthersHelper().showToast(
                              ln.getString('Please enter a valid password'),
                              Colors.black);
                          return;
                        }

                        provider.deleteAccount(
                          context,
                          passwordController.text,
                          descController.text,
                        );
                        // Navigator.pushReplacement<void, void>(
                        //   context,
                        //   MaterialPageRoute<void>(
                        //     builder: (BuildContext context) =>
                        //         const Homepage(),
                        //   ),
                        // );
                      }
                    },
                            isloading:
                                provider.isloading == false ? false : true,
                            bgColor: cc.warningColor),
                  ),

                  const SizedBox(
                    height: 30,
                  )
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
