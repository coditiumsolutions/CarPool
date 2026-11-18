<?php

namespace Tests\Feature\Api;

use App\Models\Ride;
use Tests\Support\MakesData;
use Tests\TestCase;

/** The original v1 behaviour must keep working: the Flutter app already depends on it. */
class ExistingFlowRegressionTest extends TestCase
{
    use MakesData;

    public function test_book_confirm_pay_ride_review_flow_still_works(): void
    {
        $driver = $this->user('driver');
        $pax = $this->user('passenger');
        $ride = $this->ride($driver, $this->vehicle($driver));

        $id = $this->as($pax)->postJson('/api/v1/bookings', ['ride_id' => $ride->id, 'seats' => 2])
            ->assertCreated()->assertJsonPath('data.total_amount', '9000.00')->assertJsonStructure(['data' => ['id', 'passengers', 'ride', 'booking_status', 'payment_status']])->json('data.id');
        $this->assertSame(2, Ride::find($ride->id)->available_seats);

        $this->as($driver)->postJson("/api/v1/bookings/$id/confirm")->assertOk()->assertJsonPath('data.booking_status', 'confirmed');
        $pid = $this->as($pax)->postJson("/api/v1/bookings/$id/payments", ['payment_method' => 'Cash', 'amount' => 9000])->assertCreated()->json('data.id');
        $this->as($driver)->postJson("/api/v1/payments/$pid/confirm")->assertOk();
        $this->as($pax)->getJson("/api/v1/bookings/$id")->assertJsonPath('data.payment_status', 'paid');

        $this->as($driver)->postJson("/api/v1/rides/{$ride->id}/start")->assertOk();
        $this->as($driver)->postJson("/api/v1/rides/{$ride->id}/complete")->assertOk();
        $this->as($pax)->postJson("/api/v1/bookings/$id/reviews", ['rating' => 5])->assertCreated();
        $this->as($pax)->getJson("/api/v1/bookings/$id")->assertJsonPath('data.booking_status', 'completed')->assertJsonPath('data.has_reviewed', true);
        $this->as($pax)->getJson("/api/v1/users/{$driver->id}/reviews")->assertJsonPath('summary.average_rating', 5);
    }

    public function test_cancel_restores_seats_and_login_register_still_work(): void
    {
        $driver = $this->user('driver');
        $pax = $this->user('passenger');
        $ride = $this->ride($driver, $this->vehicle($driver));
        $id = $this->booking($ride, $pax, 'pending', ['total_seats' => 2, 'total_amount' => 9000])->id;
        $ride->update(['available_seats' => 2]);

        $this->as($pax)->postJson("/api/v1/bookings/$id/cancel", ['reason' => 'x'])->assertOk()->assertJsonPath('data.booking_status', 'cancelled')->assertJsonPath('data.cancellation.reason', 'x');
        $this->assertSame(4, $ride->refresh()->available_seats);

        $this->postJson('/api/v1/register', ['name' => 'New', 'phone' => '03111111111', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'user_type' => 'admin'])->assertStatus(422);
        $this->postJson('/api/v1/register', ['name' => 'New', 'phone' => '03111111111', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'user_type' => 'passenger'])
            ->assertCreated()->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name', 'email', 'phone', 'user_type', 'gender', 'date_of_birth', 'profile_image', 'city_id', 'address', 'is_verified', 'status']]]);
        $this->postJson('/api/v1/login', ['login' => '03111111111', 'password' => 'Secret123'])->assertOk()->assertJsonPath('data.user.user_type', 'passenger');
        $this->postJson('/api/v1/login', ['login' => '03111111111', 'password' => 'bad'])->assertStatus(401);
    }
}
