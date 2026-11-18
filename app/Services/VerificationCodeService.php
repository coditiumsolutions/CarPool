<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;

/** 6-digit one-time codes (stored hashed, expire, limited attempts, resend cooldown). */
class VerificationCodeService
{
    public const EXPIRY_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 60;

    /** True if a code was issued for this user/purpose less than RESEND_SECONDS ago. */
    public function onCooldown(User $user, string $purpose): bool
    {
        return VerificationCode::where('user_id', $user->id)->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds(self::RESEND_SECONDS))->exists();
    }

    /** Create a new code, invalidating older unused ones. Returns the plain code (send it, never store it). */
    public function issue(User $user, string $purpose): string
    {
        VerificationCode::where('user_id', $user->id)->where('purpose', $purpose)->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => $this->hash($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        return $code;
    }

    /** Check and consume a code. Wrong guesses count towards MAX_ATTEMPTS. */
    public function verify(User $user, string $purpose, string $code): bool
    {
        $record = VerificationCode::where('user_id', $user->id)->where('purpose', $purpose)
            ->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();

        if (! $record || $record->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals($record->code_hash, $this->hash($code))) {
            $record->increment('attempts');

            return false;
        }

        $record->update(['used_at' => now()]);

        return true;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }
}
