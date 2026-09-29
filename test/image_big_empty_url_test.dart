import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/view/services/components/image_big.dart';

void main() {
  testWidgets('ImageBig with empty imageLink safely renders without throwing', (tester) async {
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => RtlService()),
        ],
        child: const MaterialApp(
          home: Scaffold(
            body: ImageBig(
              serviceName: 'Test Service',
              imageLink: '',
            ),
          ),
        ),
      ),
    );

    // Let the image start loading
    await tester.pump();
    final exception = tester.takeException();
    expect(exception, isNull, reason: 'ImageBig should safely fall back to placeholder without throwing');
  });
}
