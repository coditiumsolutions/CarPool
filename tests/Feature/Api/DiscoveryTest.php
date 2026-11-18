<?php

namespace Tests\Feature\Api;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\Place;
use Tests\Support\MakesData;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    use MakesData;

    private function rides(): array
    {
        $khi = $this->city('Karachi');
        $lhe = $this->city('Lahore');
        $good = $this->user('driver');
        $bad = $this->user('driver');
        $v1 = $this->vehicle($good);
        $v2 = $this->vehicle($bad);
        $day = now()->addDays(2);

        $morning = $this->ride($good, $v1, ['from_city_id' => $khi->id, 'to_city_id' => $lhe->id, 'from_location' => 'Gulshan-e-Iqbal, Karachi', 'to_location' => 'Johar Town, Lahore', 'departure_datetime' => $day->copy()->setTime(7, 30), 'price_per_seat' => 5000]);
        $evening = $this->ride($bad, $v2, ['from_city_id' => $khi->id, 'to_city_id' => $lhe->id, 'from_location' => 'Clifton, Karachi', 'to_location' => 'DHA, Lahore', 'departure_datetime' => $day->copy()->setTime(18, 0), 'price_per_seat' => 3500]);

        return [$morning, $evening, $good, $bad];
    }

    public function test_text_filters_are_partial_and_case_insensitive(): void
    {
        [$morning, $evening] = $this->rides();
        $me = $this->user();

        $ids = fn (string $qs) => collect($this->as($me)->getJson("/api/v1/rides?$qs")->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$morning->id], $ids('from_location=GULSHAN'));
        $this->assertSame([$evening->id], $ids('to_location=dha'));
        $this->assertSame([$morning->id], $ids('from_location=iqbal&to_location=johar'));
        $this->assertSame([], $ids('from_location=nowhere'));
        $this->assertCount(2, $ids('from_location=karachi'));
        $this->assertSame([], $ids('from_location=%25'), 'wildcards are escaped, not interpreted');
    }

    public function test_departure_after_and_sorting(): void
    {
        [$morning, $evening, $good, $bad] = $this->rides();
        $me = $this->user();
        $get = fn (string $qs) => collect($this->as($me)->getJson("/api/v1/rides?$qs")->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$evening->id], $get('departure_after=12:00'));
        $this->assertCount(2, $get('departure_after=07:00'));
        $this->as($me)->getJson('/api/v1/rides?departure_after=25:00')->assertStatus(422);

        $this->assertSame([$morning->id, $evening->id], $get(''), 'default is earliest first');
        $this->assertSame([$morning->id, $evening->id], $get('sort=earliest'));
        $this->assertSame([$evening->id, $morning->id], $get('sort=price'));

        $b = $this->booking($morning, $me, 'completed');
        $this->review($b, $me, $good, 5);
        $b2 = $this->booking($evening, $me, 'completed');
        $this->review($b2, $me, $bad, 2);
        $this->assertSame([$morning->id, $evening->id], $get('sort=rating'));

        $this->as($me)->getJson('/api/v1/rides?sort=bogus')->assertStatus(422);
    }

    public function test_default_filters_still_hide_past_full_and_cancelled_rides(): void
    {
        $driver = $this->user('driver');
        $v = $this->vehicle($driver);
        $this->ride($driver, $v, ['departure_datetime' => now()->subDay()]);
        $this->ride($driver, $v, ['available_seats' => 0]);
        $this->ride($driver, $v, ['ride_status' => 'cancelled']);
        $open = $this->ride($driver, $v);

        $this->assertSame([$open->id], collect($this->as($this->user())->getJson('/api/v1/rides')->json('data'))->pluck('id')->all());
    }

    public function test_complaints_booking_is_optional_and_category_is_validated(): void
    {
        $driver = $this->user('driver');
        $pax = $this->user();
        $ride = $this->ride($driver, $this->vehicle($driver));
        $booking = $this->booking($ride, $pax);

        $this->as($pax)->postJson('/api/v1/complaints', ['subject' => 'App crash', 'description' => 'It closes', 'category' => 'app_issue'])
            ->assertCreated()->assertJsonPath('data.booking_id', null)->assertJsonPath('data.category', 'app_issue')->assertJsonPath('data.complaint_status', 'open');

        $this->as($pax)->postJson('/api/v1/complaints', ['booking_id' => $booking->id, 'subject' => 'Late', 'description' => 'x'])->assertCreated()->assertJsonPath('data.booking_id', $booking->id);
        $this->as($pax)->postJson('/api/v1/complaints', ['subject' => 'S', 'description' => 'D', 'category' => 'weird'])->assertStatus(422);
        $this->as($this->user())->postJson('/api/v1/complaints', ['booking_id' => $booking->id, 'subject' => 'S', 'description' => 'D'])->assertStatus(403);
        $this->as($pax)->getJson('/api/v1/complaints')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertSame(['open', 'in_progress', 'resolved', 'rejected'], Complaint::STATUSES);
    }

    public function test_dashboard_returns_everything_for_the_home_screen(): void
    {
        [$morning, $evening, $good] = $this->rides();
        $khi = $morning->fromCity;
        $me = $this->user('both', ['city_id' => $khi->id]);
        $mine = $this->ride($me, $this->vehicle($me), ['from_city_id' => $khi->id, 'to_city_id' => $morning->to_city_id]);
        $this->booking($evening, $me, 'confirmed');
        Notification::create(['user_id' => $me->id, 'title' => 'Hi', 'message' => 'There']);

        $r = $this->as($me)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonStructure(['data' => ['nearby_rides', 'popular_routes', 'recent_activity', 'active_rides']]);

        $this->assertCount(3, $r->json('data.nearby_rides'));
        $this->assertArrayHasKey('rating_avg', $r->json('data.nearby_rides.0.driver'));

        $route = $r->json('data.popular_routes.0');
        $this->assertEquals(['Karachi', 'Lahore', 3, 3500], [$route['from'], $route['to'], $route['ride_count'], $route['min_price']]);
        $this->assertSame('Hi', $r->json('data.recent_activity.0.title'));

        $roles = collect($r->json('data.active_rides'))->pluck('role', 'id')->all();
        $this->assertSame([$mine->id => 'driver', $evening->id => 'passenger'], $roles);
    }

    public function test_places_are_filtered_by_city_and_text(): void
    {
        $khi = $this->city('Karachi');
        $lhe = $this->city('Lahore');
        foreach ([[$khi, 'Gulshan-e-Iqbal'], [$khi, 'Gulistan-e-Jauhar'], [$khi, 'Clifton'], [$lhe, 'Gulberg']] as [$c, $n]) {
            Place::create(['city_id' => $c->id, 'name' => $n]);
        }
        $me = $this->user();

        $this->as($me)->getJson("/api/v1/places?city_id={$khi->id}")->assertJsonCount(3, 'data');
        $this->as($me)->getJson("/api/v1/places?city_id={$khi->id}&q=GUL")->assertJsonCount(2, 'data');
        $this->as($me)->getJson('/api/v1/places?q=gulb')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Gulberg');
        $this->as($me)->getJson('/api/v1/places?city_id=999')->assertStatus(422);
    }
}
