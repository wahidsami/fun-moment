import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:funmoments/model/service_details_model.dart';

void main() {
  test('Parse real production service5.json', () {
    final file = File('C:/Users/wahid/.gemini/antigravity-ide/brain/4ef3e690-bb9f-4bc7-b667-bff065d4715e/scratch/service5.json');
    final jsonStr = file.readAsStringSync();
    final Map<String, dynamic> raw = jsonDecode(jsonStr);

    print('Starting ServiceDetailsModel.fromJson test...');
    try {
      final model = ServiceDetailsModel.fromJson(raw);
      print('Model parsed successfully!');
      print('Service title: ${model.serviceDetails.title}');
      print('Service image imgUrl: "${model.serviceImage?.imgUrl}"');
      print('Seller name: ${model.serviceSellerName}');
      print('Includes count: ${model.serviceIncludes.length}');
      expect(model.serviceDetails.title, isNotEmpty);
    } catch (e, st) {
      print('CRASH IN fromJson: $e');
      print('StackTrace: $st');
      rethrow;
    }
  });
}
