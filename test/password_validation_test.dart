import 'package:flutter_test/flutter_test.dart';

String? validatePassword(String pass) {
  String? value;
  if (pass.isEmpty) {
    value = 'Please enter your password';
  } else if (pass.length < 8) {
    value = 'Password should be at least 8 characters long';
  } else if (!RegExp(r'[A-Z]').hasMatch(pass)) {
    value = 'Password should contain at least 1 uppercase letter';
  } else if (!RegExp(r'[a-z]').hasMatch(pass)) {
    value = 'Password should contain at least 1 lowercase letter';
  } else if (!RegExp(r'\d').hasMatch(pass)) {
    value = 'Password should contain at least 1 digit';
  } else if (!RegExp(r'[^a-zA-Z0-9\s]').hasMatch(pass)) {
    value = 'Password should contain at least 1 special character';
  }
  return value;
}

void main() {
  group('Password Validator Tests', () {
    test('Valid password with # and . (Tekkentag2#.#) passes', () {
      expect(validatePassword('Tekkentag2#.#'), isNull);
    });

    test('Valid passwords with other common special characters pass', () {
      expect(validatePassword('FunMoment@2026'), isNull);
      expect(validatePassword('Password123!'), isNull);
      expect(validatePassword('Secret_Pass1'), isNull);
      expect(validatePassword('Pass-Word2'), isNull);
      expect(validatePassword('Admin%2024'), isNull);
      expect(validatePassword('Secure\$99'), isNull);
      expect(validatePassword('Testing*7'), isNull);
      expect(validatePassword('Query?123'), isNull);
      expect(validatePassword('Ampersand&1'), isNull);
      expect(validatePassword('Bracket[1]A'), isNull);
      expect(validatePassword('Plus+Code9'), isNull);
    });

    test('Missing uppercase is rejected', () {
      expect(
        validatePassword('tekkentag2#.#'),
        equals('Password should contain at least 1 uppercase letter'),
      );
    });

    test('Missing lowercase is rejected', () {
      expect(
        validatePassword('TEKKENTAG2#.#'),
        equals('Password should contain at least 1 lowercase letter'),
      );
    });

    test('Missing digit/number is rejected', () {
      expect(
        validatePassword('Tekkentag#.#'),
        equals('Password should contain at least 1 digit'),
      );
    });

    test('Missing special character is rejected', () {
      expect(
        validatePassword('Tekkentag22'),
        equals('Password should contain at least 1 special character'),
      );
    });

    test('Space alone does NOT count as special character', () {
      expect(
        validatePassword('Tekkentag 2'),
        equals('Password should contain at least 1 special character'),
      );
    });

    test('Too-short password (5 chars) is rejected', () {
      expect(
        validatePassword('Tek2#'),
        equals('Password should be at least 8 characters long'),
      );
    });

    test('Too-short password (7 chars) is rejected', () {
      expect(
        validatePassword('Tek2#.#'),
        equals('Password should be at least 8 characters long'),
      );
    });

    test('Empty password is rejected with prompt to enter password', () {
      expect(
        validatePassword(''),
        equals('Please enter your password'),
      );
    });
  });
}
