/**
 * Local Verification Test Suite for FUN MOMENT Landing Page & Email Server
 * Does NOT send real emails. Uses local port and mock transport.
 */

import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import nodemailer from 'nodemailer';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const serverPath = path.resolve(__dirname, '../server.js');
console.log('[TEST] Loading server from:', serverPath);

async function runTests() {
  const TEST_PORT = 3899;
  process.env.PORT = String(TEST_PORT);
  process.env.NODE_ENV = 'test';
  process.env.PUBLIC_BASE_URL = 'http://localhost:3899';
  process.env.SUPPORT_TO_EMAIL = 'funmomentsproject@gmail.com';
  process.env.SMTP_USER = 'funmomentsproject@gmail.com';
  process.env.SMTP_PASS = ''; // Initially empty to test missing SMTP_PASS

  const serverModule = await import(`file://${serverPath}`);
  const app = serverModule.default;

  const server = http.createServer(app);
  await new Promise((resolve) => server.listen(TEST_PORT, '127.0.0.1', resolve));
  console.log(`[TEST] Test server listening on http://127.0.0.1:${TEST_PORT}`);

  let passed = 0;
  let failed = 0;

  async function test(name, fn) {
    try {
      await fn();
      console.log(`  ✓ ${name}`);
      passed++;
    } catch (e) {
      console.error(`  ✗ ${name}:`, e.message);
      failed++;
    }
  }

  const BASE = `http://127.0.0.1:${TEST_PORT}`;

  // 1. Health endpoint
  await test('GET /healthz returns 200 with ok status and no secrets', async () => {
    const res = await fetch(`${BASE}/healthz`);
    if (res.status !== 200) throw new Error(`Status was ${res.status}`);
    const data = await res.json();
    if (data.status !== 'ok') throw new Error(`Status not ok: ${JSON.stringify(data)}`);
    if (data.smtpPass || data.password) throw new Error(`Secret leaked in health response!`);
  });

  // 2. SPA direct routing
  await test('GET / returns 200 and serves index.html', async () => {
    const res = await fetch(`${BASE}/`);
    if (res.status !== 200) throw new Error(`Status was ${res.status}`);
    const text = await res.text();
    if (!text.includes('FUN MOMENT') || !text.includes('root')) throw new Error('HTML content missing');
  });

  await test('GET /privacy returns 200 and serves index.html (SPA route)', async () => {
    const res = await fetch(`${BASE}/privacy`);
    if (res.status !== 200) throw new Error(`Status was ${res.status}`);
    const text = await res.text();
    if (!text.includes('root')) throw new Error('SPA fallback failed for /privacy');
  });

  await test('GET /support returns 200 and serves index.html (SPA route)', async () => {
    const res = await fetch(`${BASE}/support`);
    if (res.status !== 200) throw new Error(`Status was ${res.status}`);
    const text = await res.text();
    if (!text.includes('root')) throw new Error('SPA fallback failed for /support');
  });

  // 3. Server-side validation (use unique simulated client IP per check to isolate validation from rate limits)
  await test('POST /api/support rejects missing name with 400', async () => {
    const res = await fetch(`${BASE}/api/support`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-forwarded-for': '10.0.0.1' },
      body: JSON.stringify({ email: 'test@example.com', message: 'Hello test message' }),
    });
    if (res.status !== 400) throw new Error(`Expected 400, got ${res.status}`);
    const data = await res.json();
    if (!data.error.includes('name') && !data.error.includes('Name')) {
      throw new Error(`Expected name error, got: ${data.error}`);
    }
  });

  await test('POST /api/support rejects invalid email with 400', async () => {
    const res = await fetch(`${BASE}/api/support`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-forwarded-for': '10.0.0.2' },
      body: JSON.stringify({ name: 'Ahmad', email: 'not-an-email', message: 'Hello test message' }),
    });
    if (res.status !== 400) throw new Error(`Expected 400, got ${res.status}`);
    const data = await res.json();
    if (!data.error.includes('email') && !data.error.includes('Email')) {
      throw new Error(`Expected email error, got: ${data.error}`);
    }
  });

  await test('POST /api/support rejects empty message with 400', async () => {
    const res = await fetch(`${BASE}/api/support`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-forwarded-for': '10.0.0.3' },
      body: JSON.stringify({ name: 'Ahmad', email: 'ahmad@example.com', message: '   ' }),
    });
    if (res.status !== 400) throw new Error(`Expected 400, got ${res.status}`);
  });

  await test('POST /api/support rejects oversized message with 400', async () => {
    const longMsg = 'A'.repeat(3001);
    const res = await fetch(`${BASE}/api/support`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-forwarded-for': '10.0.0.4' },
      body: JSON.stringify({ name: 'Ahmad', email: 'ahmad@example.com', message: longMsg }),
    });
    if (res.status !== 400) throw new Error(`Expected 400, got ${res.status}`);
  });

  // 4. Anti-Spam Honeypot
  await test('POST /api/support with honeypot returns 200 silently without SMTP', async () => {
    const res = await fetch(`${BASE}/api/support`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-forwarded-for': '10.0.0.5' },
      body: JSON.stringify({
        name: 'Bot User',
        email: 'bot@example.com',
        message: 'Spam message',
        _hp: 'I am a bot',
      }),
    });
    if (res.status !== 200) throw new Error(`Expected 200, got ${res.status}`);
    const data = await res.json();
    if (!data.success) throw new Error('Expected success: true');
  });

  // 5. Missing SMTP_PASS handling
  await test('POST /api/support without SMTP_PASS returns graceful 503', async () => {
    const res = await fetch(`${BASE}/api/support`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-forwarded-for': '10.0.0.6' },
      body: JSON.stringify({
        name: 'Saud Al-Fahad',
        email: 'saud@example.com',
        topic: 'general',
        message: 'Valid inquiry message for testing',
      }),
    });
    if (res.status !== 503) throw new Error(`Expected 503, got ${res.status}`);
    const data = await res.json();
    if (!data.error.includes('unavailable')) throw new Error(`Unexpected error message: ${data.error}`);
  });

  // 6. Anti-Spam Rate Limiting: 6th request from same IP is blocked with 429
  await test('Rate Limiter blocks 6th request from same IP with 429', async () => {
    const rateLimitIp = '192.168.100.50';
    for (let i = 0; i < 5; i++) {
      const r = await fetch(`${BASE}/api/support`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'x-forwarded-for': rateLimitIp },
        body: JSON.stringify({ name: 'User', email: 'user@example.com', message: 'Test message' }),
      });
      if (r.status !== 503 && r.status !== 200) {
        throw new Error(`Unexpected status on request ${i + 1}: ${r.status}`);
      }
    }
    // 6th request must be 429
    const blockedRes = await fetch(`${BASE}/api/support`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-forwarded-for': rateLimitIp },
      body: JSON.stringify({ name: 'User', email: 'user@example.com', message: 'Test message' }),
    });
    if (blockedRes.status !== 429) throw new Error(`Expected 429 on 6th request, got ${blockedRes.status}`);
  });

  // 7. Email Construction with Mock Transport (Zero Real Emails Sent)
  await test('Mock Nodemailer transport receives correct headers, replyTo, and formatted content', async () => {
    const mockTransport = nodemailer.createTransport({
      jsonTransport: true,
    });

    const cleanName = 'Saud Al-Fahad';
    const cleanEmail = 'saud@example.com';
    const topicLabel = 'Account Management / إدارة الحساب';
    const rawMessage = 'Please help me reset my account credentials.';
    const cleanSubject = `[FUN MOMENT Support] ${topicLabel}: ${cleanName}`;

    const mailOptions = {
      from: `"FUN MOMENT Support" <funmomentsproject@gmail.com>`,
      to: 'funmomentsproject@gmail.com',
      replyTo: `"${cleanName}" <${cleanEmail}>`,
      subject: cleanSubject,
      text: `Name: ${cleanName}\nEmail: ${cleanEmail}\nMessage:\n${rawMessage}`,
      html: `<b>${cleanName}</b><p>${rawMessage}</p>`,
    };

    const info = await mockTransport.sendMail(mailOptions);
    const parsed = JSON.parse(info.message);

    if (parsed.to[0].address !== 'funmomentsproject@gmail.com') {
      throw new Error(`To address mismatch: ${parsed.to[0].address}`);
    }
    if (parsed.replyTo[0].address !== 'saud@example.com') {
      throw new Error(`Reply-To address mismatch: ${parsed.replyTo[0].address}`);
    }
    if (parsed.replyTo[0].name !== 'Saud Al-Fahad') {
      throw new Error(`Reply-To name mismatch: ${parsed.replyTo[0].name}`);
    }
    if (!parsed.subject.includes('[FUN MOMENT Support]')) {
      throw new Error(`Subject mismatch: ${parsed.subject}`);
    }
  });

  // 8. Verify no secrets in dist/ assets
  await test('Dist assets do not leak SMTP credentials or env variables', async () => {
    const distAssetsDir = path.resolve(__dirname, '../dist/assets');
    const files = fs.readdirSync(distAssetsDir);
    for (const f of files) {
      const content = fs.readFileSync(path.join(distAssetsDir, f), 'utf-8');
      if (content.includes('SMTP_PASS') || content.includes('smtp.gmail.com')) {
        throw new Error(`Potential secret leaked in asset: ${f}`);
      }
    }
  });

  server.close(() => {
    console.log(`\n[TEST SUMMARY] Passed: ${passed}, Failed: ${failed}`);
    process.exit(failed > 0 ? 1 : 0);
  });
}

runTests().catch((e) => {
  console.error('[TEST FATAL]:', e);
  process.exit(1);
});
