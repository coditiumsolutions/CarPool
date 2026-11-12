<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\RideTracking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RideService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly BookingService $bookings,
    ) {
    }

    /** Bookable rides filtered by the passenger's search criteria. */
    public function search(array $f): Builder
    {
        return Ride::query()
            ->with(['driver:id,name,gender,profile_image,is_verified', 'vehicle:id,vehicle_type,make,model,color,model_year', 'fromCity:id,city_name', 'toCity:id,city_name', 'stops'])
            ->where('ride_status', Ride::STATUS_SCHEDULED)
            ->where('available_seats', '>', 0)
            ->where('departure_datetime', '>', now())
            ->when($f['ride_type'] ?? null, fn ($q, $v) => $q->where('ride_type', $v))
            ->when($f['from_city_id'] ?? null, fn ($q, $v) => $q->where('from_city_id', $v))
            ->when($f['to_city_id'] ?? null, fn ($q, $v) => $q->where('to_city_id', $v))
            ->when($f['date'] ?? null, fn ($q, $v) => $q->whereDate('departure_datetime', $v))
            ->when($f['seats'] ?? null, fn ($q, $v) => $q->where('available_seats', '>=', $v))
            ->when($f['max_price'] ?? null, fn ($q, $v) => $q->where('price_per_seat', '<=', $v))
            ->orderBy('departure_datetime');
    }

    public function create(User $driver, array $data): Ride
    {
        if (! $driver->isDriver()) {
            throw new HttpException(403, 'Only driver accounts can publish rides.');
        }

        $vehicle = Vehicle::where('id', $data['vehicle_id'])->where('user_id', $driver->id)->first();
        if (! $vehicle || $vehicle->status !== 'active') {
            throw new HttpException(422, 'Choose one of your active vehicles.');
        }
        if ($data['total_seats'] > $vehicle->total_seats) {
            throw new HttpException(422, "This vehicle has only {$vehicle->total_seats} seat(s).");
        }

        return DB::transaction(function () use ($driver, $data) {
            $stops = $data['stops'] ?? [];
            unset($data['stops']);

            $ride = Ride::create($data + [
                'driver_id' => $driver->id,
                'available_seats' => $data['total_seats'],
                'ride_status' => Ride::STATUS_SCHEDULED,
            ]);

            foreach (array_values($stops) as $i => $stop) {
                $ride->stops()->create($stop + ['stop_order' => $i + 1]);
            }

            return $ride->load('stops', 'vehicle');
        });
    }

    public function cancel(Ride $ride, User $actor, ?string $reason): Ride
    {
        $this->assertOwner($ride, $actor);

        if (! in_array($ride->ride_status, [Ride::STATUS_SCHEDULED], true)) {
            throw new HttpException(409, 'Only scheduled rides can be cancelled.');
        }

        return DB::transaction(function () use ($ride, $actor, $reason) {
            $ride->update(['ride_status' => Ride::STATUS_CANCELLED]);

            // Cancel (and refund) every active booking on the ride.
            foreach ($ride->bookings()->whereIn('booking_status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])->get() as $booking) {
                $this->bookings->cancel($booking, $actor, $reason ?: 'Ride cancelled by driver.');
            }

            return $ride->refresh();
        });
    }

    public function start(Ride $ride, User $actor): Ride
    {
        $this->assertOwner($ride, $actor);

        if ($ride->ride_status !== Ride::STATUS_SCHEDULED) {
            throw new HttpException(409, 'Only scheduled rides can be started.');
        }

        $ride->update(['ride_status' => Ride::STATUS_STARTED]);

        foreach ($ride->bookings()->where('booking_status', Booking::STATUS_CONFIRMED)->pluck('passenger_id') as $id) {
            $this->notifications->notify($id, 'Ride Started', "Your ride to {$ride->to_location} has started.", 'ride');
        }

        return $ride;
    }

    public function complete(Ride $ride, User $actor): Ride
    {
        $this->assertOwner($ride, $actor);

        if ($ride->ride_status !== Ride::STATUS_STARTED) {
            throw new HttpException(409, 'Only started rides can be completed.');
        }

        return DB::transaction(function () use ($ride) {
            $ride->update(['ride_status' => Ride::STATUS_COMPLETED, 'arrival_datetime' => $ride->arrival_datetime ?? now()]);

            $ride->bookings()->where('booking_status', Booking::STATUS_CONFIRMED)->each(function (Booking $b) use ($ride) {
                $b->update(['booking_status' => Booking::STATUS_COMPLETED]);
                $this->notifications->notify($b->passenger_id, 'Ride Completed', "Your ride to {$ride->to_location} is complete. Please leave a review.", 'ride');
            });

            return $ride;
        });
    }

    public function recordLocation(Ride $ride, User $actor, float $lat, float $lng): RideTracking
    {
        $this->assertOwner($ride, $actor);

        if ($ride->ride_status !== Ride::STATUS_STARTED) {
            throw new HttpException(409, 'Location can only be shared while the ride is in progress.');
        }

        return RideTracking::create([
            'ride_id' => $ride->id,
            'driver_id' => $actor->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'recorded_at' => now(),
        ]);
    }

    /** Driver, admin, or a passenger with an active booking may see the live location. */
    public function canViewTracking(Ride $ride, User $user): bool
    {
        return $user->isAdmin()
            || $ride->driver_id === $user->id
            || $ride->bookings()->where('passenger_id', $user->id)
                ->whereIn('booking_status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])->exists();
    }

    private function assertOwner(Ride $ride, User $actor): void
    {
        if ($ride->driver_id !== $actor->id && ! $actor->isAdmin()) {
            throw new HttpException(403, 'This is not your ride.');
        }
    }
}
