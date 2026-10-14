/**
 * High-fidelity formatter and sanitizer for Terms & Conditions documents.
 * Converts Markdown syntax (#, ##, **, -, 1., [text](url)) into safe semantic HTML
 * and passes through legacy HTML safely without dangerous tags or event handlers.
 */

export function sanitizeLegalHtml(html: string): string {
  if (!html || !html.trim()) return '';

  let cleaned = html;

  // 1. Remove dangerous executable container tags and contents
  cleaned = cleaned.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
  cleaned = cleaned.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '');
  cleaned = cleaned.replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '');
  cleaned = cleaned.replace(/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi, '');
  cleaned = cleaned.replace(/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/gi, '');
  cleaned = cleaned.replace(/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/gi, '');

  // 2. Strip all inline JavaScript event handlers (e.g., onload, onclick, onerror)
  cleaned = cleaned.replace(/\s+on[a-zA-Z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');

  // 3. Disallow javascript: and vbscript: URIs in attributes
  cleaned = cleaned.replace(/href\s*=\s*["']\s*(javascript|vbscript|data):[^"']*["']/gi, 'href="#"');

  return cleaned;
}

export function formatLegalDocumentToHtml(rawContent: string | null | undefined): string {
  if (!rawContent || !rawContent.trim()) {
    return '';
  }

  const content = sanitizeLegalHtml(rawContent.trim());

  // Check if content is already structured HTML without raw Markdown markers
  const hasHtmlBlocks = /<(p|h[1-6]|ul|ol|table|div|blockquote)[^>]*>/i.test(content);
  const hasMarkdownMarkers = /(^|\n)\s*(#{1,6}\s+|[\-*]\s+|\d+[\.)]\s+)|\*\*[^*]+\*\*/.test(content);

  if (hasHtmlBlocks && !hasMarkdownMarkers) {
    return content;
  }

  // Convert Markdown / plain text to semantic HTML
  const lines = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
  const result: string[] = [];

  let inUl = false;
  let inOl = false;
  let paragraphLines: string[] = [];

  const flushParagraph = () => {
    if (paragraphLines.length === 0) return;
    const formatted = paragraphLines.map((l) => formatInline(l.trim())).join('<br/>\n');
    if (formatted) {
      result.push(`<p class="mb-3 text-slate-700 leading-relaxed">${formatted}</p>`);
    }
    paragraphLines = [];
  };

  const closeLists = () => {
    if (inUl) {
      result.push('</ul>');
      inUl = false;
    }
    if (inOl) {
      result.push('</ol>');
      inOl = false;
    }
  };

  const headingRegex = /^(\#{1,6})\s+(.+)$/;
  const bulletRegex = /^[\-*+]\s+(.+)$/;
  const numberedRegex = /^(\d+)[\.)]\s+(.+)$/;
  const htmlBlockRegex = /^<(h[1-6]|p|div|ul|ol|table|hr|blockquote)[^>]*>/i;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const trimmed = line.trim();

    if (!trimmed) {
      flushParagraph();
      closeLists();
      continue;
    }

    // Heading match
    const headingMatch = trimmed.match(headingRegex);
    if (headingMatch) {
      flushParagraph();
      closeLists();
      const level = headingMatch[1].length;
      const text = formatInline(headingMatch[2]);
      if (level === 1) {
        result.push(`<h1 class="text-xl font-extrabold text-cyan-600 mt-5 mb-2.5 pb-1 border-b border-slate-100">${text}</h1>`);
      } else if (level === 2) {
        result.push(`<h2 class="text-base font-bold text-slate-800 mt-4 mb-2">${text}</h2>`);
      } else if (level === 3) {
        result.push(`<h3 class="text-sm font-bold text-slate-700 mt-3 mb-1.5">${text}</h3>`);
      } else {
        result.push(`<h4 class="text-xs font-bold text-slate-600 mt-2.5 mb-1">${text}</h4>`);
      }
      continue;
    }

    // Bullet list match
    const bulletMatch = trimmed.match(bulletRegex);
    if (bulletMatch) {
      flushParagraph();
      if (inOl) {
        result.push('</ol>');
        inOl = false;
      }
      if (!inUl) {
        result.push('<ul class="list-disc ps-5 space-y-1 mb-3 text-slate-700">');
        inUl = true;
      }
      const itemText = formatInline(bulletMatch[1]);
      result.push(`  <li class="leading-relaxed">${itemText}</li>`);
      continue;
    }

    // Numbered list match
    const numberedMatch = trimmed.match(numberedRegex);
    if (numberedMatch) {
      flushParagraph();
      if (inUl) {
        result.push('</ul>');
        inUl = false;
      }
      if (!inOl) {
        result.push('<ol class="list-decimal ps-5 space-y-1 mb-3 text-slate-700">');
        inOl = true;
      }
      const itemText = formatInline(numberedMatch[2]);
      result.push(`  <li class="leading-relaxed">${itemText}</li>`);
      continue;
    }

    // Existing block HTML tag
    if (htmlBlockRegex.test(trimmed)) {
      flushParagraph();
      closeLists();
      result.push(trimmed);
      continue;
    }

    // Regular text line
    closeLists();
    paragraphLines.push(trimmed);
  }

  flushParagraph();
  closeLists();

  return result.join('\n');
}

function formatInline(text: string): string {
  let out = text;

  // Bold: **text** or __text__
  out = out.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
  out = out.replace(/__([^_]+)__/g, '<strong class="font-bold text-slate-900">$1</strong>');

  // Italic: *text* or _text_
  out = out.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em class="italic">$1</em>');
  out = out.replace(/(?<!_)_([^_]+)_(?!_)/g, '<em class="italic">$1</em>');

  // Safe links: [label](url)
  out = out.replace(
    /\[([^\]]+)\]\(((?:https?:\/\/|mailto:|tel:)[^\s)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-cyan-600 underline font-medium hover:text-cyan-700">$1</a>'
  );

  return out;
}
