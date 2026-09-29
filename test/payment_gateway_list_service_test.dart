import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/service/payment_gateway_list_service.dart';

void main() {
  group('PaymentGatewayListService Unit Tests', () {
    test('Initial state is correct', () {
      final service = PaymentGatewayListService();

      expect(service.paymentList, isEmpty);
      expect(service.isloading, isFalse);
      expect(service.isLoaded, isFalse);
      expect(service.hasError, isFalse);
      expect(service.errorMessage, isNull);
      expect(service.selectedMethodName, isNull);
    });

    test('setLoadingTrue and setLoadingFalse update state and notify', () {
      final service = PaymentGatewayListService();
      int notifyCount = 0;
      service.addListener(() => notifyCount++);

      service.setLoadingTrue();
      expect(service.isloading, isTrue);
      expect(notifyCount, 1);

      service.setLoadingFalse();
      expect(service.isloading, isFalse);
      expect(notifyCount, 2);
    });

    test('setSelectedMethodName updates selectedMethodName', () {
      final service = PaymentGatewayListService();
      service.setSelectedMethodName('paytabs');

      expect(service.selectedMethodName, 'paytabs');
    });

    test('setKey correctly extracts paytabs configuration', () {
      final service = PaymentGatewayListService();
      service.paymentList = [
        {
          'name': 'paytabs',
          'profile_id': '12345',
          'server_key': 'test_server_key',
          'test_mode': true,
        }
      ];

      service.setKey('paytabs', 0);

      expect(service.paytabProfileId, '12345');
      expect(service.serverkey, 'test_server_key');
      expect(service.isTestMode, isTrue);
    });

    test('Manual payment and cash on delivery keys are safely set', () {
      final service = PaymentGatewayListService();
      service.paymentList = [
        {
          'name': 'manual_payment',
          'test_mode': false,
        },
        {
          'name': 'cash_on_delivery',
          'test_mode': false,
        }
      ];

      service.setKey('manual_payment', 0);
      expect(service.publicKey, '');
      expect(service.serverkey, '');

      service.setKey('cash_on_delivery', 1);
      expect(service.publicKey, '');
      expect(service.serverkey, '');
    });

    test('Empty gateway list is properly handled as empty state', () {
      final service = PaymentGatewayListService();
      service.paymentList = [];
      service.isLoaded = true;
      service.hasError = false;

      expect(service.paymentList.isEmpty, isTrue);
      expect(service.hasError, isFalse);
      expect(service.isloading, isFalse);
    });

    test('Error state is properly set with descriptive message', () {
      final service = PaymentGatewayListService();
      service.hasError = true;
      service.errorMessage = 'Network timeout while loading payment gateways. Please try again.';
      service.isLoaded = true;

      expect(service.hasError, isTrue);
      expect(service.errorMessage, contains('timeout'));
      expect(service.paymentList, isEmpty);
    });
  });
}
