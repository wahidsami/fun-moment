import 'package:flutter/material.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/theme/fun_moment_components.dart';
import 'package:funmoments/view/utils/responsive.dart';

class EmailNameFields extends StatelessWidget {
  const EmailNameFields({
    Key? key,
    this.fullNameController,
    this.userNameController,
    this.emailController,
  }) : super(key: key);

  final fullNameController;
  final userNameController;
  final emailController;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FMTextField(
          controller: fullNameController,
          label: lnProvider.getString('Full name'),
          hintText: lnProvider.getString("Enter your full name"),
          keyboardType: TextInputType.name,
          textInputAction: TextInputAction.next,
          prefixIcon: const Icon(Icons.person_rounded),
          validator: (value) {
            if (value == null || value.isEmpty) {
              return lnProvider.getString('Please enter your full name');
            }
            return null;
          },
        ),
        const SizedBox(height: 18),
        FMTextField(
          controller: userNameController,
          label: 'Username',
          hintText: lnProvider.getString("Enter your username"),
          textInputAction: TextInputAction.next,
          prefixIcon: const Icon(Icons.alternate_email_rounded),
          validator: (value) {
            if (value == null || value.isEmpty) {
              return lnProvider.getString('Please enter your username');
            }
            return null;
          },
        ),
        const SizedBox(height: 18),
        FMTextField(
          controller: emailController,
          label: lnProvider.getString("Email"),
          hintText: lnProvider.getString("Enter your email"),
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          prefixIcon: const Icon(Icons.mail_rounded),
          validator: (value) {
            if (value == null || value.isEmpty) {
              return lnProvider.getString('Please enter your email');
            }
            return null;
          },
        ),
      ],
    );
  }
}
