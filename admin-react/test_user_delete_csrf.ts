/**
 * Automated Regression Test Suite:
 * Administrative User Deletion CSRF Token & Protocol Verification
 *
 * Verifies:
 * 1. deleteUser() uses fetchWithCsrf() helper.
 * 2. Plaintext session token is sent via X-CSRF-TOKEN header.
 * 3. Encrypted cookie value is sent via X-XSRF-TOKEN header.
 * 4. Encrypted cookie is NEVER sent via X-CSRF-TOKEN.
 * 5. Automatic retry on HTTP 419 with fresh token from /admin-home/csrf-token.
 * 6. Controlled error when token remains invalid/expired and refresh fails.
 * 7. Credentials, JSON body, language parameters, and error reporting preserved.
 */

import { LaravelAPI, setCsrfToken, getXsrfCookie } from './src/api';

async function runTests() {
  console.log('====================================================================');
  console.log('RUNNING FRONTEND TEST SUITE: Admin User Deletion CSRF & Protocol');
  console.log('====================================================================\n');

  let passed = 0;
  let failed = 0;

  function assert(condition: boolean, name: string) {
    if (condition) {
      console.log(`  [PASS] ${name}`);
      passed++;
    } else {
      console.error(`  [FAIL] ${name}`);
      failed++;
    }
  }

  const originalFetch = globalThis.fetch;

  try {
    // -------------------------------------------------------------------------
    // TEST 1: deleteUser() attaches X-CSRF-TOKEN (plaintext) and preserves params
    // -------------------------------------------------------------------------
    {
      const capturedRequests: Array<{ url: string; options?: RequestInit }> = [];
      setCsrfToken('plaintext-session-token-12345');

      globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : input.toString();
        capturedRequests.push({ url, options: init });

        if (url.startsWith('/admin-home/frontend-users/42/delete')) {
          return new Response(
            JSON.stringify({
              status: 'success',
              message: 'User deleted successfully',
            }),
            { status: 200, headers: { 'Content-Type': 'application/json' } }
          );
        }

        return new Response('Not Found', { status: 404 });
      };

      const result = await LaravelAPI.deleteUser(42, 'target@example.com', 'ar');

      assert(result?.status === 'success', 'T1: deleteUser returns successful API response');
      assert(capturedRequests.length === 1, 'T1: Exactly one HTTP request dispatched with valid token');
      assert(
        capturedRequests[0].url === '/admin-home/frontend-users/42/delete?lang=ar',
        'T1: Target URL includes query parameter lang=ar'
      );
      assert(capturedRequests[0].options?.method === 'POST', 'T1: Request method is POST');
      assert(capturedRequests[0].options?.credentials === 'include', 'T1: credentials: include is preserved');

      const headers = capturedRequests[0].options?.headers as Headers;
      assert(headers.get('Accept') === 'application/json', 'T1: Accept header is application/json');
      assert(headers.get('Content-Type') === 'application/json', 'T1: Content-Type header is application/json');
      assert(headers.get('X-Language') === 'ar', 'T1: X-Language header is preserved');
      assert(
        headers.get('X-CSRF-TOKEN') === 'plaintext-session-token-12345',
        'T1: X-CSRF-TOKEN contains plaintext session token'
      );

      const parsedBody = JSON.parse((capturedRequests[0].options?.body as string) || '{}');
      assert(parsedBody.confirm_email === 'target@example.com', 'T1: confirm_email is in JSON body');
      assert(parsedBody.lang === 'ar', 'T1: lang is in JSON body');
    }

    // -------------------------------------------------------------------------
    // TEST 2: Token Protocol: Encrypted cookie sent as X-XSRF-TOKEN, NEVER X-CSRF-TOKEN
    // -------------------------------------------------------------------------
    {
      const capturedRequests: Array<{ url: string; options?: RequestInit }> = [];
      setCsrfToken('plaintext-token-999');

      // Mock document.cookie with encrypted Laravel cookie
      const fakeEncryptedCookie = 'eyJpdiI6InRlc3RfaXYiLCJ2YWx1ZSI6ImVuY3J5cHRlZF9kYXRhIiwibWFjIjoiMTIzNCJ9';
      (globalThis as any).document = {
        cookie: `XSRF-TOKEN=${encodeURIComponent(fakeEncryptedCookie)}; other_cookie=abc`,
      };

      globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : input.toString();
        capturedRequests.push({ url, options: init });

        return new Response(JSON.stringify({ status: 'success' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      };

      await LaravelAPI.deleteUser(99, 'test@example.com');

      const headers = capturedRequests[0].options?.headers as Headers;
      assert(
        headers.get('X-XSRF-TOKEN') === fakeEncryptedCookie,
        'T2: Encrypted cookie is sent exclusively via X-XSRF-TOKEN'
      );
      assert(
        headers.get('X-CSRF-TOKEN') === 'plaintext-token-999',
        'T2: Plaintext session token is sent via X-CSRF-TOKEN'
      );
      assert(
        headers.get('X-CSRF-TOKEN') !== fakeEncryptedCookie,
        'T2: Encrypted cookie is NEVER sent as X-CSRF-TOKEN'
      );

      delete (globalThis as any).document;
    }

    // -------------------------------------------------------------------------
    // TEST 3: Automatic retry on HTTP 419 token expiration
    // -------------------------------------------------------------------------
    {
      const capturedUrls: string[] = [];
      setCsrfToken('expired-stale-token');

      let attempt = 0;
      globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : input.toString();
        capturedUrls.push(url);

        if (url.startsWith('/admin-home/frontend-users/55/delete')) {
          attempt++;
          if (attempt === 1) {
            return new Response(JSON.stringify({ message: 'CSRF token mismatch.' }), {
              status: 419,
              headers: { 'Content-Type': 'application/json' },
            });
          }
          const headers = init?.headers as Headers;
          if (headers.get('X-CSRF-TOKEN') === 'fresh-refreshed-token-777') {
            return new Response(JSON.stringify({ status: 'success', message: 'Deleted after retry' }), {
              status: 200,
              headers: { 'Content-Type': 'application/json' },
            });
          }
        }

        if (url === '/admin-home/csrf-token') {
          return new Response(JSON.stringify({ success: true, csrf_token: 'fresh-refreshed-token-777' }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
          });
        }

        return new Response('Not Found', { status: 404 });
      };

      const result = await LaravelAPI.deleteUser(55, 'retry@example.com');

      assert(result?.status === 'success', 'T3: Request succeeds after 419 retry');
      assert(capturedUrls.length === 3, 'T3: 3 calls: initial delete (419) -> fetch csrf-token -> retried delete (200)');
      assert(capturedUrls[0].includes('/55/delete'), 'T3: First call was initial delete');
      assert(capturedUrls[1] === '/admin-home/csrf-token', 'T3: Second call fetched fresh CSRF token');
      assert(capturedUrls[2].includes('/55/delete'), 'T3: Third call retried delete with fresh token');
    }

    // -------------------------------------------------------------------------
    // TEST 4: Controlled error handling when token is permanently rejected
    // -------------------------------------------------------------------------
    {
      setCsrfToken('permanently-invalid-token');

      globalThis.fetch = async (input: RequestInfo | URL) => {
        const url = typeof input === 'string' ? input : input.toString();
        if (url === '/admin-home/csrf-token') {
          return new Response(JSON.stringify({ message: 'Session expired' }), {
            status: 401,
            headers: { 'Content-Type': 'application/json' },
          });
        }
        return new Response(JSON.stringify({ message: 'CSRF token mismatch.' }), {
          status: 419,
          headers: { 'Content-Type': 'application/json' },
        });
      };

      let errorThrown = false;
      let errorMessage = '';
      try {
        await LaravelAPI.deleteUser(88, 'fail@example.com');
      } catch (err: any) {
        errorThrown = true;
        errorMessage = err.message;
      }

      assert(errorThrown, 'T4: Exception thrown when CSRF refresh fails');
      assert(
        errorMessage.includes('CSRF token mismatch') || errorMessage.includes('419'),
        'T4: Controlled error message informs client of deletion failure'
      );
    }

  } finally {
    globalThis.fetch = originalFetch;
  }

  console.log('\n--------------------------------------------------------------------');
  console.log(`TEST RESULTS: Passed: ${passed}, Failed: ${failed}`);
  console.log('--------------------------------------------------------------------');

  if (failed > 0) {
    process.exit(1);
  }
}

runTests().catch((err) => {
  console.error('Fatal test error:', err);
  process.exit(1);
});
