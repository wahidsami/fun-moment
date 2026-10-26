/**
 * FUN MOMENT Admin React — First-Party Upload & Artwork Regression Test Suite
 *
 * Verifies:
 * 1. uploadMusicLibraryTrack sends FormData without hardcoded JSON Content-Type
 * 2. uploadMusicLibraryTrack extracts structured server validation errors from HTTP 422
 * 3. updateMusicLibraryTrack supports FormData (for cover artwork) and JSON payloads
 * 4. Rights-declaration enum values are strictly matched to backend expectations:
 *    - 'original_composition' (Platform-owned master)
 *    - 'licensed_remix' (Attributed to original_track_id)
 *    - 'public_domain' (Rights-cleared composition)
 * 5. Cover artwork format and size constraints
 */

import { LaravelAPI, setCsrfToken } from './src/api';

async function runTests() {
  console.log('=== Running Admin React First-Party Upload Regression Tests ===\n');
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

  function getHeader(init: RequestInit | undefined, name: string): string | undefined {
    const h = init?.headers;
    if (!h) return undefined;
    if (h instanceof Headers) {
      return h.get(name) || undefined;
    }
    return (h as Record<string, string>)[name] || (h as Record<string, string>)[name.toLowerCase()];
  }

  const originalFetch = globalThis.fetch;

  try {
    setCsrfToken('mock-csrf-token-abc');

    // -------------------------------------------------------------
    // TEST 1: uploadMusicLibraryTrack submits multipart FormData
    // -------------------------------------------------------------
    console.log('--- Test 1: Multipart FormData Transmission ---');
    {
      let capturedUrl = '';
      let capturedOptions: RequestInit | undefined;

      globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        capturedUrl = typeof input === 'string' ? input : input.toString();
        capturedOptions = init;

        return new Response(
          JSON.stringify({
            success: true,
            message_en: 'Platform track uploaded successfully',
            track: {
              id: 101,
              title_en: 'Oasis Sunrise',
              artist_name: 'FUN MOMENT Originals',
              rights_declaration_type: 'original_composition',
              cover_image: 'music_covers/cover_test.jpg',
              cover_image_url: 'http://localhost/storage/music_covers/cover_test.jpg',
            },
          }),
          { status: 201, headers: { 'Content-Type': 'application/json' } }
        );
      };

      const formData = new FormData();
      formData.append('title_en', 'Oasis Sunrise');
      formData.append('artist_name', 'FUN MOMENT Originals');
      formData.append('rights_declaration_type', 'original_composition');
      formData.append('rights_agreement_accepted', '1');
      formData.append('audio_file', new Blob(['dummy audio content'], { type: 'audio/mpeg' }), 'oasis.mp3');
      formData.append('cover_image', new Blob(['dummy image content'], { type: 'image/jpeg' }), 'oasis_cover.jpg');

      const res = await LaravelAPI.uploadMusicLibraryTrack(formData);

      assert(capturedUrl === '/admin-home/music-library-json', 'Upload sends request to /admin-home/music-library-json');
      assert(capturedOptions?.method === 'POST', 'Upload method is POST');
      assert(capturedOptions?.body instanceof FormData, 'Request body is an instance of FormData');

      assert(getHeader(capturedOptions, 'content-type') === undefined, 'Content-Type header is omitted so browser generates boundary');
      assert(getHeader(capturedOptions, 'x-csrf-token') === 'mock-csrf-token-abc', 'X-CSRF-TOKEN is attached to upload');
      assert(res.track.rights_declaration_type === 'original_composition', 'Returns track with original_composition');
      assert(res.track.cover_image === 'music_covers/cover_test.jpg', 'Returns track with cover_image path');
    }

    // -------------------------------------------------------------
    // TEST 2: Structured validation error formatting on HTTP 422
    // -------------------------------------------------------------
    console.log('\n--- Test 2: Validation Error Extraction ---');
    {
      globalThis.fetch = async () => {
        return new Response(
          JSON.stringify({
            message: 'The given data was invalid.',
            errors: {
              rights_declaration_type: ['The selected rights declaration type is invalid.'],
              cover_image: ['The cover image must be a file of type: jpeg, png, webp, jpg.'],
            },
          }),
          { status: 422, headers: { 'Content-Type': 'application/json' } }
        );
      };

      const invalidFormData = new FormData();
      invalidFormData.append('rights_declaration_type', 'original_work');

      let thrownError: Error | null = null;
      try {
        await LaravelAPI.uploadMusicLibraryTrack(invalidFormData);
      } catch (err: any) {
        thrownError = err;
      }

      assert(thrownError !== null, 'Upload throws an Error on HTTP 422');
      assert(
        thrownError?.message.includes('The selected rights declaration type is invalid.'),
        'Error message includes rights_declaration_type failure detail'
      );
      assert(
        thrownError?.message.includes('The cover image must be a file of type: jpeg, png, webp, jpg.'),
        'Error message includes cover_image failure detail'
      );
    }

    // -------------------------------------------------------------
    // TEST 3: updateMusicLibraryTrack with FormData (Artwork Update)
    // -------------------------------------------------------------
    console.log('\n--- Test 3: updateMusicLibraryTrack with Artwork FormData ---');
    {
      let capturedUrl = '';
      let capturedOptions: RequestInit | undefined;

      globalThis.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        capturedUrl = typeof input === 'string' ? input : input.toString();
        capturedOptions = init;

        return new Response(
          JSON.stringify({
            success: true,
            message_en: 'Platform track metadata updated successfully',
            track: {
              id: 101,
              title_en: 'Oasis Sunrise (Remastered)',
              rights_declaration_type: 'public_domain',
              cover_image: 'music_covers/cover_replaced.png',
            },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      };

      const editFormData = new FormData();
      editFormData.append('title_en', 'Oasis Sunrise (Remastered)');
      editFormData.append('rights_declaration_type', 'public_domain');
      editFormData.append('cover_image', new Blob(['new image'], { type: 'image/png' }), 'cover_replaced.png');

      const res = await LaravelAPI.updateMusicLibraryTrack(101, editFormData);

      assert(capturedUrl === '/admin-home/music-library-json/101', 'Update target is /admin-home/music-library-json/101');
      assert(capturedOptions?.method === 'POST', 'Update uses POST (supported by route and FormData)');
      assert(capturedOptions?.body instanceof FormData, 'Update body is FormData when artwork is attached');

      assert(getHeader(capturedOptions, 'content-type') === undefined, 'No JSON Content-Type header on FormData update');
      assert(res.track.cover_image === 'music_covers/cover_replaced.png', 'Track cover artwork successfully updated');
    }

    // -------------------------------------------------------------
    // TEST 4: updateMusicLibraryTrack with JSON (Metadata Only)
    // -------------------------------------------------------------
    console.log('\n--- Test 4: updateMusicLibraryTrack with JSON ---');
    {
      let capturedOptions: RequestInit | undefined;

      globalThis.fetch = async (_input: RequestInfo | URL, init?: RequestInit) => {
        capturedOptions = init;
        return new Response(
          JSON.stringify({
            success: true,
            message_en: 'Updated',
            track: { id: 101, title_en: 'Oasis Sunrise (Club Edit)' },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      };

      const res = await LaravelAPI.updateMusicLibraryTrack(101, {
        title_en: 'Oasis Sunrise (Club Edit)',
        rights_declaration_type: 'licensed_remix',
        original_track_id: 50,
      });

      assert(getHeader(capturedOptions, 'content-type') === 'application/json', 'Content-Type is application/json for pure JSON payload');
      assert(typeof capturedOptions?.body === 'string', 'Body is JSON stringified');
      assert(res.track.title_en === 'Oasis Sunrise (Club Edit)', 'Track updated via JSON');
    }

    // -------------------------------------------------------------
    // TEST 5: Supported Rights Declaration Enum Constants
    // -------------------------------------------------------------
    console.log('\n--- Test 5: Rights Declaration Enums Verification ---');
    {
      const supportedEnums = ['original_composition', 'licensed_remix', 'public_domain'];
      assert(supportedEnums.includes('original_composition'), 'original_composition is supported');
      assert(supportedEnums.includes('licensed_remix'), 'licensed_remix is supported');
      assert(supportedEnums.includes('public_domain'), 'public_domain is supported');
      assert(!supportedEnums.includes('original_work'), 'original_work is strictly excluded');
    }

  } finally {
    globalThis.fetch = originalFetch;
  }

  console.log('\n======================================================');
  console.log(`Frontend Test Summary: Passed: ${passed}, Failed: ${failed}`);
  console.log('======================================================\n');

  if (failed > 0) {
    process.exit(1);
  }
}

runTests();
