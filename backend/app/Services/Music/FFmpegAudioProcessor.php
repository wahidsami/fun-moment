<?php

namespace App\Services\Music;

use Illuminate\Support\Facades\Log;

class FFmpegAudioProcessor implements AudioProcessorInterface
{
    protected string $ffmpegPath;
    protected string $ffprobePath;
    protected ?bool $isAvailableCache = null;

    /**
     * Optional simulated mock handler for testing environments lacking ffmpeg binaries.
     * @var callable|null
     */
    protected static $mockProbeHandler = null;
    protected static $mockProcessHandler = null;

    public function __construct(?string $ffmpegPath = null, ?string $ffprobePath = null)
    {
        $ffmpeg = $ffmpegPath ?? config('services.music.ffmpeg_path', env('FFMPEG_BINARY', 'ffmpeg'));
        $ffprobe = $ffprobePath ?? config('services.music.ffprobe_path', env('FFPROBE_BINARY', 'ffprobe'));

        // On Windows hosts, if binary is not on direct PATH, check installed candidate paths
        if (PHP_OS_FAMILY === 'Windows') {
            if ($ffmpeg === 'ffmpeg' && !file_exists($ffmpeg)) {
                $candidates = [
                    'C:\\Program Files\\Krita (x64)\\bin\\ffmpeg.exe',
                    'C:\\ffmpeg\\bin\\ffmpeg.exe',
                ];
                foreach ($candidates as $cand) {
                    if (file_exists($cand)) {
                        $ffmpeg = $cand;
                        break;
                    }
                }
            }
            if ($ffprobe === 'ffprobe' && !file_exists($ffprobe)) {
                $candidates = [
                    'C:\\Program Files\\Krita (x64)\\bin\\ffprobe.exe',
                    'C:\\ffmpeg\\bin\\ffprobe.exe',
                ];
                foreach ($candidates as $cand) {
                    if (file_exists($cand)) {
                        $ffprobe = $cand;
                        break;
                    }
                }
            }
        }

        $this->ffmpegPath = $ffmpeg;
        $this->ffprobePath = $ffprobe;
    }

    /**
     * Register a mock probe handler for automated test environments.
     */
    public static function setMockProbeHandler(?callable $handler): void
    {
        self::$mockProbeHandler = $handler;
    }

    /**
     * Register a mock process handler for preview/stream generation in tests.
     */
    public static function setMockProcessHandler(?callable $handler): void
    {
        self::$mockProcessHandler = $handler;
    }

    public function isAvailable(): bool
    {
        if (self::$mockProbeHandler !== null) {
            return true;
        }

        if ($this->isAvailableCache !== null) {
            return $this->isAvailableCache;
        }

        // Test execution of ffprobe -version
        $output = [];
        $returnCode = 1;
        $cmd = escapeshellarg($this->ffprobePath) . ' -version';

        try {
            @exec($cmd . ' 2>&1', $output, $returnCode);
            $this->isAvailableCache = ($returnCode === 0);
        } catch (\Throwable $e) {
            $this->isAvailableCache = false;
        }

        return $this->isAvailableCache;
    }

    public function probeMetadata(string $filePath): AudioMetadata
    {
        if (!file_exists($filePath)) {
            return AudioMetadata::failure("Source audio file does not exist: {$filePath}");
        }

        $fileSize = (int) filesize($filePath);
        if ($fileSize <= 0) {
            return AudioMetadata::failure("Source audio file is empty (0 bytes): {$filePath}");
        }

        if (self::$mockProbeHandler !== null) {
            $mockResult = call_user_func(self::$mockProbeHandler, $filePath, $fileSize);
            // Ensure mock result also undergoes codec validation if valid
            if ($mockResult->isValid && $mockResult->codec) {
                $codec = strtolower((string) $mockResult->codec);
                $approvedCodecs = ['mp3', 'flac', 'aac'];
                $isApproved = in_array($codec, $approvedCodecs, true) || str_starts_with($codec, 'pcm_');
                if (!$isApproved) {
                    return AudioMetadata::failure("Unsupported or unapproved audio codec '{$codec}'. Approved codecs: MP3, WAV (PCM), FLAC, AAC.");
                }
            }
            return $mockResult;
        }

        if (!$this->isAvailable()) {
            return AudioMetadata::failure("FFprobe binary is not available or not executable on the host system");
        }

        $cmd = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s',
            escapeshellarg($this->ffprobePath),
            escapeshellarg($filePath)
        );

