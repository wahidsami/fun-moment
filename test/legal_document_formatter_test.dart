import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:provider/provider.dart';
import 'package:funmoments/service/app_string_service.dart';
import 'package:funmoments/service/legal_document_formatter.dart';
import 'package:funmoments/service/rtl_service.dart';
import 'package:funmoments/service/terms_service.dart';
import 'package:funmoments/view/auth/signup/components/terms_conditions_modal.dart';

class _FakeTermsService extends TermsService {
  final PublishedTerms termsToReturn;
  _FakeTermsService(this.termsToReturn);

  @override
  Future<PublishedTerms> fetchCurrentTerms({
    required String role,
    required String lang,
    dynamic client,
    Duration timeout = const Duration(seconds: 15),
  }) async {
    return termsToReturn;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('LegalDocumentFormatter Unit Tests', () {
    test('F-01: Converts Markdown headings #, ##, ### to semantic tags', () {
      const input = '# Level 1 Title\n\n## Level 2 Section\n\n### Level 3 Subsection';
      final html = LegalDocumentFormatter.formatToHtml(input);

      expect(html, contains('<h1>Level 1 Title</h1>'));
      expect(html, contains('<h2>Level 2 Section</h2>'));
      expect(html, contains('<h3>Level 3 Subsection</h3>'));
      expect(html, isNot(contains('# Level 1 Title')));
      expect(html, isNot(contains('## Level 2 Section')));
    });

    test('F-02: Converts bold **text** and italic *text* into strong and em', () {
      const input = 'This is **crucial bold text** and this is *italicized note*.';
      final html = LegalDocumentFormatter.formatToHtml(input);

      expect(html, contains('<strong>crucial bold text</strong>'));
      expect(html, contains('<em>italicized note</em>'));
      expect(html, isNot(contains('**crucial bold text**')));
      expect(html, isNot(contains('*italicized note*')));
    });

    test('F-03: Groups bullet list items into <ul> and numbered into <ol>', () {
      const input = '''
## Conditions
- First bullet item
- Second bullet item

## Next Steps
1. First numbered step
2. Second numbered step
''';
      final html = LegalDocumentFormatter.formatToHtml(input);

      expect(html, contains('<ul>'));
      expect(html, contains('<li>First bullet item</li>'));
      expect(html, contains('<li>Second bullet item</li>'));
      expect(html, contains('</ul>'));

      expect(html, contains('<ol>'));
      expect(html, contains('<li>First numbered step</li>'));
      expect(html, contains('<li>Second numbered step</li>'));
      expect(html, contains('</ol>'));
    });

    test('F-04: Arabic Markdown text with mixed numbering and punctuation formats correctly', () {
      const arabicDoc = '''
# شروط وأحكام المشتري

**تاريخ النفاذ:** 14 أكتوبر 2026

## 1. التعريفات والتمهيد
يقصد بالكلمات والعبارات التالية المعاني الموضحة أمام كل منها:
- المنصة: تطبيق وموقع FUN MOMENT
- العميل: كل شخص طبيعي ينشئ حساباً
''';
      final html = LegalDocumentFormatter.formatToHtml(arabicDoc);

      expect(html, contains('<h1>شروط وأحكام المشتري</h1>'));
      expect(html, contains('<strong>تاريخ النفاذ:</strong> 14 أكتوبر 2026'));
      expect(html, contains('<h2>1. التعريفات والتمهيد</h2>'));
      expect(html, contains('<ul>'));
      expect(html, contains('<li>المنصة: تطبيق وموقع FUN MOMENT</li>'));
      expect(html, contains('<li>العميل: كل شخص طبيعي ينشئ حساباً</li>'));
      expect(html, isNot(contains('## 1. التعريفات')));
      expect(html, isNot(contains('**تاريخ النفاذ:**')));
    });

    test('F-05: Legacy structured HTML passes through safely without double-wrapping', () {
      const legacyHtml = '<p>Legacy clause one.</p><h3>Section 1</h3><p>Legacy clause two.</p>';
      final html = LegalDocumentFormatter.formatToHtml(legacyHtml);

      expect(html, equals(legacyHtml));
    });

    test('F-06: Malicious scripts and event handlers are strictly sanitized', () {
      const untrusted = '''
# Terms Title
<script>alert("xss")</script>
<p onclick="stealCookies()">Paragraph</p>
<img src="x" onerror="alert(1)">
<a href="javascript:alert('pwned')">Click here</a>
<iframe src="https://evil.com"></iframe>
''';
      final html = LegalDocumentFormatter.formatToHtml(untrusted);

      expect(html, isNot(contains('<script>')));
      expect(html, isNot(contains('alert("xss")')));
      expect(html, isNot(contains('onclick')));
      expect(html, isNot(contains('onerror')));
      expect(html, isNot(contains('javascript:')));
      expect(html, isNot(contains('<iframe')));
      expect(html, contains('<h1>Terms Title</h1>'));
      expect(html, contains('href="#"'));
    });

    test('F-07: Safe external links [text](https://...) convert to anchor tags', () {
      const input = 'Contact support at [FUN MOMENT Support](https://funmoments.unifinitylab.com).';
      final html = LegalDocumentFormatter.formatToHtml(input);

      expect(html, contains('<a href="https://funmoments.unifinitylab.com">FUN MOMENT Support</a>'));
    });

    test('F-08: Empty and null inputs return clean empty string', () {
      expect(LegalDocumentFormatter.formatToHtml(null), equals(''));
      expect(LegalDocumentFormatter.formatToHtml('   '), equals(''));
    });
  });

  group('TermsConditionsModal Rendering Widget Tests', () {
    testWidgets('W-01: Modal renders Markdown headings without raw ## or ** markers', (tester) async {
      final fakeTerms = const PublishedTerms(
        documentId: 1,
        versionId: 5,
        versionNumber: 1,
        role: 'buyer',
        language: 'ar',
        title: 'شروط وأحكام المشتري',
        content: '''
# شروط وأحكام المشتري

**تاريخ النفاذ:** 14 أكتوبر 2026

## 1. الحساب والأهلية
يلتزم العميل بالحفاظ على سرية حسابه.
- بند الحساب الأول
- بند الحساب الثاني
''',
        contentHash: 'hash123',
        publishedAt: '2026-10-14T20:35:57Z',
      );

      final service = _FakeTermsService(fakeTerms);

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AppStringService>(create: (_) => AppStringService()),
            ChangeNotifierProvider<RtlService>(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: TermsConditionsModal(
                role: 'buyer',
                termsService: service,
                langOverride: 'ar',
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Verify HtmlWidget exists and receives formatted semantic HTML without raw Markdown
      final htmlFinder = find.byType(HtmlWidget);
      expect(htmlFinder, findsOneWidget);
      final htmlWidget = tester.widget<HtmlWidget>(htmlFinder);
      expect(htmlWidget.html, contains('<h1>شروط وأحكام المشتري</h1>'));
      expect(htmlWidget.html, contains('<strong>تاريخ النفاذ:</strong> 14 أكتوبر 2026'));
      expect(htmlWidget.html, contains('<h2>1. الحساب والأهلية</h2>'));
      expect(htmlWidget.html, isNot(contains('## 1. الحساب')));
      expect(htmlWidget.html, isNot(contains('**تاريخ النفاذ:**')));

      // Verify the formatted text is rendered in the widget tree via RichText spans
      final richTexts = tester.widgetList<RichText>(find.byType(RichText));
      final allText = richTexts.map((r) => r.text.toPlainText()).join(' ');
      expect(allText, contains('14 أكتوبر 2026'));
      expect(allText, contains('يلتزم العميل بالحفاظ على سرية حسابه'));
    });

    testWidgets('W-02: Modal displays English LTR content correctly', (tester) async {
      final fakeTerms = const PublishedTerms(
        documentId: 2,
        versionId: 3,
        versionNumber: 2,
        role: 'buyer',
        language: 'en',
        title: 'Buyer Terms & Conditions',
        content: '''
# Buyer Terms

**Effective Date:** October 2026

## 1. Eligibility
You must be at least 18 years old.
''',
        contentHash: 'hash456',
        publishedAt: '2026-10-14T20:35:57Z',
      );

      final service = _FakeTermsService(fakeTerms);

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AppStringService>(create: (_) => AppStringService()),
            ChangeNotifierProvider<RtlService>(create: (_) => RtlService()),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: TermsConditionsModal(
                role: 'buyer',
                termsService: service,
                langOverride: 'en',
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      final htmlFinder = find.byType(HtmlWidget);
      expect(htmlFinder, findsOneWidget);
      final htmlWidget = tester.widget<HtmlWidget>(htmlFinder);
      expect(htmlWidget.html, contains('<h1>Buyer Terms</h1>'));
      expect(htmlWidget.html, contains('<strong>Effective Date:</strong> October 2026'));
      expect(htmlWidget.html, contains('<h2>1. Eligibility</h2>'));
      expect(htmlWidget.html, isNot(contains('## 1. Eligibility')));
      expect(htmlWidget.html, isNot(contains('**Effective Date:**')));

      final richTexts = tester.widgetList<RichText>(find.byType(RichText));
      final allText = richTexts.map((r) => r.text.toPlainText()).join(' ');
      expect(allText, contains('You must be at least 18 years old'));
    });
  });
}
