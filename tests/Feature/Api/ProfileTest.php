<?php

namespace Tests\Feature\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesData;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use MakesData;

    public function test_passenger_can_upgrade_to_both_and_then_add_a_vehicle(): void
    {
        $user = $this->user('passenger');
        $vehicle = ['vehicle_type' => 'Car', 'registration_no' => 'ABC-1', 'total_seats' => 4];

        $this->as($user)->postJson('/api/v1/vehicles', $vehicle)->assertStatus(403);

        $this->as($user)->putJson('/api/v1/profile', ['user_type' => 'both'])
            ->assertOk()->assertJsonPath('data.user_type', 'both')->assertJsonPath('success', true);

        $this->as($user->refresh())->postJson('/api/v1/vehicles', $vehicle)->assertCreated();
    }

    public function test_driver_can_upgrade_to_both(): void
    {
        $this->as($this->user('driver'))->putJson('/api/v1/profile', ['user_type' => 'both'])
            ->assertOk()->assertJsonPath('data.user_type', 'both');
    }

    public function test_downgrade_and_admin_values_are_rejected(): void
    {
        $both = $this->user('both');

        foreach (['passenger', 'driver', 'admin', 'nonsense'] as $value) {
            $this->as($both)->putJson('/api/v1/profile', ['user_type' => $value])
                ->assertStatus(422)->assertJsonValidationErrors('user_type');
        }
        $this->assertSame('both', $both->refresh()->user_type);
    }

    public function test_admin_account_type_cannot_be_changed(): void
    {
        $admin = $this->user('admin');

        $this->as($admin)->putJson('/api/v1/profile', ['user_type' => 'both'])->assertStatus(422);
        $this->assertSame('admin', $admin->refresh()->user_type);
    }

    public function test_authorization_failures_return_a_clear_message(): void
    {
        $passenger = $this->user('passenger');
        $driver = $this->user('driver');

        $this->as($passenger)->postJson('/api/v1/vehicles', [])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only driver accounts can add vehicles. Switch your account type to both in your profile.');

        $this->as($driver)->postJson('/api/v1/bookings', [])
            ->assertStatus(403)->assertJsonPath('success', false);
        $this->assertStringContainsString('passenger accounts', $this->getJsonMessage($driver, 'POST', '/api/v1/bookings'));
    }

    private function getJsonMessage($user, string $method, string $url): string
    {
        return $this->as($user)->json($method, $url)->json('message');
    }

    public function test_emergency_contact_is_saved_and_returned(): void
    {
        $user = $this->user();

        $this->as($user)->putJson('/api/v1/profile', ['emergency_contact_name' => 'Ahmed', 'emergency_contact_phone' => '03211234567'])
            ->assertOk()
            ->assertJsonPath('data.emergency_contact_name', 'Ahmed')
            ->assertJsonPath('data.emergency_contact_phone', '03211234567');

        $this->as($user)->getJson('/api/v1/user')->assertJsonPath('data.emergency_contact_name', 'Ahmed');
        $this->as($user)->putJson('/api/v1/profile', ['emergency_contact_phone' => '123'])->assertStatus(422);
    }

    public function test_profile_photo_upload_returns_a_full_url(): void
    {
        Storage::fake('public');
        $user = $this->user();

        $response = $this->as($user)->post('/api/v1/profile/photo', ['photo' => $this->pngFile('me.png')], ['Accept' => 'application/json'])
            ->assertOk();

        $url = $response->json('data.profile_image');
        $this->assertStringStartsWith('http', $url);
        $this->assertStringContainsString('/storage/profile-photos/', $url);
        Storage::disk('public')->assertExists('profile-photos/'.basename($url));
        $this->as($user)->getJson('/api/v1/user')->assertJsonPath('data.profile_image', $url);

        // A second upload replaces the first file.
        $this->as($user->refresh())->post('/api/v1/profile/photo', ['image' => $this->pngFile('new.png')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing('profile-photos/'.basename($url));
    }

    public function test_photo_must_be_an_image(): void
    {
        Storage::fake('public');

        $this->as($this->user())->post('/api/v1/profile/photo', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_date_formats_are_consistent(): void
    {
        $driver = $this->user('driver', ['date_of_birth' => '1990-05-15']);
        $ride = $this->ride($driver, $this->vehicle($driver), ['departure_datetime' => now()->addDay()]);

        $this->as($driver)->getJson('/api/v1/user')->assertJsonPath('data.date_of_birth', '1990-05-15');

        // Datetimes are ISO-8601 in UTC with a trailing Z.
        $this->as($this->user())->getJson("/api/v1/rides/{$ride->id}")->assertOk();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $this->as($this->user())->getJson("/api/v1/rides/{$ride->id}")->json('data.departure_datetime'));
    }
}
