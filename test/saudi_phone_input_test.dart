import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/view/utils/saudi_phone_input.dart';

void main() {
  group('Saudi Mobile Phone Unit Tests', () {
    // a. valid 9-digit Saudi number
    test('a. valid 9-digit Saudi number starting with 5 passes validation', () {
      expect(validateSaudiPhone('512345678'), isNull);
      expect(validateSaudiPhone('582255554'), isNull);
      expect(validateSaudiPhone('500000000'), isNull);
    });

    // b. leading digit other than 5
    test('b. leading digit other than 5 fails validation', () {
      expect(validateSaudiPhone('612345678'), isNotNull);
      expect(validateSaudiPhone('123456789'), isNotNull);
      expect(validateSaudiPhone('712345678'), isNotNull);
      expect(validateSaudiPhone('0512345678'), isNotNull);
      expect(validateSaudiPhone('966512345678'), isNotNull);
    });

    // c. 8 digits
    test('c. 8 digits fails validation', () {
      expect(validateSaudiPhone('51234567'), isNotNull);
      expect(validateSaudiPhone('5000000'), isNotNull);
    });

    // d. 10+ digits
    test('d. 10+ digits fails validation', () {
      expect(validateSaudiPhone('5123456789'), isNotNull);
      expect(validateSaudiPhone('51234567890'), isNotNull);
    });

    // e. pasted +966512345678
    test('e. pasted +966512345678 is formatted and normalized to 512345678', () {
      final formatter = SaudiPhoneInputFormatter();
      const input = TextEditingValue(
        text: '+966512345678',
        selection: TextSelection.collapsed(offset: 13),
      );
      final result = formatter.formatEditUpdate(TextEditingValue.empty, input);
      expect(result.text, equals('512345678'));
      expect(result.selection.end, equals(9));
      expect(normalizeToLocalSaudiPhone('+966512345678'), equals('512345678'));
    });

    // f. pasted 966512345678
    test('f. pasted 966512345678 is formatted and normalized to 512345678', () {
      final formatter = SaudiPhoneInputFormatter();
      const input = TextEditingValue(
        text: '966512345678',
        selection: TextSelection.collapsed(offset: 12),
      );
      final result = formatter.formatEditUpdate(TextEditingValue.empty, input);
      expect(result.text, equals('512345678'));
      expect(result.selection.end, equals(9));
      expect(normalizeToLocalSaudiPhone('966512345678'), equals('512345678'));
    });

    // g. normalization of existing stored numbers
    test('g. normalization of existing stored numbers from profile/database', () {
      // With spaces and formatting
      expect(normalizeToLocalSaudiPhone('+966 58 225 5554'), equals('582255554'));
      expect(normalizeToLocalSaudiPhone('+966-51-234-5678'), equals('512345678'));
      // With 00966
      expect(normalizeToLocalSaudiPhone('00966512345678'), equals('512345678'));
      // Stored with local 05
      expect(normalizeToLocalSaudiPhone('0512345678'), equals('512345678'));
      // Already clean 5XXXXXXXX
      expect(normalizeToLocalSaudiPhone('512345678'), equals('512345678'));
      // Null or empty
      expect(normalizeToLocalSaudiPhone(null), equals(''));
      expect(normalizeToLocalSaudiPhone(''), equals(''));
    });

    // h. final API payload normalization
    test('h. final API payload normalization converts 5XXXXXXXX to +9665XXXXXXXX', () {
      expect(normalizeToBackendSaudiPhone('512345678'), equals('+966512345678'));
      expect(normalizeToBackendSaudiPhone('582255554'), equals('+966582255554'));
      expect(normalizeToBackendSaudiPhone('+966512345678'), equals('+966512345678'));
      expect(normalizeToBackendSaudiPhone('0512345678'), equals('+966512345678'));
      expect(normalizeToBackendSaudiPhone(''), equals(''));
      expect(normalizeToBackendSaudiPhone(null), equals(''));
    });
  });

  group('SaudiPhoneInput Widget Tests', () {
    testWidgets('renders fixed +966 Saudi badge and editable field in LTR direction', (tester) async {
      final controller = TextEditingController(text: '512345678');
      final formKey = GlobalKey<FormState>();

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Form(
              key: formKey,
              child: SaudiPhoneInput(
                controller: controller,
              ),
            ),
          ),
        ),
      );

      // Verify fixed country code badge
      expect(find.text('+966'), findsOneWidget);
      expect(find.text('🇸🇦'), findsOneWidget);

      // Verify initial number in editable field
      expect(find.text('512345678'), findsOneWidget);

      // Form validation succeeds for valid number
      expect(formKey.currentState!.validate(), isTrue);
    });

    testWidgets('shows validation error when empty or invalid', (tester) async {
      final controller = TextEditingController(text: '');
      final formKey = GlobalKey<FormState>();

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Form(
              key: formKey,
              child: SaudiPhoneInput(
                controller: controller,
              ),
            ),
          ),
        ),
      );

      // Validate empty field
      expect(formKey.currentState!.validate(), isFalse);
      await tester.pumpAndSettle();
      expect(find.text('Phone field is required'), findsOneWidget);

      // Enter invalid number (not starting with 5)
      controller.text = '612345678';
      expect(formKey.currentState!.validate(), isFalse);
      await tester.pumpAndSettle();
      expect(find.text('Please enter a valid phone number'), findsOneWidget);

      // Enter valid number
      controller.text = '512345678';
      expect(formKey.currentState!.validate(), isTrue);
      await tester.pumpAndSettle();
      expect(find.text('Please enter a valid phone number'), findsNothing);
    });
  });
}
