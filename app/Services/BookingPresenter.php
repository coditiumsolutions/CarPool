<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One place that defines what a booking looks like in API responses:
 * vehicle, stops, driver (with rating stats), offer route, has_reviewed / my_review.
 */
class BookingPresenter
{
    /** Eager-load definition. $forDriver also loads the passenger with their stats. */
    public function relations(bool $forDriver = false): array
    {
        $vehicle = 'id,vehicle_type,make,model,color,model_year,registration_no,total_seats';

        $relations = [
            'ride.fromCity:id,city_name',
            'ride.toCity:id,city_name',
            'ride.vehicle:'.$vehicle,
            'ride.stops',
            'ride.driver' => fn ($q) => $q->publicProfile(true),
            'rideOffer.request:id,from_location,to_location,from_city_id,to_city_id,departure_datetime,required_seats',
            'rideOffer.request.fromCity:id,city_name',
            'rideOffer.request.toCity:id,city_name',
            'rideOffer.vehicle:'.$vehicle,
            'rideOffer.driver' => fn ($q) => $q->publicProfile(true),
            'passengers',
            'payments',
        ];

        if ($forDriver) {
            $relations['passenger'] = fn ($q) => $q->passengerProfile();
        }

        return $relations;
    }

    /** Load relations on a query (list endpoints). */
    public function query(Builder $query, bool $forDriver = false): Builder
    {
        return $query->with($this->relations($forDriver));
    }

    /** Load relations on a single booking and decorate it for $viewer. */
    public function present(Booking $booking, User $viewer, bool $forDriver = false): Booking
    {
        $booking->load($this->relations($forDriver));
        $this->decorate([$booking], $viewer, $forDriver);

        return $booking;
    }

    /**
     * Add has_reviewed / my_review for the viewer, and (driver view) hide the
     * passenger's phone until the booking is confirmed.
     *
     * @param  iterable<Booking>  $bookings
     */
    public function decorate(iterable $bookings, User $viewer, bool $hidePendingPassengerPhone = false): void
    {
        $bookings = collect($bookings);
        if ($bookings->isEmpty()) {
            return;
        }

        $reviews = Review::where('reviewer_id', $viewer->id)
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->get()->keyBy('booking_id');

        foreach ($bookings as $booking) {
            $review = $reviews->get($booking->id);
            $booking->setAttribute('has_reviewed', $review !== null);
            $booking->setAttribute('my_review', $review ? ['rating' => (int) $review->rating, 'comments' => $review->comments] : null);

            if ($hidePendingPassengerPhone) {
                $this->maskPassengerPhone($booking);
            }
        }
    }

    /** Drivers only see a passenger's phone after confirming the booking. */
    public function maskPassengerPhone(Model $booking): void
    {
        if ($booking->relationLoaded('passenger') && $booking->passenger
            && ! in_array($booking->booking_status, [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED], true)) {
            // Eager loading shares one passenger instance between bookings, so mask a copy.
            $booking->setRelation('passenger', (clone $booking->passenger)->setAttribute('phone', null));
        }
    }
}
