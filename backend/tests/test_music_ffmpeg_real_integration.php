<?php

/**
 * FUN MOMENT — Music Marketplace Phase 2
 * Real FFmpeg & FFprobe Integration Test Suite
 *
 * This test path NEVER uses mocks or simulated handlers.
 * It strictly tests the real host-installed ffmpeg and ffprobe binaries.
 * If binaries are not available on the current host, it explicitly reports
 * the skip and provides the exact reproducible command for an FFmpeg-enabled host.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Music\FFmpegAudioProcessor;

class RealFFmpegTestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function assert(bool $condition, string $description): void
    {
        if ($condition) {
            echo "  [PASS] {$description}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$description}\n";
            $this->failed++;
        }
    }

    public function summary(): bool
    {
        echo "\n======================================================\n";
        echo "Real FFmpeg Integration Test Results:\n";
        echo "  Passed: {$this->passed}\n";
        echo "  Failed: {$this->failed}\n";
        echo "======================================================\n";
        return $this->failed === 0;
    }
}

echo "========================================================================\n";
echo "FUN MOMENT — REAL FFMPEG & FFPROBE INTEGRATION TEST\n";
echo "========================================================================\n";

// STRICT GUARANTEE: Never use mocks in this test
FFmpegAudioProcessor::setMockProbeHandler(null);
FFmpegAudioProcessor::setMockProcessHandler(null);

$processor = new FFmpegAudioProcessor();

if (!$processor->isAvailable()) {
    echo "\n[NOTICE] FFmpeg and/or FFprobe binaries are NOT available on this host.\n";
    echo "Host OS: " . PHP_OS_FAMILY . " (" . PHP_OS . ")\n";
    echo "This is expected on local development environments where ffmpeg is not installed.\n";
    echo "\nTo execute this real integration test in an FFmpeg-enabled environment (e.g. Coolify container):\n";
    echo "  1. Ensure ffmpeg and ffprobe are on PATH (e.g. via nixpacks.toml nixPkgs = ['ffmpeg'])\n";
    echo "  2. Run command:\n";
    echo "     php backend/tests/test_music_ffmpeg_real_integration.php\n\n";
    echo "Result: SKIPPED (Binaries unavailable on current host). Mock handlers were NOT used.\n";
    echo "========================================================================\n";
    exit(0);
}

echo "\nFFmpeg and FFprobe binaries detected on host! Executing real media pipeline...\n\n";

$runner = new RealFFmpegTestRunner();
$tempDir = sys_get_temp_dir() . '/ffmpeg_real_test_' . uniqid();
@mkdir($tempDir, 0755, true);

// 1. Generate a small, legitimate 44.1kHz 16-bit mono PCM WAV audio fixture (8.0 seconds)
$fixturePath = $tempDir . '/fixture_real.wav';
$sampleRate = 44100;
$durationSec = 8.0;
$numSamples = (int) ($sampleRate * $durationSec);
$dataSize = $numSamples * 2; // 16-bit = 2 bytes per sample
$fileSize = 44 + $dataSize;

$header = pack(
    'a4Va4a4VvvVVvva4V',
    'RIFF',
    $fileSize - 8,
    'WAVE',
    'fmt ',
    16,            // Subchunk1Size (16 for PCM)
    1,             // AudioFormat (1 for PCM)
    1,             // NumChannels (1 mono)
    $sampleRate,   // SampleRate
    $sampleRate * 2, // ByteRate (SampleRate * NumChannels * BitsPerSample/8)
    2,             // BlockAlign (NumChannels * BitsPerSample/8)
    16,            // BitsPerSample
    'data',
    $dataSize
);

// Generate 440Hz sine wave (A4 note)
$samples = '';
for ($i = 0; $i < $numSamples; $i++) {
    $t = $i / $sampleRate;
    $val = (int) (sin(2 * M_PI * 440 * $t) * 16000);
    $samples .= pack('v', $val & 0xFFFF);
}

file_put_contents($fixturePath, $header . $samples);
$runner->assert(file_exists($fixturePath) && filesize($fixturePath) > 1000, 'Legitimate WAV audio fixture created');

// 2. Real FFprobe Metadata Extraction
$metadata = $processor->probeMetadata($fixturePath);
$runner->assert($metadata->isValid, 'Real FFprobe successfully probed WAV fixture');
$runner->assert($metadata->durationSeconds === 8 && $metadata->durationExact >= 7.9, "Real FFprobe reports expected duration (~8.0s, actual: {$metadata->durationSeconds}s)");
$runner->assert(str_starts_with((string) $metadata->codec, 'pcm_'), "Real FFprobe detected valid PCM audio codec: {$metadata->codec}");
$runner->assert($metadata->sampleRate === 44100, 'Real FFprobe detected 44100 Hz sample rate');
$runner->assert($metadata->channels === 1, 'Real FFprobe detected mono channel count');

// 3. Real Preview Generation (with audio fade-out)
$previewPath = $tempDir . '/preview_out.mp3';
$previewResult = $processor->generatePreview($fixturePath, $previewPath, 5, true);
$runner->assert($previewResult && file_exists($previewPath), 'Real FFmpeg successfully generated preview MP3');

$previewMeta = $processor->probeMetadata($previewPath);
$runner->assert($previewMeta->isValid, 'Real FFprobe successfully probed generated preview');
$runner->assert($previewMeta->codec === 'mp3', "Generated preview is valid MP3 codec: {$previewMeta->codec}");
$runner->assert($previewMeta->durationSeconds >= 4 && $previewMeta->durationSeconds <= 6, "Generated preview duration is ~5s: {$previewMeta->durationSeconds}s");

// 4. Real Streaming Rendition Generation (Transcoding to 192k MP3)
$streamPath = $tempDir . '/stream_rendition_out.mp3';
$streamResult = $processor->generateStreamingRendition($fixturePath, $streamPath);
$runner->assert($streamResult && file_exists($streamPath), 'Real FFmpeg successfully generated streaming rendition MP3');

$streamMeta = $processor->probeMetadata($streamPath);
$runner->assert($streamMeta->isValid, 'Real FFprobe successfully probed generated streaming rendition');
$runner->assert($streamMeta->codec === 'mp3', "Streaming rendition is valid MP3 codec: {$streamMeta->codec}");
$runner->assert($streamMeta->durationSeconds === 8, "Streaming rendition matches full duration (8s)");

// 5. Corrupt / Invalid Input Safe Rejection
$corruptPath = $tempDir . '/corrupt.wav';
file_put_contents($corruptPath, 'RIFF' . str_repeat("\x00", 100)); // Malformed header, no fmt/data
$corruptMeta = $processor->probeMetadata($corruptPath);
$runner->assert(!$corruptMeta->isValid, 'Real FFprobe safely rejects corrupt/truncated audio stream');

// 6. Cleanup
@unlink($fixturePath);
@unlink($previewPath);
@unlink($streamPath);
@unlink($corruptPath);
@rmdir($tempDir);

$success = $runner->summary();
exit($success ? 0 : 1);
