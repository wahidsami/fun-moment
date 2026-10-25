<?php

namespace App\Services\Music;

use App\MusicSetting;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class AudioValidationService
{
    public const FORMAT_MP3 = 'mp3';
    public const FORMAT_WAV = 'wav';
    public const FORMAT_FLAC = 'flac';
    public const FORMAT_AAC = 'aac';
    public const FORMAT_M4A = 'm4a';

    public const SUPPORTED_FORMATS = [
        self::FORMAT_MP3,
        self::FORMAT_WAV,
        self::FORMAT_FLAC,
        self::FORMAT_AAC,
        self::FORMAT_M4A,
    ];

    public const MIME_MAP = [
        self::FORMAT_MP3 => ['audio/mpeg', 'audio/mp3', 'audio/x-mp3'],
        self::FORMAT_WAV => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        self::FORMAT_FLAC => ['audio/flac', 'audio/x-flac'],
        self::FORMAT_AAC => ['audio/aac', 'audio/x-aac'],
        self::FORMAT_M4A => ['audio/mp4', 'audio/x-m4a'],
    ];

    /**
     * Validate an audio file upload or local filesystem path.
     *
     * @param UploadedFile|string $file
     * @return AudioValidationResult
     */
    public function validate($file, ?int $maxUploadBytes = null): AudioValidationResult
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $clientName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if (!file_exists($path) || !is_readable($path)) {
            return AudioValidationResult::failure('Audio file does not exist or cannot be read.');
        }

        $fileSize = filesize($path);
        if ($fileSize === false || $fileSize < 1024) {
            return AudioValidationResult::failure('Audio file is empty or too small to be a valid audio stream (minimum 1 KB required).');
        }

        // 1. Upload Size Limits (Governed by MusicSetting & PHP SAPI ceiling)
        $maxBytes = $maxUploadBytes ?? MusicSetting::getEffectiveMaxUploadBytes();
        if ($fileSize > $maxBytes) {
            $maxMb = max(1, (int) round($maxBytes / (1024 * 1024)));
            return AudioValidationResult::failure(
                "Audio file size exceeds the maximum permitted limit of {$maxMb} MB."
            );
        }

        // 2. Binary Magic Bytes & Header Inspection
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return AudioValidationResult::failure('Unable to open audio stream for inspection.');
        }
        $header = fread($handle, 4096);
        fclose($handle);

        if (strlen($header) < 12) {
            return AudioValidationResult::failure('Audio file header is truncated.');
        }

        // Security: Prevent script injection / polyglots (e.g. PHP/HTML inside audio extension)
        if ($this->containsScriptSignatures($header)) {
            return AudioValidationResult::failure('Security violation: file contains executable code or script tags.');
        }

        $detectedFormat = $this->detectFormatFromMagicBytes($header);
        if ($detectedFormat === null) {
            return AudioValidationResult::failure(
                'Unsupported audio container or invalid magic bytes. Supported formats: MP3, WAV, FLAC, AAC/M4A.'
            );
        }

        // 3. Platform Settings Allowed Formats Enforcement
        if (!MusicSetting::isAudioFormatAllowed($detectedFormat)) {
            $allowedList = implode(', ', array_map('strtoupper', MusicSetting::getAllowedAudioFormats()));
            return AudioValidationResult::failure(
                "Audio format '{$detectedFormat}' is currently not permitted by platform configuration. Allowed formats: {$allowedList}."
            );
        }

        // 4. Extension Spoof Detection
        $clientExt = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!MusicSetting::isAudioFormatAllowed($clientExt)) {
            $allowedList = implode(', ', array_map('strtoupper', MusicSetting::getAllowedAudioFormats()));
            return AudioValidationResult::failure(
                "File extension '.{$clientExt}' is not permitted by platform configuration. Allowed formats: {$allowedList}."
            );
        }

        if (!$this->isExtensionCompatibleWithFormat($clientExt, $detectedFormat)) {
            return AudioValidationResult::failure(
                "File extension '.{$clientExt}' does not match the detected audio container format '{$detectedFormat}'."
            );
        }

        // 5. Resolve Canonical MIME
        $mime = $this->getCanonicalMime($detectedFormat);

        return AudioValidationResult::success(
            $detectedFormat,
            $mime,
            $fileSize,
            $clientExt ?: $detectedFormat
        );
    }

    /**
     * Detect audio format strictly from binary magic bytes.
     */
    public function detectFormatFromMagicBytes(string $header): ?string
    {
        // FLAC: Starts with 'fLaC' (0x66 0x4C 0x61 0x43)
        if (substr($header, 0, 4) === "fLaC") {
            return self::FORMAT_FLAC;
        }

        // WAV: Starts with 'RIFF' and offset 8 is 'WAVE'
        if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WAVE') {
            return self::FORMAT_WAV;
        }

        // MP3 with ID3v2 tag: starts with 'ID3' (0x49 0x44 0x33)
        if (substr($header, 0, 3) === 'ID3') {
            return self::FORMAT_MP3;
        }

        // MP4 / M4A container: bytes 4-8 are 'ftyp' followed by brand (M4A , isom, mp42, etc.)
        if (substr($header, 4, 4) === 'ftyp') {
            $brand = substr($header, 8, 4);
            if (in_array($brand, ['M4A ', 'm4a ', 'mp42', 'isom', 'dash'])) {
                return self::FORMAT_M4A;
            }
        }

        // Raw AAC ADTS: Starts with 0xFFF sync word (first byte 0xFF, second byte high nibble 0xF)
        $byte0 = ord($header[0]);
        $byte1 = ord($header[1]);
        if ($byte0 === 0xFF && ($byte1 & 0xF0) === 0xF0 && ($byte1 & 0x06) === 0x00) {
            return self::FORMAT_AAC;
        }

        // Raw MPEG Audio Layer 3 (MP3 without ID3 header)
        // First byte 0xFF, second byte starts with 0b111 (sync) and Layer III bits
        if ($byte0 === 0xFF && ($byte1 & 0xE0) === 0xE0) {
            $layer = ($byte1 >> 1) & 0x03;
            if ($layer === 1) { // 01 in binary represents Layer III
                return self::FORMAT_MP3;
            }
        }

        return null;
    }

    /**
     * Check if client extension is compatible with the detected format.
     */
    public function isExtensionCompatibleWithFormat(string $ext, string $format): bool
    {
        switch ($format) {
            case self::FORMAT_MP3:
                return $ext === 'mp3';
            case self::FORMAT_WAV:
                return in_array($ext, ['wav', 'wave'], true);
            case self::FORMAT_FLAC:
                return $ext === 'flac';
            case self::FORMAT_AAC:
                // Raw AAC ADTS stream requires .aac extension
                return $ext === 'aac';
            case self::FORMAT_M4A:
                // MP4/M4A audio container requires .m4a extension
                return $ext === 'm4a';
            default:
                return false;
        }
    }

    /**
     * Get primary canonical MIME type for format.
     */
    public function getCanonicalMime(string $format): string
    {
        switch ($format) {
            case self::FORMAT_MP3:
                return 'audio/mpeg';
            case self::FORMAT_WAV:
                return 'audio/wav';
            case self::FORMAT_FLAC:
                return 'audio/flac';
            case self::FORMAT_AAC:
                return 'audio/aac';
            case self::FORMAT_M4A:
                return 'audio/mp4';
            default:
                return 'application/octet-stream';
        }
    }

    /**
     * Check for executable/script signatures in audio headers.
     */
    private function containsScriptSignatures(string $chunk): bool
    {
        $lowered = strtolower($chunk);
        $patterns = [
            '<?php',
            '<?=',
            '<script',
            'eval(',
            'base64_decode',
            'system(',
            'shell_exec',
            'passthru',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lowered, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
