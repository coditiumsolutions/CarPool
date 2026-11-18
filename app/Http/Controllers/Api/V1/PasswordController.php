<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\VerificationCodeMail;
use App\Services\AuthService;
use App\Services\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

/** Password reset by 6-digit code, delivered by email (no SMS provider yet). */
class PasswordController extends ApiController
{
    private const PURPOSE = 'password_reset';

    public function __construct(private readonly AuthService $auth, private readonly VerificationCodeService $codes)
    {
    }

    /** Always answers the same way so accounts cannot be enumerated. */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:150']]);
        $user = $this->auth->findByLogin($data['login']);

        if ($user && $user->isActive() && ! $this->codes->onCooldown($user, self::PURPOSE)) {
            $code = $this->codes->issue($user, self::PURPOSE);

            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new VerificationCodeMail($code, VerificationCodeService::EXPIRY_MINUTES));
                } catch (\Throwable $e) {
                    Log::error('Password reset email failed: '.$e->getMessage());
                }
            } else {
                Log::warning("Password reset requested for user {$user->id} who has no email; no SMS provider is configured.");
            }
        }

        return $this->ok(null, 'If an account exists, a 6-digit code has been sent to its email address.');
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $this->auth->findByLogin($data['login']);

        if (! $user || ! $user->isActive() || ! $this->codes->verify($user, self::PURPOSE, $data['code'])) {
            return response()->json(['success' => false, 'message' => 'The code is invalid or has expired.'], 422);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete(); // sign out every device

        return $this->ok(null, 'Password reset. Please log in with your new password.');
    }
}
