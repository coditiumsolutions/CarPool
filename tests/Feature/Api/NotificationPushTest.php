<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesData;
use Tests\TestCase;

class NotificationPushTest extends TestCase
{
    use MakesData;

    private function enableFcm(): string
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // On Windows/XAMPP PHP cannot always find openssl.cnf by itself.
        foreach ([getenv('OPENSSL_CONF'), 'C:/xampp/php/extras/openssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf'] as $cnf) {
            if ($cnf && is_file($cnf)) {
                $options['config'] = $cnf;
                break;
            }
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode(['project_id' => 'ride-test', 'client_email' => 'svc@ride-test.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.fcm.credentials' => $path]);
        Cache::forget('fcm_access_token');

        return $path;
    }

    public function test_device_token_is_registered_once_and_moves_between_users(): void
    {
        [$a, $b] = [$this->user(), $this->user()];
        $payload = ['token' => 'tok-abc:123_xyz', 'platform' => 'android', 'device_name' => 'Pixel'];

        $this->as($a)->postJson('/api/v1/device-tokens', $payload)->assertCreated();
        $this->as($a)->postJson('/api/v1/device-tokens', $payload)->assertOk();
        $this->assertSame(1, DeviceToken::count());

        // Another account signs in on the same phone: the token follows them.
        $this->as($b)->postJson('/api/v1/device-tokens', $payload)->assertOk();
        $this->assertSame($b->id, DeviceToken::first()->user_id);
    }

    public function test_device_token_validation(): void
    {
        $this->as($this->user())->postJson('/api/v1/device-tokens', ['token' => 'x', 'platform' => 'windows'])->assertStatus(422);
        $this->as($this->user())->postJson('/api/v1/device-tokens', ['platform' => 'ios'])->assertStatus(422);
    }

    public function test_device_token_can_be_deleted_on_logout_and_is_idempotent(): void
    {
        $user = $this->user();
        $other = $this->user();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok:1', 'platform' => 'ios']);

        $this->as($other)->deleteJson('/api/v1/device-tokens/tok:1')->assertOk();
        $this->assertSame(1, DeviceToken::count(), 'someone else cannot delete it');

        $this->as($user)->deleteJson('/api/v1/device-tokens/tok:1')->assertOk();
        $this->assertSame(0, DeviceToken::count());
        $this->as($user)->deleteJson('/api/v1/device-tokens/tok:1')->assertOk();
    }

    public function test_booking_creates_notification_with_refs_and_sends_fcm(): void
    {
        $this->enableFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'access-1']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/ride-test/messages/1']),
        ]);

        $driver = $this->user('driver');
        $passenger = $this->user();
        $ride = $this->ride($driver, $this->vehicle($driver));
        DeviceToken::create(['user_id' => $driver->id, 'token' => 'driver-device', 'platform' => 'android']);

        $this->as($passenger)->postJson('/api/v1/bookings', ['ride_id' => $ride->id, 'seats' => 1])->assertCreated();

        $note = Notification::where('user_id', $driver->id)->first();
        $this->assertSame($ride->id, $note->ride_id);
        $this->assertNotNull($note->booking_id);

        Http::assertSent(function (Request $r) use ($note) {
            return str_contains($r->url(), 'projects/ride-test/messages:send')
                && $r->hasHeader('Authorization', 'Bearer access-1')
                && $r['message']['token'] === 'driver-device'
                && $r['message']['notification']['title'] === 'New Booking'
                && $r['message']['data']['notification_id'] === (string) $note->id
                && $r['message']['data']['type'] === 'booking'
                && $r['message']['data']['booking_id'] === (string) $note->booking_id
                && $r['message']['data']['ride_id'] === (string) $note->ride_id
                && ! isset($r['message']['data']['offer_id']);
        });
    }

    public function test_unregistered_tokens_are_removed_and_failures_do_not_break_requests(): void
    {
        $this->enableFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'a']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        $driver = $this->user('driver');
        $ride = $this->ride($driver, $this->vehicle($driver));
        DeviceToken::create(['user_id' => $driver->id, 'token' => 'dead-token', 'platform' => 'android']);

        $this->as($this->user())->postJson('/api/v1/bookings', ['ride_id' => $ride->id, 'seats' => 1])->assertCreated();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_no_push_is_attempted_when_fcm_is_not_configured(): void
    {
        config(['services.fcm.credentials' => null]);
        Http::fake();

        $driver = $this->user('driver');
        $ride = $this->ride($driver, $this->vehicle($driver));
        DeviceToken::create(['user_id' => $driver->id, 'token' => 't', 'platform' => 'android']);

        $this->as($this->user())->postJson('/api/v1/bookings', ['ride_id' => $ride->id, 'seats' => 1])->assertCreated();
        Http::assertNothingSent();
        $this->assertSame(1, Notification::where('user_id', $driver->id)->count());
    }

    public function test_notification_list_exposes_refs_and_can_be_deleted_by_owner_only(): void
    {
        $user = $this->user();
        $note = Notification::create(['user_id' => $user->id, 'title' => 'T', 'message' => 'M', 'notification_type' => 'offer', 'request_id' => 7, 'offer_id' => 9]);

        $this->as($user)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.request_id', 7)->assertJsonPath('data.0.offer_id', 9)
            ->assertJsonPath('data.0.booking_id', null)->assertJsonPath('data.0.ride_id', null);

        $this->as($this->user())->deleteJson("/api/v1/notifications/{$note->id}")->assertStatus(403);
        $this->as($user)->deleteJson("/api/v1/notifications/{$note->id}")->assertOk();
        $this->assertSame(0, Notification::count());
    }

    public function test_offer_and_confirmation_notifications_carry_the_right_ids(): void
    {
        $driver = $this->user('driver');
        $passenger = $this->user();
        $ride = $this->ride($driver, $this->vehicle($driver));
        $booking = $this->booking($ride, $passenger);

        $this->as($driver)->postJson("/api/v1/bookings/{$booking->id}/confirm")->assertOk();

        $note = Notification::where('user_id', $passenger->id)->first();
        $this->assertSame($booking->id, $note->booking_id);
        $this->assertSame($ride->id, $note->ride_id);
    }
}
