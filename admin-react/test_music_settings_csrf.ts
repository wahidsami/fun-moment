/**
 * Regression Test: Music Marketplace Settings CSRF Token Protection
 *
 * Verifies:
 * 1. saveMusicSettings attaches X-CSRF-TOKEN header to POST /admin-home/music-settings-json
 * 2. Automatic retry mechanism on HTTP 419 (CSRF token expiration)
 * 3. Successful payload deserialization
 */

import { LaravelAPI, setCsrfToken } from './src/api';

async function runTests() {
  console.log('--- Running Music Settings CSRF Regression Tests ---');
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
    // TEST 1: Verify X-CSRF-TOKEN is attached on saveMusicSettings
    {
      const capturedRequests: Array<{ url: string; options?: RequestInit }> = [];
      setCsrfToken('authoritative-csrf-token-xyz');

      globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : input.toString();
        capturedRequests.push({ url, options: init });

        if (url === '/admin-home/music-settings-json') {
          return new Response(
            JSON.stringify({
              success: true,
              message_en: 'Music settings updated successfully',
              data: {
                music_marketplace_enabled: true,
                paid_preview_duration_seconds: 30,
              },
            }),
            { status: 200, headers: { 'Content-Type': 'application/json' } }
          );
        }

        return new Response('Not Found', { status: 404 });
      };

      const result = await LaravelAPI.saveMusicSettings({
        music_marketplace_enabled: true,
        paid_preview_duration_seconds: 30,
      });

      assert(result.success === true, 'saveMusicSettings returns successful API response');
      assert(capturedRequests.length === 1, 'Only one HTTP request dispatched when token is valid');
      assert(capturedRequests[0].url === '/admin-home/music-settings-json', 'Dispatched to /admin-home/music-settings-json');
      assert(capturedRequests[0].options?.method === 'POST', 'HTTP method is POST');

      const headers = capturedRequests[0].options?.headers as Headers | Record<string, string>;
      const csrfHeader = headers instanceof Headers ? headers.get('X-CSRF-TOKEN') : headers?.['X-CSRF-TOKEN'];
      assert(csrfHeader === 'authoritative-csrf-token-xyz', 'X-CSRF-TOKEN header matches expected session token');
    }

    // TEST 2: Verify automatic retry on HTTP 419 (CSRF token expiration)
    {
      const capturedCalls: string[] = [];
      setCsrfToken('stale-token');

      let attempt = 0;
      globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : input.toString();
        capturedCalls.push(url);

        if (url === '/admin-home/music-settings-json') {
          attempt++;
          if (attempt === 1) {
            // First attempt returns 419 CSRF Token Mismatch
            return new Response(JSON.stringify({ message: 'CSRF token mismatch' }), {
              status: 419,
              headers: { 'Content-Type': 'application/json' },
            });
          }
          // Second attempt with refreshed token returns 200
          const headers = init?.headers as Headers | Record<string, string>;
          const token = headers instanceof Headers ? headers.get('X-CSRF-TOKEN') : headers?.['X-CSRF-TOKEN'];
          if (token === 'refreshed-fresh-token-abc') {
            return new Response(
              JSON.stringify({
                success: true,
                message_en: 'Settings saved after token refresh',
                data: { music_marketplace_enabled: false },
              }),
              { status: 200, headers: { 'Content-Type': 'application/json' } }
            );
          }
        }

        if (url === '/admin-home/csrf-token') {
          return new Response(JSON.stringify({ csrf_token: 'refreshed-fresh-token-abc' }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
          });
        }

        return new Response('Not Found', { status: 404 });
      };

      const result = await LaravelAPI.saveMusicSettings({ music_marketplace_enabled: false });

      assert(result.success === true, 'saveMusicSettings succeeds after 419 transparent recovery');
      assert(capturedCalls.includes('/admin-home/csrf-token'), 'Fetched fresh CSRF token upon receiving 419');
      assert(attempt === 2, 'Retried saveMusicSettings after token refresh');
    }

    console.log(`\nResults: ${passed} passed, ${failed} failed.`);
    if (failed > 0) {
      process.exit(1);
    }
  } finally {
    globalThis.fetch = originalFetch;
    setCsrfToken(null);
  }
}

runTests().catch((err) => {
  console.error('Test suite failed:', err);
  process.exit(1);
});
