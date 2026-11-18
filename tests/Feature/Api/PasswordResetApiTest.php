<?php

namespace Tests\Feature\Api;

use App\Mail\VerificationCodeMail;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Mail;
use Tests\Support\MakesData;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use MakesData;

    private function requestCode(string $login): ?string
    {
        $code = null;
        $this->postJson('/api/v1/forgot-password', ['login' => $login])->assertOk();
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_code_is_emailed_and_resets_the_password(): void
    {
        Mail::fake();
        $user = $this->user('passenger', ['email' => 'amna@test.local']);
        $old = $user->createToken('phone')->plainTextToken;

        $code = $this->requestCode('amna@test.local');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseMissing('verification_codes', ['code_hash' => $code], null); // stored hashed, never plain

        $this->postJson('/api/v1/reset-password', ['login' => 'amna@test.local', 'code' => $code, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/login', ['login' => 'amna@test.local', 'password' => 'NewPass123'])->assertOk();
        $this->assertSame(1, $user->tokens()->count(), 'old sessions are revoked (only the new login token remains)');

        // Single use.
        $this->postJson('/api/v1/reset-password', ['login' => 'amna@test.local', 'code' => $code, 'password' => 'Another123', 'password_confirmation' => 'Another123'])
            ->assertStatus(422);
    }

    public function test_login_by_phone_works_and_unknown_accounts_get_the_same_answer(): void
    {
        Mail::fake();
        $user = $this->user('passenger', ['email' => 'p@test.local']);

        $known = $this->postJson('/api/v1/forgot-password', ['login' => $user->phone])->assertOk()->json();
        $unknown = $this->postJson('/api/v1/forgot-password', ['login' => '03999999999'])->assertOk()->json();

        $this->assertSame($known, $unknown);
        Mail::assertSent(VerificationCodeMail::class, 1);
    }

    public function test_wrong_code_expired_code_and_attempt_limit(): void
    {
        Mail::fake();
        $this->user('passenger', ['email' => 'w@test.local']);
        $code = $this->requestCode('w@test.local');
        $reset = fn (string $c) => $this->postJson('/api/v1/reset-password', ['login' => 'w@test.local', 'code' => $c, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123']);

        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $reset($wrong)->assertStatus(422)->assertJsonPath('message', 'The code is invalid or has expired.');
        }
        $reset($code)->assertStatus(422); // locked after 5 failures

        VerificationCode::query()->update(['attempts' => 0, 'expires_at' => now()->subMinute()]);
        $reset($code)->assertStatus(422); // expired
    }

    public function test_requests_are_throttled_and_resend_has_a_cooldown(): void
    {
        Mail::fake();
        $this->user('passenger', ['email' => 'c@test.local']);

        $this->postJson('/api/v1/forgot-password', ['login' => 'c@test.local'])->assertOk();
        $this->postJson('/api/v1/forgot-password', ['login' => 'c@test.local'])->assertOk();
        Mail::assertSent(VerificationCodeMail::class, 1); // second request inside 60 seconds sends nothing

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/forgot-password', ['login' => 'c@test.local']);
        }
        $this->postJson('/api/v1/forgot-password', ['login' => 'c@test.local'])->assertStatus(429);
    }

    public function test_password_rules_and_blocked_accounts(): void
    {
        Mail::fake();
        $user = $this->user('passenger', ['email' => 'r@test.local']);
        $code = $this->requestCode('r@test.local');

        $this->postJson('/api/v1/reset-password', ['login' => 'r@test.local', 'code' => $code, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/reset-password', ['login' => 'r@test.local', 'code' => 'abc', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $user->update(['status' => 'blocked']);
        $this->postJson('/api/v1/reset-password', ['login' => 'r@test.local', 'code' => $code, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertStatus(422);
    }
}
