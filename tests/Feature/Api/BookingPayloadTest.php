<?php

namespace Tests\Feature\Api;

use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\RideRequest;
use Tests\Support\MakesData;
use Tests\TestCase;

class BookingPayloadTest extends TestCase
{
    use MakesData;

    /** @return array{0:\App\Models\User,1:\App\Models\User,2:Ride} */
    private function scenario(): array
    {
        $city = $this->city();
        $driver = $this->user('driver', ['name' => 'Dan Driver']);
        $passenger = $this->user('passenger');
        $ride = $this->ride($driver, $this->vehicle($driver, ['registration_no' => 'LEA-123']), ['from_city_id' => $city->id, 'to_city_id' => $city->id]);
        $ride->stops()->create(['stop_name' => 'Sukkur', 'stop_order' => 1]);

        return [$driver, $passenger, $ride];
    }

    public function test_booking_payload_includes_vehicle_stops_and_driver(): void
    {
        [$driver, $passenger, $ride] = $this->scenario();

        $this->as($passenger)->postJson('/api/v1/bookings', ['ride_id' => $ride->id, 'seats' => 1])
            ->assertCreated()
            ->assertJsonPath('data.ride.vehicle.registration_no', 'LEA-123')
            ->assertJsonPath('data.ride.vehicle.total_seats', 4)
            ->assertJsonPath('data.ride.stops.0.stop_name', 'Sukkur')
            ->assertJsonPath('data.ride.driver.id', $driver->id)
            ->assertJsonPath('data.ride.driver.phone', $driver->phone)
            ->assertJsonPath('data.has_reviewed', false)
            ->assertJsonPath('data.my_review', null);

        $this->as($passenger)->getJson('/api/v1/bookings')
            ->assertOk()->assertJsonPath('data.0.ride.vehicle.make', 'Toyota')->assertJsonPath('data.0.has_reviewed', false);
    }

    public function test_has_reviewed_and_my_review_reflect_the_viewer(): void
    {
        [$driver, $passenger, $ride] = $this->scenario();
        $booking = $this->booking($ride, $passenger, 'completed');
        $this->review($booking, $passenger, $driver, 4);

        $this->as($passenger)->getJson("/api/v1/bookings/{$booking->id}")
            ->assertJsonPath('data.has_reviewed', true)
            ->assertJsonPath('data.my_review.rating', 4);

        // The driver has not reviewed the passenger yet.
        $this->as($driver)->getJson("/api/v1/bookings/{$booking->id}")
            ->assertJsonPath('data.has_reviewed', false)->assertJsonPath('data.my_review', null);
    }

    public function test_driver_rating_stats_are_on_every_driver_object(): void
    {
        [$driver, $passenger, $ride] = $this->scenario();
        $b1 = $this->booking($ride, $passenger, 'completed');
        $b2 = $this->booking($ride, $this->user(), 'completed');
        $this->review($b1, $passenger, $driver, 5);
        $this->review($b2, $b2->passenger, $driver, 4);
        $this->ride($driver, $ride->vehicle, ['ride_status' => 'completed']);
        $expected = ['rating_avg' => 4.5, 'reviews_count' => 2, 'completed_rides_count' => 1, 'member_since' => (int) $driver->created_at->format('Y')];

        $search = $this->as($passenger)->getJson('/api/v1/rides')->assertOk();
        foreach ($expected as $key => $value) {
            $search->assertJsonPath("data.0.driver.$key", $value);
        }
        $this->as($passenger)->getJson("/api/v1/rides/{$ride->id}")->assertJsonPath('data.driver.rating_avg', 4.5)->assertJsonPath('data.driver.reviews_count', 2);
        $this->as($passenger)->getJson('/api/v1/bookings')->assertJsonPath('data.0.ride.driver.rating_avg', 4.5);

        // A driver with no reviews is clearly "new": 0 rating, 0 reviews.
        $new = $this->user('driver');
        $this->ride($new, $this->vehicle($new), ['available_seats' => 3]);
        $drivers = collect($this->as($passenger)->getJson('/api/v1/rides')->json('data'))->keyBy('driver.id');
        $this->assertSame(0.0, (float) $drivers[$new->id]['driver']['rating_avg']);
        $this->assertSame(0, $drivers[$new->id]['driver']['reviews_count']);
    }