        $result = $this->executeCommandWithTimeout($cmd, 30);

        if ($result['timed_out']) {
            return AudioMetadata::failure("FFprobe metadata inspection timed out after 30 seconds");
        }

        if ($result['exit_code'] !== 0 || empty($result['output'])) {
            $diag = !empty($result['output']) ? ": " . substr($result['output'], 0, 500) : "";
            Log::warning("FFmpegAudioProcessor::probeMetadata - FFprobe failed to inspect audio file (exit code {$result['exit_code']}){$diag}");
            return AudioMetadata::failure("FFprobe failed to inspect audio file (exit code {$result['exit_code']}){$diag}");
        }

        $json = json_decode($result['output'], true);
        if (!$json || !is_array($json)) {
            return AudioMetadata::failure("Invalid JSON response received from FFprobe");
        }

        $format = $json['format'] ?? [];
        $streams = $json['streams'] ?? [];

        // Find primary audio stream
        $audioStream = null;
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                $audioStream = $stream;
                break;
            }
        }

        if (!$audioStream) {
            return AudioMetadata::failure("No valid audio stream detected in file");
        }

        $durationExact = (float) ($format['duration'] ?? $audioStream['duration'] ?? 0.0);
        if ($durationExact <= 0.0) {
            return AudioMetadata::failure("Audio file has zero or undetectable duration");
        }

        $formatName = $format['format_name'] ?? null;
        $codec = strtolower((string) ($audioStream['codec_name'] ?? ''));

        // Whitelist approved audio codecs: MP3, AAC, FLAC, and PCM (WAV)
        $approvedCodecs = ['mp3', 'flac', 'aac'];
        $isApproved = in_array($codec, $approvedCodecs, true) || str_starts_with($codec, 'pcm_');
        if (!$isApproved) {
            return AudioMetadata::failure("Unsupported or unapproved audio codec '{$codec}'. Approved codecs: MP3, WAV (PCM), FLAC, AAC.");
        }

        $bitrateBps = (int) ($format['bit_rate'] ?? $audioStream['bit_rate'] ?? 0);
        $bitrateKbps = $bitrateBps > 0 ? (int) round($bitrateBps / 1000) : null;
        $sampleRate = isset($audioStream['sample_rate']) ? (int) $audioStream['sample_rate'] : null;
        $channels = isset($audioStream['channels']) ? (int) $audioStream['channels'] : null;

        return new AudioMetadata(
            true,
            $durationExact,
            $formatName,
            $codec,
            $bitrateKbps,
            $sampleRate,
            $channels,
            $fileSize,
            null
        );
    }

    public function generatePreview(
        string $sourcePath,
        string $outputPath,
        int $durationSeconds,
        bool $applyFade = true
    ): bool {
        if (!file_exists($sourcePath)) {
            Log::error("FFmpegAudioProcessor::generatePreview - source not found: {$sourcePath}");
            return false;
        }

        if (self::$mockProcessHandler !== null) {
            return call_user_func(self::$mockProcessHandler, 'preview', $sourcePath, $outputPath, $durationSeconds, $applyFade);
        }

        if (!$this->isAvailable()) {
            Log::warning("FFmpegAudioProcessor::generatePreview - FFmpeg not available on system");
            return false;
        }

        // Clamp duration within reasonable bounds (5 - 60s)
        $durationSeconds = max(5, min(60, $durationSeconds));

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }

        // Fade filter: apply subtle 2-second fade-out before end if requested and duration >= 6s
        $audioFilter = '';
        if ($applyFade && $durationSeconds >= 6) {
            $fadeStart = $durationSeconds - 2;
            $audioFilter = sprintf('-af afade=t=out:st=%d:d=2', $fadeStart);
        }

        $cmd = sprintf(
            '%s -y -i %s -t %d %s -vn -acodec libmp3lame -b:a 192k %s',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($sourcePath),
            $durationSeconds,
            $audioFilter,
            escapeshellarg($outputPath)
        );

        $result = $this->executeCommandWithTimeout($cmd, 90);

        if ($result['exit_code'] !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error("FFmpeg preview generation failed (exit {$result['exit_code']}): " . $result['output']);
            return false;
        }

        return true;
    }

    public function generateStreamingRendition(
        string $sourcePath,
        string $outputPath,
        int $targetBitrateKbps = 192
    ): bool {
        if (!file_exists($sourcePath)) {
            Log::error("FFmpegAudioProcessor::generateStreamingRendition - source not found: {$sourcePath}");
            return false;
        }

        if (self::$mockProcessHandler !== null) {
            return call_user_func(self::$mockProcessHandler, 'stream', $sourcePath, $outputPath, $targetBitrateKbps);
        }

        if (!$this->isAvailable()) {
            Log::warning("FFmpegAudioProcessor::generateStreamingRendition - FFmpeg not available on system");
            return false;
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }

        $cmd = sprintf(
            '%s -y -i %s -vn -acodec libmp3lame -b:a %dk %s',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($sourcePath),
            $targetBitrateKbps,
            escapeshellarg($outputPath)
        );

        $result = $this->executeCommandWithTimeout($cmd, 120);

        if ($result['exit_code'] !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error("FFmpeg streaming rendition generation failed (exit {$result['exit_code']}): " . $result['output']);
            return false;
        }

        return true;
    }

    /**
     * Safely execute a system command with timeout protection against hung processes.
     *
     * @param string $cmd Command string to execute
     * @param int $timeoutSeconds Maximum allowed runtime in seconds (default 60s)
     * @return array{exit_code: int, output: string, timed_out: bool}
     */
    public function executeCommandWithTimeout(string $cmd, int $timeoutSeconds = 60): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return ['exit_code' => 1, 'output' => 'Failed to spawn process', 'timed_out' => false];
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $errorOutput = '';
        $startTime = microtime(true);
        $timedOut = false;
        $capturedExitCode = null;

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                if (isset($status['exitcode']) && $status['exitcode'] !== -1) {
                    $capturedExitCode = (int) $status['exitcode'];
                }
                break;
            }

            if ((microtime(true) - $startTime) > $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }

            // On POSIX systems, drain stream pipes incrementally to prevent buffer clogging on large output
            if (PHP_OS_FAMILY !== 'Windows') {
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;

                if (@stream_select($read, $write, $except, 0, 50000) > 0) {
                    if (in_array($pipes[1], $read, true)) {
                        $chunk = fread($pipes[1], 4096);
                        if ($chunk !== false) {
                            $output .= $chunk;
                        }
                    }
                    if (in_array($pipes[2], $read, true)) {
                        $chunk = fread($pipes[2], 4096);
                        if ($chunk !== false) {
                            $errorOutput .= $chunk;
                        }
                    }
                }
            }

            usleep(20000); // 20ms
        }

        $output .= stream_get_contents($pipes[1]);
        $errorOutput .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $procCloseExit = proc_close($process);

        if ($timedOut) {
            $exitCode = 124;
        } elseif ($procCloseExit !== -1) {
            $exitCode = $procCloseExit;
        } elseif ($capturedExitCode !== null) {
            $exitCode = $capturedExitCode;
        } else {
            $exitCode = -1;
        }

        return [
            'exit_code' => $exitCode,
            'output' => trim($output . "\n" . $errorOutput),
            'timed_out' => $timedOut,
        ];
    }
}
