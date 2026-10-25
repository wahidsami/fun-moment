<?php

namespace App\Services\Music;

class AudioValidationResult
{
    public bool $isValid;
    public ?string $format;
    public ?string $mimeType;
    public int $fileSizeBytes;
    public ?string $error;
    public ?string $detectedExtension;

    public function __construct(
        bool $isValid,
        ?string $format = null,
        ?string $mimeType = null,
        int $fileSizeBytes = 0,
        ?string $error = null,
        ?string $detectedExtension = null
    ) {
        $this->isValid = $isValid;
        $this->format = $format;
        $this->mimeType = $mimeType;
        $this->fileSizeBytes = $fileSizeBytes;
        $this->error = $error;
        $this->detectedExtension = $detectedExtension;
    }

    public static function success(string $format, string $mimeType, int $size, string $extension): self
    {
        return new self(true, $format, $mimeType, $size, null, $extension);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, null, 0, $error, null);
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getFileSizeBytes(): int
    {
        return $this->fileSizeBytes;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getDetectedExtension(): ?string
    {
        return $this->detectedExtension;
    }
}
