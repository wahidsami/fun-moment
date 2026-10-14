/// Service that converts raw legal document text (Markdown, plain text, or HTML)
/// into safe, semantic HTML ready for high-fidelity rendering in Flutter and web.
class LegalDocumentFormatter {
  /// Transforms raw legal terms content (Markdown, legacy HTML, or plain text)
  /// into sanitized, semantic HTML ready for [HtmlWidget].
  static String formatToHtml(String? rawContent) {
    if (rawContent == null || rawContent.trim().isEmpty) {
      return '';
    }

    String content = rawContent.trim();

    // 1. Sanitize untrusted markup and scripts first
    content = _sanitizeUntrustedMarkup(content);

    // 2. Check if the content is already full HTML without Markdown markers
    final hasHtmlBlocks = RegExp(r'<(p|h[1-6]|ul|ol|table|div|blockquote)[^>]*>', caseSensitive: false).hasMatch(content);
    final hasMarkdownMarkers = RegExp(r'(^|\n)\s*(#{1,6}\s+|[\-\*]\s+|\d+[\.\)]\s+)|\*\*[^\*]+\*\*').hasMatch(content);

    if (hasHtmlBlocks && !hasMarkdownMarkers) {
      // Pure legacy HTML, already structured
      return content;
    }

    // 3. Convert Markdown / plain text to semantic HTML
    return _markdownToHtml(content);
  }

  /// Strips dangerous tags, event handlers, and unsafe link protocols.
  static String _sanitizeUntrustedMarkup(String text) {
    var out = text;

    // Remove script tags and contents
    out = out.replaceAll(RegExp(r'<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>', caseSensitive: false), '');
    // Remove style tags and contents
    out = out.replaceAll(RegExp(r'<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>', caseSensitive: false), '');
    // Remove iframe, object, embed, form tags
    out = out.replaceAll(RegExp(r'<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>', caseSensitive: false), '');
    out = out.replaceAll(RegExp(r'<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>', caseSensitive: false), '');
    out = out.replaceAll(RegExp(r'<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>', caseSensitive: false), '');
    out = out.replaceAll(RegExp(r'<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>', caseSensitive: false), '');

    // Strip inline event handlers: e.g. onerror=..., onclick=...
    out = out.replaceAll(RegExp(r'\s+on[a-zA-Z]+\s*=\s*("[^"]*"|\x27[^\x27]*\x27|[^\s>]+)', caseSensitive: false), '');

    // Neutralize dangerous javascript: or data: URIs in href
    out = out.replaceAll(RegExp(r'href\s*=\s*["\x27]\s*(javascript|vbscript|data):[^"\x27]*["\x27]', caseSensitive: false), 'href="#"');

    return out;
  }

  /// Parses Markdown syntax into semantic HTML.
  static String _markdownToHtml(String markdown) {
    final lines = markdown.replaceAll('\r\n', '\n').replaceAll('\r', '\n').split('\n');
    final sb = StringBuffer();

    bool inUl = false;
    bool inOl = false;
    final paragraphLines = <String>[];

    void flushParagraph() {
      if (paragraphLines.isEmpty) return;
      final text = paragraphLines.map((l) => _formatInline(l.trim())).join('<br/>\n');
      if (text.isNotEmpty) {
        sb.writeln('<p>$text</p>');
      }
      paragraphLines.clear();
    }

    void closeLists() {
      if (inUl) {
        sb.writeln('</ul>');
        inUl = false;
      }
      if (inOl) {
        sb.writeln('</ol>');
        inOl = false;
      }
    }

    final headingRegex = RegExp(r'^(\#{1,6})\s+(.+)$');
    final bulletRegex = RegExp(r'^[\-\*\+]\s+(.+)$');
    final numberedRegex = RegExp(r'^(\d+)[\.\)]\s+(.+)$');
    final htmlBlockRegex = RegExp(r'^<(h[1-6]|p|div|ul|ol|table|hr|blockquote)[^>]*>', caseSensitive: false);

    for (var i = 0; i < lines.length; i++) {
      final line = lines[i];
      final trimmed = line.trim();

      if (trimmed.isEmpty) {
        flushParagraph();
        closeLists();
        continue;
      }

      // Check heading
      final headingMatch = headingRegex.firstMatch(trimmed);
      if (headingMatch != null) {
        flushParagraph();
        closeLists();
        final level = headingMatch.group(1)!.length;
        final hTag = level <= 3 ? 'h$level' : 'h4';
        final headingText = _formatInline(headingMatch.group(2)!);
        sb.writeln('<$hTag>$headingText</$hTag>');
        continue;
      }

      // Check bullet list item
      final bulletMatch = bulletRegex.firstMatch(trimmed);
      if (bulletMatch != null) {
        flushParagraph();
        if (inOl) {
          sb.writeln('</ol>');
          inOl = false;
        }
        if (!inUl) {
          sb.writeln('<ul>');
          inUl = true;
        }
        final itemText = _formatInline(bulletMatch.group(1)!);
        sb.writeln('  <li>$itemText</li>');
        continue;
      }

      // Check numbered list item
      final numberedMatch = numberedRegex.firstMatch(trimmed);
      if (numberedMatch != null) {
        flushParagraph();
        if (inUl) {
          sb.writeln('</ul>');
          inUl = false;
        }
        if (!inOl) {
          sb.writeln('<ol>');
          inOl = true;
        }
        final itemText = _formatInline(numberedMatch.group(2)!);
        sb.writeln('  <li>$itemText</li>');
        continue;
      }

      // Check if existing block HTML tag
      if (htmlBlockRegex.hasMatch(trimmed)) {
        flushParagraph();
        closeLists();
        sb.writeln(trimmed);
        continue;
      }

      // Regular text -> accumulates into paragraph
      closeLists();
      paragraphLines.add(trimmed);
    }

    flushParagraph();
    closeLists();

    return sb.toString().trim();
  }

  /// Parses inline bold, italic, and safe link patterns.
  static String _formatInline(String text) {
    var out = text;

    // Bold: **text** or __text__
    out = out.replaceAllMapped(RegExp(r'\*\*([^*]+)\*\*'), (m) => '<strong>${m[1]}</strong>');
    out = out.replaceAllMapped(RegExp(r'__([^_]+)__'), (m) => '<strong>${m[1]}</strong>');

    // Italic: *text* or _text_
    out = out.replaceAllMapped(RegExp(r'(?<!\*)\*([^*]+)\*(?!\*)'), (m) => '<em>${m[1]}</em>');
    out = out.replaceAllMapped(RegExp(r'(?<!_)_([^_]+)_(?!_)'), (m) => '<em>${m[1]}</em>');

    // Safe links: [label](url)
    out = out.replaceAllMapped(
      RegExp(r'\[([^\]]+)\]\(((?:https?:\/\/|mailto:|tel:)[^\s\)]+)\)'),
      (m) => '<a href="${m[2]}">${m[1]}</a>',
    );

    return out;
  }
}
