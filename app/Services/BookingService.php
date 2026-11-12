<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingPassenger;
use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /** Passenger books seats on a published ride. All steps run in one transaction. */
    public function bookRide(User $passenger, int $rideId, int $seats, array $passengers = []): Booking
    {
        if (! $passenger->isPassenger()) {
            throw new HttpException(403, 'Only passenger accounts can book rides.');
        }

        return DB::transaction(function () use ($passenger, $rideId, $seats, $passengers) {
            // Row lock prevents two people taking the last seat at the same time.
            $ride = Ride::whereKey($rideId)->lockForUpdate()->first();

            if (! $ride) {
                throw new HttpException(404, 'Ride not found.');
            }
            if ($ride->driver_id === $passenger->id) {
                throw new HttpException(422, 'You cannot book your own ride.');
            }
            if ($ride->ride_status !== Ride::STATUS_SCHEDULED || $ride->departure_datetime->isPast()) {
                throw new HttpException(422, 'This ride is no longer open for booking.');
            }
            if ($seats > $ride->available_seats) {
                throw new HttpException(422, "Only {$ride->available_seats} seat(s) available.");
            }
            if ($ride->gender_preference !== 'any' && $passenger->gender !== $ride->gender_preference) {
                throw new HttpException(422, "This ride is for {$ride->gender_preference} passengers only.");
            }
            if (Booking::where('ride_id', $ride->id)->where('passenger_id', $passenger->id)
                ->whereIn('booking_status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])->exists()) {
                throw new HttpException(409, 'You already have an active booking on this ride.');
            }

            $booking = Booking::create([
                'ride_id' => $ride->id,
                'passenger_id' => $passenger->id,
                'booking_date' => now(),
                'total_seats' => $seats,
                'price_per_seat' => $ride->price_per_seat,
                'total_amount' => round($ride->price_per_seat * $seats, 2),
                'payment_status' => 'pending',
                'booking_status' => Booking::STATUS_PENDING,
            ]);

            $ride->decrement('available_seats', $seats);
            $this->savePassengers($booking, $passenger, $passengers);

            $this->notifications->notify(
                $ride->driver_id,
                'New Booking',
                "{$passenger->name} booked {$seats} seat(s) on your ride to {$ride->to_location}.",
                'booking'
            );

            return $booking->load('passengers', 'ride');
        });
    }

    /** Passenger accepts a driver's offer on their ride request. */
    public function acceptOffer(User $passenger, int $offerId): Booking
    {
        return DB::transaction(function () use ($passenger, $offerId) {
            $offer = RideOffer::whereKey($offerId)->lockForUpdate()->first();
            if (! $offer) {
                throw new HttpException(404, 'Offer not found.');
            }

            $request = RideRequest::whereKey($offer->request_id)->lockForUpdate()->first();

            if ($request->passenger_id !== $passenger->id) {
                throw new HttpException(403, 'This is not your ride request.');
            }
            if ($request->request_status !== 'open' || $offer->offer_status !== 'pending') {
                throw new HttpException(409, 'This offer can no longer be accepted.');
            }

            $booking = Booking::create([
                'ride_offer_id' => $offer->id,
                'passenger_id' => $passenger->id,
                'booking_date' => now(),
                'total_seats' => $offer->offered_seats,
                'price_per_seat' => round($offer->offered_price / max($offer->offered_seats, 1), 2),
                'total_amount' => $offer->offered_price,
                'payment_status' => 'pending',
                'booking_status' => Booking::STATUS_CONFIRMED,
            ]);

            $offer->update(['offer_status' => 'accepted']);
            RideOffer::where('request_id', $request->id)->where('id', '!=', $offer->id)
                ->where('offer_status', 'pending')->update(['offer_status' => 'rejected']);
            $request->update(['request_status' => 'accepted']);

            $this->savePassengers($booking, $passenger, []);

            $this->notifications->notify(
                $offer->driver_id,
                'Offer Accepted',
                "{$passenger->name} accepted your offer for {$request->from_location} to {$request->to_location}.",
                'booking'
            );

            return $booking->load('passengers', 'rideOffer');
        });
    }

    /** Driver confirms a pending booking on their ride. */
    public function confirm(Booking $booking, User $actor): Booking
    {
        $this->assertDriverOrAdmin($booking, $actor);

        if ($booking->booking_status !== Booking::STATUS_PENDING) {
            throw new HttpException(409, 'Only pending bookings can be confirmed.');
        }

        $booking->update(['booking_status' => Booking::STATUS_CONFIRMED]);
        $this->notifications->notify($booking->passenger_id, 'Booking Confirmed', "Your booking #{$booking->id} has been confirmed.", 'booking');

        return $booking;
    }

    /** Cancel by the passenger, the driver, or an admin. Restores seats and records refund. */
    public function cancel(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $isPassenger = $booking->passenger_id === $actor->id;

            if (! $isPassenger && ! $this->isDriverOrAdmin($booking, $actor)) {
                throw new HttpException(403, 'You cannot cancel this booking.');
            }
            if (! in_array($booking->booking_status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
                throw new HttpException(409, 'This booking can no longer be cancelled.');
            }

            if ($booking->ride_id) {
                $ride = Ride::whereKey($booking->ride_id)->lockForUpdate()->first();
                if (in_array($ride->ride_status, [Ride::STATUS_STARTED, Ride::STATUS_COMPLETED], true)) {
                    throw new HttpException(409, 'The ride has already started.');
                }
                $ride->update(['available_seats' => min($ride->total_seats, $ride->available_seats + $booking->total_seats)]);
            }

            // Refund everything the passenger has paid.
            $refund = 0.0;
            foreach ($booking->payments()->where('payment_status', 'completed')->get() as $payment) {
                $refund += (float) $payment->amount;
                $payment->update(['payment_status' => 'refunded', 'refund_amount' => $payment->amount]);
            }

            $booking->update([
                'booking_status' => Booking::STATUS_CANCELLED,
                'payment_status' => $refund > 0 ? 'refunded' : $booking->payment_status,
            ]);

            BookingCancellation::create([
                'booking_id' => $booking->id,
                'cancelled_by' => $actor->id,
                'reason' => $reason,
                'refund_amount' => $refund,
            ]);

            $other = $isPassenger ? $booking->driverId() : $booking->passenger_id;
            if ($other) {
                $this->notifications->notify($other, 'Booking Cancelled', "Booking #{$booking->id} was cancelled.", 'booking');
            }

            return $booking;
        });
    }

    /** Recalculate paid / partial / pending from completed payments. */
    public function refreshPaymentStatus(Booking $booking): void
    {
        $paid = (float) $booking->payments()->where('payment_status', 'completed')->sum('amount');
        $status = $paid <= 0 ? 'pending' : ($paid + 0.001 >= (float) $booking->total_amount ? 'paid' : 'partial');
        $booking->update(['payment_status' => $status]);
    }

    public function isDriverOrAdmin(Booking $booking, User $user): bool
    {
        return $user->isAdmin() || $booking->driverId() === $user->id;
    }

    private function assertDriverOrAdmin(Booking $booking, User $user): void
    {
        if (! $this->isDriverOrAdmin($booking, $user)) {
            throw new HttpException(403, 'Only the driver can do this.');
        }
    }

    private function savePassengers(Booking $booking, User $owner, array $passengers): void
    {
        $rows = $passengers ?: [['passenger_name' => $owner->name, 'passenger_phone' => $owner->phone]];

        foreach ($rows as $row) {
            BookingPassenger::create([
                'booking_id' => $booking->id,
                'passenger_name' => $row['passenger_name'] ?? $row['name'],
                'passenger_phone' => $row['passenger_phone'] ?? $row['phone'] ?? null,
            ]);
        }
    }
}
