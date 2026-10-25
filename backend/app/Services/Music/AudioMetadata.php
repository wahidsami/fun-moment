<?php

namespace App\Services\Music;

class AudioMetadata
{
    public bool $isValid;
    public float $durationExact;
    public int $durationSeconds;
    public ?string $format;
    public ?string $codec;
    public ?int $bitrateKbps;
    public ?int $sampleRate;
    public ?int $channels;
    public int $fileSizeBytes;
    public ?string $error;

    public function __construct(
        bool $isValid,
        float $durationExact = 0.0,
        ?string $format = null,
        ?string $codec = null,
        ?int $bitrateKbps = null,
        ?int $sampleRate = null,
        ?int $channels = null,
        int $fileSizeBytes = 0,
        ?string $error = null
    ) {
        $this->isValid = $isValid;
        $this->durationExact = $durationExact;
        $this->durationSeconds = (int) round($durationExact);
        $this->format = $format;
        $this->codec = $codec;
        $this->bitrateKbps = $bitrateKbps;
        $this->sampleRate = $sampleRate;
        $this->channels = $channels;
        $this->fileSizeBytes = $fileSizeBytes;
        $this->error = $error;
    }

    public static function failure(string $error): self
    {
        return new self(false, 0.0, null, null, null, null, null, 0, $error);
    }
}
