/**
 * FUN MOMENT — Production Web & Support Email Server
 * Serves compiled React SPA and provides secure POST /api/support endpoint.
 */

import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import nodemailer from 'nodemailer';
import dotenv from 'dotenv';
import fs from 'fs';

// Load environment variables from .env if present
dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();

// Configuration
const PORT = parseInt(process.env.PORT || '3000', 10);
const HOST = '0.0.0.0';
const NODE_ENV = process.env.NODE_ENV || 'production';
const PUBLIC_BASE_URL = process.env.PUBLIC_BASE_URL || 'https://funmoments.unifinitylab.com';
const SUPPORT_TO_EMAIL = process.env.SUPPORT_TO_EMAIL || 'funmomentsproject@gmail.com';

const SMTP_HOST = process.env.SMTP_HOST || 'smtp.gmail.com';
const SMTP_PORT = parseInt(process.env.SMTP_PORT || '587', 10);
const SMTP_SECURE = process.env.SMTP_SECURE === 'true';
const SMTP_USER = process.env.SMTP_USER || 'funmomentsproject@gmail.com';
const SMTP_PASS = process.env.SMTP_PASS || '';

// Security: Enforce small payload size limit (max 50KB)
app.use(express.json({ limit: '50kb' }));

// Security headers
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  next();
});

// In-memory rate limiter: max 5 submissions per 15 minutes per IP
const rateLimitMap = new Map();
const RATE_LIMIT_WINDOW_MS = 15 * 60 * 1000;
const MAX_REQUESTS_PER_WINDOW = 5;

// Periodically purge stale rate limit records
setInterval(() => {
  const now = Date.now();
  for (const [ip, record] of rateLimitMap.entries()) {
    if (now - record.startTime > RATE_LIMIT_WINDOW_MS) {
      rateLimitMap.delete(ip);
    }
  }
}, 5 * 60 * 1000).unref();

function checkRateLimit(ip) {
  const now = Date.now();
  const record = rateLimitMap.get(ip);
  if (!record || now - record.startTime > RATE_LIMIT_WINDOW_MS) {
    rateLimitMap.set(ip, { startTime: now, count: 1 });
    return true;
  }
  if (record.count >= MAX_REQUESTS_PER_WINDOW) {
    return false;
  }
  record.count += 1;
  return true;
}

// Utility: Strip carriage return and newline characters to prevent email header injection
function sanitizeHeader(str) {
  if (typeof str !== 'string') return '';
  return str.replace(/[\r\n]/g, ' ').trim();
}

