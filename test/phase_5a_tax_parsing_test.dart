import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/service_extra_model.dart';

void main() {
  group('Phase 5A DEF-07: Tax parsing and normalization tests', () {
    test('parses string integer tax "15" to double 15.0', () {
      expect(Service.parseTax("15"), 15.0);
    });

    test('parses string decimal tax "15.5" to double 15.5', () {
      expect(Service.parseTax("15.5"), 15.5);
    });

    test('parses string with surrounding whitespace "  15  " to double 15.0', () {
      expect(Service.parseTax("  15  "), 15.0);
    });

    test('parses int tax 15 to double 15.0', () {
      expect(Service.parseTax(15), 15.0);
    });

    test('parses double tax 15.0 to double 15.0', () {
      expect(Service.parseTax(15.0), 15.0);
    });

    test('parses null tax to double 0.0', () {
      expect(Service.parseTax(null), 0.0);
    });

    test('parses empty string tax "" to double 0.0', () {
      expect(Service.parseTax(""), 0.0);
    });

    test('parses invalid string tax "abc" to double 0.0 without throwing', () {
      expect(Service.parseTax("abc"), 0.0);
    });

    test('ServiceExtraModel json deserialization safely parses string tax from MySQL', () {
      final jsonSample = {
        "service": {
          "id": 1,
          "seller_id": 2,
          "title": "Test Service",
          "price": "100",
          "tax": "15",
          "image": null,
          "is_service_online": 0,
          "service_city_id": 1,
          "service_additional": [],
          "service_include": [],
          "service_benifit": [],
          "seller_for_mobile": {
            "id": 2,
            "name": "Seller Name",
            "email": "seller@example.com",
            "phone": "123456789",
            "image": null,
            "created_at": null,
            "country_id": null,
            "state_id": null,
            "city_id": null,
            "address": null,
            "country": null,
            "city": null,
            "area": null
          },
          "service_city": null
        },
        "service_image": []
      };

      final model = ServiceExtraModel.fromJson(jsonSample);
      expect(model.service.tax, 15.0);
      expect(model.service.tax, isA<double>());
    });
  });
}