    private function offerScenario(): array
    {
        $city = $this->city('Lahore');
        $driver = $this->user('driver');
        $passenger = $this->user('both');
        $vehicle = $this->vehicle($driver, ['registration_no' => 'OFF-1']);
        $request = RideRequest::create([
            'passenger_id' => $passenger->id, 'ride_type' => 'intercity', 'from_city_id' => $city->id, 'to_city_id' => $city->id,
            'from_location' => 'G-11 Islamabad', 'to_location' => 'Johar Town', 'departure_datetime' => now()->addDays(3),
            'required_seats' => 2, 'max_budget' => 9000, 'request_status' => 'open',
        ]);
        $offer = RideOffer::create(['request_id' => $request->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'offered_price' => 8500, 'offered_seats' => 2]);

        return [$driver, $passenger, $request, $offer];
    }

    public function test_offer_booking_shows_route_vehicle_and_driver(): void
    {
        [$driver, $passenger, $request, $offer] = $this->offerScenario();

        $this->as($driver)->getJson('/api/v1/my/offers')->assertOk()->assertJsonPath('data.0.driver.id', $driver->id)->assertJsonPath('data.0.driver.rating_avg', 0);
        $this->as($passenger)->getJson("/api/v1/ride-requests/{$request->id}/offers")
            ->assertOk()->assertJsonPath('data.0.driver.reviews_count', 0)->assertJsonPath('data.0.vehicle.registration_no', 'OFF-1');

        $accepted = $this->as($passenger)->postJson("/api/v1/ride-offers/{$offer->id}/accept")
            ->assertCreated()
            ->assertJsonPath('data.ride_offer.request.from_location', 'G-11 Islamabad')
            ->assertJsonPath('data.ride_offer.request.to_location', 'Johar Town')
            ->assertJsonPath('data.ride_offer.request.required_seats', 2)
            ->assertJsonPath('data.ride_offer.request.from_city.city_name', 'Lahore')
            ->assertJsonPath('data.ride_offer.vehicle.registration_no', 'OFF-1')
            ->assertJsonPath('data.ride_offer.driver.id', $driver->id)
            ->assertJsonPath('data.has_reviewed', false);

        $this->as($passenger)->getJson('/api/v1/bookings/'.$accepted->json('data.id'))
            ->assertJsonPath('data.ride_offer.request.to_location', 'Johar Town');
    }

    public function test_driver_sees_passenger_stats_and_phone_only_after_confirmation(): void
    {
        [$driver, $passenger, $ride] = $this->scenario();
        $done = $this->booking($ride, $passenger, 'completed');
        $this->review($done, $driver, $passenger, 5);
        $pending = $this->booking($ride, $passenger, 'pending');

        $list = $this->as($driver)->getJson('/api/v1/driver/bookings')->assertOk();
        $rows = collect($list->json('data'))->keyBy('id');

        $this->assertNull($rows[$pending->id]['passenger']['phone'], 'phone must be hidden while pending');
        $this->assertSame($passenger->phone, $rows[$done->id]['passenger']['phone']);
        $this->assertSame(5.0, (float) $rows[$done->id]['passenger']['rating_avg']);
        $this->assertSame(1, $rows[$done->id]['passenger']['reviews_count']);
        $this->assertSame(1, $rows[$done->id]['passenger']['completed_bookings_count']);
        $this->assertArrayHasKey('is_verified', $rows[$done->id]['passenger']);
        $this->assertArrayHasKey('member_since', $rows[$done->id]['passenger']);

        $this->as($driver)->postJson("/api/v1/bookings/{$pending->id}/confirm")->assertOk()->assertJsonPath('data.passenger.phone', $passenger->phone);

        $perRide = $this->as($driver)->getJson("/api/v1/rides/{$ride->id}/bookings")->assertOk();
        $this->assertSame($passenger->phone, collect($perRide->json('data'))->firstWhere('id', $pending->id)['passenger']['phone']);
        $this->assertArrayHasKey('completed_bookings_count', $perRide->json('data.0.passenger'));
    }
}