// Utility: Escape HTML special characters for safe email rendering
function escapeHtml(str) {
  if (typeof str !== 'string') return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// Allowed topics whitelist
const ALLOWED_TOPICS = {
  general: 'General Inquiry / استفسار عام',
  account: 'Account Management / إدارة الحساب',
  technical: 'Technical Support / دعم فني',
  experiences: 'Live Experiences / تجارب ولحظات',
  deletion: 'Account Deletion / طلب حذف الحساب',
};

// -------------------------------------------------------------
// Health Check Endpoint (GET /healthz)
// Must not expose SMTP passwords or configuration secrets.
// -------------------------------------------------------------
app.get('/healthz', (req, res) => {
  res.status(200).json({
    status: 'ok',
    service: 'fun-moment-landingpage',
    timestamp: new Date().toISOString(),
    smtpConfigured: Boolean(SMTP_USER && SMTP_PASS),
  });
});

// -------------------------------------------------------------
// Support Email Endpoint (POST /api/support)
// -------------------------------------------------------------
app.post('/api/support', async (req, res) => {
  try {
    // 1. Cross-Origin Check (prevent cross-origin request forgery)
    const origin = req.headers.origin;
    if (origin) {
      try {
        const originUrl = new URL(origin);
        const configuredUrl = new URL(PUBLIC_BASE_URL);
        const isLocal = originUrl.hostname === 'localhost' || originUrl.hostname === '127.0.0.1';
        const isMatch = originUrl.hostname === configuredUrl.hostname;
        if (!isLocal && !isMatch) {
          return res.status(403).json({
            success: false,
            error: 'Forbidden origin.',
          });
        }
      } catch (e) {
        return res.status(403).json({
          success: false,
          error: 'Invalid origin header.',
        });
      }
    }

    // 2. Anti-Spam: Rate Limiting
    const clientIp =
      req.headers['x-forwarded-for']?.toString().split(',')[0].trim() ||
      req.socket.remoteAddress ||
      'unknown';

    if (!checkRateLimit(clientIp)) {
      return res.status(429).json({
        success: false,
        error: 'Too many requests. Please wait a few minutes before submitting another message.',
        errorAr: 'تم إرسال عدد كبير من الطلبات. يرجى الانتظار بضع دقائق والمحاولة مرة أخرى.',
      });
    }

    // 3. Anti-Spam: Honeypot check
    // If the hidden honeypot field is filled, silently simulate success without sending email
    if (req.body._hp) {
      console.warn(`[Anti-Spam] Honeypot triggered from IP: ${clientIp}`);
      return res.status(200).json({
        success: true,
        message: 'Support request received.',
      });
    }

    // 4. Server-Side Input Validation
    const rawName = req.body.name;
    const rawEmail = req.body.email;
    const rawPhone = req.body.phone;
    const rawTopic = req.body.topic;
    const rawMessage = req.body.message;

    // Name validation
    if (!rawName || typeof rawName !== 'string' || rawName.trim().length === 0) {
      return res.status(400).json({
        success: false,
        error: 'Full name is required.',
        errorAr: 'الاسم الكامل مطلوب.',
      });
    }
    if (rawName.trim().length > 100) {
      return res.status(400).json({
        success: false,
        error: 'Name exceeds maximum length of 100 characters.',
        errorAr: 'الاسم يتجاوز الحد الأقصى المسموح به.',
      });
    }

    // Email validation
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
    if (!rawEmail || typeof rawEmail !== 'string' || !emailRegex.test(rawEmail.trim())) {
      return res.status(400).json({
        success: false,
        error: 'A valid email address is required.',
        errorAr: 'يرجى إدخال بريد إلكتروني صحيح.',
      });
    }
    if (rawEmail.trim().length > 150) {
      return res.status(400).json({
        success: false,
        error: 'Email exceeds maximum length of 150 characters.',
        errorAr: 'البريد الإلكتروني يتجاوز الحد الأقصى.',
      });
    }

    // Phone validation (optional)
    let cleanPhone = '';
    if (rawPhone && typeof rawPhone === 'string' && rawPhone.trim().length > 0) {
      if (rawPhone.trim().length > 30) {
        return res.status(400).json({
          success: false,
          error: 'Phone number exceeds maximum length of 30 characters.',
          errorAr: 'رقم الهاتف يتجاوز الحد الأقصى.',
        });
      }
      cleanPhone = sanitizeHeader(rawPhone);
    }

    // Topic validation
    const selectedTopicKey = (rawTopic || 'general').toString().trim().toLowerCase();
    const topicLabel = ALLOWED_TOPICS[selectedTopicKey] || ALLOWED_TOPICS.general;

    // Message validation
    if (!rawMessage || typeof rawMessage !== 'string' || rawMessage.trim().length === 0) {
      return res.status(400).json({
        success: false,
        error: 'Message content is required.',
        errorAr: 'نص الرسالة مطلوب.',
      });
    }
    if (rawMessage.trim().length < 5) {
      return res.status(400).json({
        success: false,
        error: 'Message must be at least 5 characters.',
        errorAr: 'يجب أن تحتوي الرسالة على 5 أحرف على الأقل.',
      });
    }
    if (rawMessage.trim().length > 3000) {
      return res.status(400).json({
        success: false,
        error: 'Message exceeds maximum length of 3,000 characters.',
        errorAr: 'الرسالة تتجاوز الحد الأقصى المسموح به (3000 حرف).',
      });
    }

    // Sanitize headers to prevent header injection
    const cleanName = sanitizeHeader(rawName);
    const cleanEmail = sanitizeHeader(rawEmail);
    const cleanSubject = `[FUN MOMENT Support] ${sanitizeHeader(topicLabel)}: ${cleanName}`;

    // 5. Check SMTP Configuration
    if (!SMTP_PASS) {
      console.error('[SMTP Config] SMTP_PASS is missing in server environment.');
      return res.status(503).json({
        success: false,
        error: 'Support mail service is currently unavailable. Please email funmomentsproject@gmail.com directly.',
        errorAr: 'خدمة البريد الإلكتروني غير متوفرة حالياً. يمكنك مراسلتنا مباشرة على funmomentsproject@gmail.com.',
      });
    }

    // 6. Create Nodemailer Transporter
    const transporter = nodemailer.createTransport({
      host: SMTP_HOST,
      port: SMTP_PORT,
      secure: SMTP_SECURE,
      auth: {
        user: SMTP_USER,
        pass: SMTP_PASS,
      },
      connectionTimeout: 10000,
      greetingTimeout: 10000,
      socketTimeout: 15000,
    });

    // 7. Compose Email Content
    const submissionDate = new Date().toUTCString();

    const plainText = `
FUN MOMENT — New Support Inquiry
========================================
Date:      ${submissionDate}
Topic:     ${topicLabel}
Name:      ${cleanName}
Email:     ${cleanEmail}
Phone:     ${cleanPhone || 'Not provided'}
Client IP: ${clientIp}

Message:
----------------------------------------
${rawMessage.trim()}
----------------------------------------

This message was sent via the official FUN MOMENT landing page:
${PUBLIC_BASE_URL}
Reply to this email to respond directly to ${cleanName} (${cleanEmail}).
`.trim();

    const htmlBody = `
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #050814; color: #E2E8F0; margin: 0; padding: 24px; }
  .card { max-width: 600px; margin: 0 auto; background-color: #090E24; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; overflow: hidden; }
  .header { background: linear-gradient(135deg, #090E24 0%, #11183E 100%); padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.08); }
  .header h1 { margin: 0; font-size: 20px; color: #FFFFFF; font-weight: 800; letter-spacing: -0.5px; }
  .header p { margin: 6px 0 0 0; font-size: 13px; color: #00E5FF; }
  .content { padding: 24px; }
  .field-row { margin-bottom: 16px; }
  .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94A3B8; font-weight: 600; margin-bottom: 4px; }
  .value { font-size: 14px; color: #FFFFFF; font-weight: 500; }
  .message-box { background-color: #050814; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; margin-top: 16px; font-size: 14px; line-height: 1.6; color: #F1F5F9; white-space: pre-wrap; word-break: break-word; }
  .footer { padding: 16px 24px; background-color: #050814; border-top: 1px solid rgba(255,255,255,0.05); font-size: 11px; color: #64748B; text-align: center; }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; background-color: rgba(255,168,0,0.15); color: #FFA800; font-size: 11px; font-weight: 700; margin-bottom: 12px; }
</style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>FUN MOMENT Support Inquiry</h1>
      <p>Received via ${escapeHtml(PUBLIC_BASE_URL)}</p>
    </div>
    <div class="content">
      <div class="badge">${escapeHtml(topicLabel)}</div>
      <div class="field-row">
        <div class="label">Submitter Name / الاسم</div>
        <div class="value">${escapeHtml(cleanName)}</div>
      </div>
      <div class="field-row">
        <div class="label">Email Address / البريد الإلكتروني</div>
        <div class="value"><a href="mailto:${escapeHtml(cleanEmail)}" style="color: #00E5FF; text-decoration: none;">${escapeHtml(cleanEmail)}</a></div>
      </div>
      <div class="field-row">
        <div class="label">Phone / رقم الهاتف</div>
        <div class="value">${escapeHtml(cleanPhone || 'Not provided / غير مزود')}</div>
      </div>
      <div class="field-row">
        <div class="label">Date & Time / التاريخ</div>
        <div class="value">${escapeHtml(submissionDate)}</div>
      </div>
      <div class="field-row" style="margin-top: 20px;">
        <div class="label">Message Content / نص الرسالة</div>
        <div class="message-box">${escapeHtml(rawMessage.trim())}</div>
      </div>
    </div>
    <div class="footer">
      FUN MOMENT Support System • Reply directly to this email to respond to the user.
    </div>
  </div>
</body>
</html>
`.trim();

    // 8. Dispatch Mail via SMTP
    const mailOptions = {
      from: `"FUN MOMENT Support" <${SMTP_USER}>`,
      to: SUPPORT_TO_EMAIL,
      replyTo: `"${cleanName}" <${cleanEmail}>`,
      subject: cleanSubject,
      text: plainText,
      html: htmlBody,
    };

    const info = await transporter.sendMail(mailOptions);
    console.log(`[SMTP Success] Message sent to ${SUPPORT_TO_EMAIL}: ${info.messageId}`);

    return res.status(200).json({
      success: true,
      message: 'Support request sent successfully.',
      messageAr: 'تم إرسال رسالتك بنجاح وسيقوم فريق الدعم بالتواصل معك قريباً.',
    });
  } catch (err) {
    // Graceful error logging without exposing credentials or internal traces to user
    console.error('[SMTP Error] Failed to send support message:', err.message || err);
    return res.status(500).json({
      success: false,
      error: 'Failed to deliver support email. Please try again later or email funmomentsproject@gmail.com.',
      errorAr: 'تعذر إرسال الرسالة عبر الخادم. يرجى المحاولة لاحقاً أو مراسلتنا مباشرة على funmomentsproject@gmail.com.',
    });
  }
});

// -------------------------------------------------------------
// Serve Static Vite Frontend & SPA Fallback Routing
// -------------------------------------------------------------
const distPath = path.join(__dirname, 'dist');

if (fs.existsSync(distPath)) {
  // Serve static assets with caching
  app.use(
    express.static(distPath, {
      maxAge: '1d',
      index: false,
    })
  );

  // SPA fallback for all non-API GET routes (including /, /privacy, /support)
  app.get('*', (req, res) => {
    // Never intercept API routes with SPA fallback
    if (req.path.startsWith('/api/') || req.path === '/healthz') {
      return res.status(404).json({ error: 'Endpoint not found' });
    }
    res.sendFile(path.join(distPath, 'index.html'));
  });
} else {
  // Helpful warning if dist directory hasn't been built yet
  app.get('*', (req, res) => {
    if (req.path.startsWith('/api/') || req.path === '/healthz') {
      return res.status(404).json({ error: 'Endpoint not found' });
    }
    res.status(503).send('Application frontend is building or dist/ not found. Run "npm run build".');
  });
}

// Start Server (only when not running automated tests)
if (process.env.NODE_ENV !== 'test') {
  app.listen(PORT, HOST, () => {
    console.log(`[FUN MOMENT Landing] Server listening on http://${HOST}:${PORT} (${NODE_ENV})`);
  });
}

export default app;
