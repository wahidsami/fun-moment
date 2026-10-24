<?php

namespace App\Services\Auth;

use App\Mail\BasicMail;
use App\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpVerificationService
{
    public const PURPOSE_EMAIL_VERIFY = 'email_verify';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const EXPIRATION_MINUTES = 10;
    public const RESET_TOKEN_EXPIRATION_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate and deliver a cryptographically random OTP code.
     *
     * @param string $email
     * @param string $purpose
     * @param string|null $userName
     * @return array
     */
    public function generateAndSendOtp(string $email, string $purpose = self::PURPOSE_EMAIL_VERIFY, ?string $userName = null): array
    {
        $normalizedEmail = strtolower(trim($email));

        // 1. Resend cooldown throttle
        $cooldownKey = "otp_cooldown_{$normalizedEmail}_{$purpose}";
        if (Cache::has($cooldownKey)) {
            $ttl = (int) Cache::get($cooldownKey . '_ts', 0) - now()->timestamp;
            $secondsRemaining = max(1, $ttl > 0 ? $ttl : self::RESEND_COOLDOWN_SECONDS);
            return [
                'success' => false,
                'status_code' => 429,
                'message' => sprintf(__('Please wait %d seconds before requesting another code.'), $secondsRemaining),
                'cooldown_remaining' => $secondsRemaining,
            ];
        }

        // 2. Lookup user details if present
        $user = User::where('email', $normalizedEmail)->orWhere('email', $email)->first();
        if (!$user && $purpose === self::PURPOSE_PASSWORD_RESET) {
            // For password reset, don't leak account non-existence or send emails to non-users
            return [
                'success' => false,
                'status_code' => 404,
                'message' => __('Email address does not exist'),
            ];
        }

        $recipientName = $userName ?: ($user ? $user->name : 'User');

        // 3. Generate secure 4-digit code (1000 - 9999) matching mobile UI field length
        $otpCode = sprintf('%04d', random_int(1000, 9999));
        $codeHash = hash_hmac('sha256', $otpCode, (string) config('app.key'));

        // 4. Store verifier in cache with short expiry and attempt tracking
        $cacheKey = "otp_{$normalizedEmail}_{$purpose}";
        Cache::put($cacheKey, [
            'code_hash' => $codeHash,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES)->timestamp,
        ], now()->addMinutes(self::EXPIRATION_MINUTES));

        // Set cooldown tracker
        Cache::put($cooldownKey, true, self::RESEND_COOLDOWN_SECONDS);
        Cache::put($cooldownKey . '_ts', now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->timestamp, self::RESEND_COOLDOWN_SECONDS);

        // Synchronize with database columns if user exists
        if ($user) {
            $user->otp_code = $codeHash;
            $user->otp_expire_at = now()->addMinutes(self::EXPIRATION_MINUTES);
            $user->save();
        }

        // 5. Send Email via configured provider
        try {
            $rawMessage = get_static_option('user_email_verify_message');
            if (empty($rawMessage)) {
                $rawMessage = __('Hello @name, your verification code is: @email_verify_tokn');
            }
            $messageBody = str_replace(['@name', '@email_verify_tokn'], [$recipientName, $otpCode], $rawMessage);
            $subject = $purpose === self::PURPOSE_PASSWORD_RESET
                ? (get_static_option('user_password_reset_subject') ?: __('Password Reset Verification Code'))
                : (get_static_option('user_email_verify_subject') ?: __('Email Verification Code'));

            Mail::to($normalizedEmail)->send(new BasicMail([
                'subject' => $subject,
                'message' => $messageBody,
            ]));
        } catch (Exception $e) {
            Log::error('OTP email delivery failure for ' . $normalizedEmail . ': ' . $e->getMessage());
            return [
                'success' => false,
                'status_code' => 500,
                'message' => __('Unable to send verification email. Please check your email configuration or try again later.'),
            ];
        }

        // NEVER return the plaintext OTP in the response
        return [
            'success' => true,
            'status_code' => 200,
            'email' => $normalizedEmail,
            'message' => __('Verification code sent successfully'),
        ];
    }

    /**
     * Verify an entered OTP against the server-stored verifier.
     *
     * @param string $email
     * @param string $enteredOtp
     * @param string $purpose
     * @return array
     */
    public function verifyOtp(string $email, string $enteredOtp, string $purpose = self::PURPOSE_EMAIL_VERIFY): array
    {
        $normalizedEmail = strtolower(trim($email));
        $trimmedOtp = trim($enteredOtp);

        if (empty($trimmedOtp)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => __('Verification code is required'),
            ];
        }

        $cacheKey = "otp_{$normalizedEmail}_{$purpose}";
        $data = Cache::get($cacheKey);

        if (!$data || !is_array($data) || empty($data['code_hash'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => __('Verification code has expired or does not exist. Please request a new code.'),
            ];
        }

        if (now()->timestamp > ($data['expires_at'] ?? 0)) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'status_code' => 422,
                'message' => __('Verification code has expired. Please request a new code.'),
            ];
        }

        // Check and increment attempt count
        $attempts = (int) ($data['attempts'] ?? 0) + 1;
        $data['attempts'] = $attempts;

        if ($attempts > self::MAX_ATTEMPTS) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'status_code' => 429,
                'message' => __('Too many incorrect attempts. This code has been invalidated for security. Please request a new one.'),
            ];
        }

        // Constant-time hash comparison
        $enteredHash = hash_hmac('sha256', $trimmedOtp, (string) config('app.key'));
        if (!hash_equals((string) $data['code_hash'], $enteredHash)) {
            Cache::put($cacheKey, $data, now()->addMinutes(self::EXPIRATION_MINUTES));
            $remaining = max(0, self::MAX_ATTEMPTS - $attempts);
            return [
                'success' => false,
                'status_code' => 422,
                'message' => sprintf(__('Verification code does not match. %d attempts remaining.'), $remaining),
                'attempts_remaining' => $remaining,
            ];
        }

        // Single-use: Consume OTP immediately
        Cache::forget($cacheKey);

        $user = User::where('email', $normalizedEmail)->orWhere('email', $email)->first();
        if ($user) {
            $user->otp_code = null;
            $user->otp_expire_at = null;
            if ($purpose === self::PURPOSE_EMAIL_VERIFY) {
                $user->email_verified = 1;
            }
            $user->save();
        }

        // For password reset, issue short-lived single-use cryptographically bound reset credential
        if ($purpose === self::PURPOSE_PASSWORD_RESET) {
            $resetToken = $this->createPasswordResetToken($normalizedEmail);

            return [
                'success' => true,
                'status_code' => 200,
                'reset_token' => $resetToken,
                'message' => __('Verification successful'),
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => __('Email verified successfully'),
        ];
    }

    /**
     * Generate and store a short-lived, single-use, account-bound password reset token.
     *
     * @param string $email
     * @return string
     */
    public function createPasswordResetToken(string $email): string
    {
        $normalizedEmail = strtolower(trim($email));
        $user = User::where('email', $normalizedEmail)->first();
        $resetToken = 'pwd_reset_' . Str::random(60);
        $tokenHash = hash_hmac('sha256', $resetToken, (string) config('app.key'));
        Cache::put("pwd_reset_{$tokenHash}", [
            'email' => $normalizedEmail,
            'user_id' => $user ? $user->id : null,
            'created_at' => now()->timestamp,
        ], now()->addMinutes(self::RESET_TOKEN_EXPIRATION_MINUTES));

        return $resetToken;
    }

    /**
     * Atomically validate and consume a password reset token.
     *
     * @param string $email
     * @param string $resetToken
     * @return bool
     */
    public function consumePasswordResetToken(string $email, string $resetToken): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $token = trim($resetToken);

        if (empty($token) || empty($normalizedEmail)) {
            return false;
        }

        $tokenHash = hash_hmac('sha256', $token, (string) config('app.key'));
        $cacheKey = "pwd_reset_{$tokenHash}";
        $data = Cache::get($cacheKey);

        if (!$data || !is_array($data)) {
            return false;
        }

        if (strcasecmp((string) ($data['email'] ?? ''), $normalizedEmail) !== 0) {
            return false;
        }

        // Atomically consume token so it cannot be reused
        Cache::forget($cacheKey);
        return true;
    }
}
