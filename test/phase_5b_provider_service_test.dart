import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/provider_service_management_service.dart';

void main() {
  group('Phase 5B: Provider Service Item & Approval Status Tests', () {
    test('ProviderServiceItem parses status = 0 as pending approval', () {
      final json = {
        'id': 101,
        'title': 'DJ Sound System Setup',
        'price': 450.0,
        'is_service_on': 1,
        'status': 0,
      };

      final item = ProviderServiceItem.fromJson(json, 'https://example.com/dj.jpg');

      expect(item.id, 101);
      expect(item.title, 'DJ Sound System Setup');
      expect(item.price, 450.0);
      expect(item.status, 0);
      expect(item.isPendingApproval, isTrue);
      expect(item.isApproved, isFalse);
    });

    test('ProviderServiceItem parses status = 1 as approved', () {
      final json = {
        'id': 102,
        'title': 'Catering Package',
        'price': 1200,
        'is_service_on': 1,
        'status': 1,
      };

      final item = ProviderServiceItem.fromJson(json, null);

      expect(item.id, 102);
      expect(item.status, 1);
      expect(item.isPendingApproval, isFalse);
      expect(item.isApproved, isTrue);
    });

    test('ProviderServiceItem handles string status gracefully', () {
      final jsonPending = {
        'id': 103,
        'title': 'Photography Service',
        'price': '300.50',
        'is_service_on': '1',
        'status': '0',
      };
      final itemPending = ProviderServiceItem.fromJson(jsonPending, null);
      expect(itemPending.status, 0);
      expect(itemPending.isPendingApproval, isTrue);

      final jsonApproved = {
        'id': 104,
        'title': 'Lighting Package',
        'price': '600',
        'is_service_on': '1',
        'status': '1',
      };
      final itemApproved = ProviderServiceItem.fromJson(jsonApproved, null);
      expect(itemApproved.status, 1);
      expect(itemApproved.isApproved, isTrue);
    });

    test('ProviderServiceItem defaults to pending approval when status is missing or null', () {
      final jsonMissing = {
        'id': 105,
        'title': 'Stage Setup',
        'price': 800,
      };
      final item = ProviderServiceItem.fromJson(jsonMissing, null);
      expect(item.status, 0);
      expect(item.isPendingApproval, isTrue);
      expect(item.isApproved, isFalse);
    });

    test('AppStringService translates Phase 5B approval strings to English and Arabic', () {
      final appStringService = AppStringService();

      // Test English
      appStringService.setLanguage('en');
      expect(
        appStringService.getString('Pending Admin Approval'),
        equals('Pending Admin Approval'),
      );
      expect(
        appStringService.getString('Approved'),
        equals('Approved'),
      );
      expect(
        appStringService.getString('Service Submitted Successfully'),
        equals('Service Submitted Successfully'),
      );
      expect(
        appStringService.getString('View My Services'),
        equals('View My Services'),
      );

      // Test Arabic
      appStringService.setLanguage('ar');
      expect(
        appStringService.getString('Pending Admin Approval'),
        equals('قيد موافقة الإدارة'),
      );
      expect(
        appStringService.getString('Approved'),
        equals('معتمد'),
      );
      expect(
        appStringService.getString('Service Submitted Successfully'),
        equals('تم إرسال الخدمة بنجاح'),
      );
      expect(
        appStringService.getString('View My Services'),
        equals('عرض خدماتي'),
      );
    });
  });
}
