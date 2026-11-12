<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\RideOffer;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideOfferController extends ApiController
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    /** Passenger accepts the offer; a confirmed booking is created in one transaction. */
    public function accept(Request $request, RideOffer $rideOffer): JsonResponse
    {
        $booking = $this->bookings->acceptOffer($request->user(), $rideOffer->id);

        return $this->created($booking, 'Offer accepted and booking created.');
    }

    public function reject(Request $request, RideOffer $rideOffer): JsonResponse
    {
        $rideOffer->request()->first()->passenger_id === $request->user()->id || $this->deny('This is not your ride request.');
        $rideOffer->offer_status === 'pending' || $this->deny('Only pending offers can be rejected.', 409);

        $rideOffer->update(['offer_status' => 'rejected']);

        return $this->ok($rideOffer, 'Offer rejected.');
    }

    /** Driver withdraws a pending offer. */
    public function cancel(Request $request, RideOffer $rideOffer): JsonResponse
    {
        $rideOffer->driver_id === $request->user()->id || $this->deny('This is not your offer.');
        $rideOffer->offer_status === 'pending' || $this->deny('Only pending offers can be cancelled.', 409);

        $rideOffer->update(['offer_status' => 'cancelled']);

        return $this->ok($rideOffer, 'Offer cancelled.');
    }
}
