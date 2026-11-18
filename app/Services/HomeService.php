<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\City;
use App\Models\Ride;
use App\Models\User;

/** Everything the mobile home screen needs in one call. */
class HomeService
{
    public function summary(User $user): array
    {
        $upcoming = fn () => Ride::query()
            ->where('ride_status', Ride::STATUS_SCHEDULED)
            ->where('available_seats', '>', 0)
            ->where('departure_datetime', '>', now());

        $with = ['driver' => fn ($q) => $q->publicProfile(), 'fromCity:id,city_name', 'toCity:id,city_name'];

        $nearby = $upcoming()->with($with)
            ->when($user->city_id, fn ($q, $city) => $q->where('from_city_id', $city))
            ->orderBy('departure_datetime')->limit(10)->get();

        if ($nearby->isEmpty() && $user->city_id) {
            $nearby = $upcoming()->with($with)->orderBy('departure_datetime')->limit(10)->get();
        }

        $routes = $upcoming()->whereNotNull('from_city_id')->whereNotNull('to_city_id')
            ->selectRaw('from_city_id, to_city_id, COUNT(*) AS ride_count, MIN(price_per_seat) AS min_price')
            ->groupBy('from_city_id', 'to_city_id')->orderByDesc('ride_count')->limit(6)->get();
        $cities = City::whereIn('id', $routes->pluck('from_city_id')->merge($routes->pluck('to_city_id')))->pluck('city_name', 'id');

        return [
            'nearby_rides' => $nearby,
            'popular_routes' => $routes->map(fn ($r) => [
                'from_city_id' => (int) $r->from_city_id,
                'to_city_id' => (int) $r->to_city_id,
                'from' => $cities[$r->from_city_id] ?? null,
                'to' => $cities[$r->to_city_id] ?? null,
                'ride_count' => (int) $r->ride_count,
                'min_price' => (float) $r->min_price,
            ])->values(),
            'recent_activity' => $user->notifications()->latest('created_at')->latest('id')->limit(10)->get(),
            'active_rides' => $this->activeRides($user, $with),
        ];
    }

    /** Rides the user is part of that are scheduled or in progress ("role" says how). */
    private function activeRides(User $user, array $with)
    {
        $active = [Ride::STATUS_SCHEDULED, Ride::STATUS_STARTED];

        $asDriver = $user->rides()->with($with)->whereIn('ride_status', $active)->orderBy('departure_datetime')->get()
            ->each(fn ($r) => $r->setAttribute('role', 'driver'));

        $rideIds = Booking::where('passenger_id', $user->id)
            ->whereIn('booking_status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->whereNotNull('ride_id')->pluck('ride_id');

        $asPassenger = Ride::with($with)->whereIn('id', $rideIds)->whereIn('ride_status', $active)->orderBy('departure_datetime')->get()
            ->each(fn ($r) => $r->setAttribute('role', 'passenger'));

        return $asDriver->concat($asPassenger)->sortBy('departure_datetime')->values();
    }
}
