<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_ATTEMPTS = 5;

    /** Find a user by email or phone number. */
    public function findByLogin(string $login): ?User
    {
        $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        return User::where($column, $login)->first();
    }

    /**
     * Validate login + password and return the user, or throw a validation error.
     * Rate limited per login + IP.
     */
    public function authenticate(string $login, string $password, string $ip): User
    {
        $key = Str::lower($login).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $user = $this->findByLogin($login);

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['login' => 'These credentials do not match our records.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => 'Your account is not active. Please contact support.']);
        }

        RateLimiter::clear($key);

        return $user;
    }

    /** Issue a Sanctum bearer token for the mobile app. */
    public function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
