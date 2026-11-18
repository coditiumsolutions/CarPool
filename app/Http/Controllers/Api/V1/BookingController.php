<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingPresenter;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends ApiController
{
    public function __construct(private readonly BookingService $bookings, private readonly BookingPresenter $presenter)
    {
    }

    /** Passenger's own bookings. */
    public function index(Request $request): JsonResponse
    {
        $page = $this->presenter->query($request->user()->bookings()->getQuery())
            ->when($request->query('status'), fn ($q, $s) => $q->where('booking_status', $s))
            ->latest('booking_date')->paginate($this->perPage());

        $this->presenter->decorate($page->items(), $request->user());

        return $this->paginated($page);
    }

    /** Bookings on the driver's rides and accepted offers (passenger stats; phone once confirmed). */
    public function driverBookings(Request $request): JsonResponse
    {
        $id = $request->user()->id;

        $page = $this->presenter->query(Booking::query(), true)
            ->where(fn ($q) => $q->whereHas('ride', fn ($r) => $r->where('driver_id', $id))
                ->orWhereHas('rideOffer', fn ($o) => $o->where('driver_id', $id)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('booking_status', $s))
            ->latest('booking_date')->paginate($this->perPage());

        $this->presenter->decorate($page->items(), $request->user(), true);

        return $this->paginated($page);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->bookRide(
            $request->user(), $request->integer('ride_id'), $request->integer('seats'), $request->input('passengers', [])
        );

        return $this->created($this->presenter->present($booking, $request->user()), 'Booking created.');
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->assertParticipant($request, $booking);
        $isDriverView = $booking->passenger_id !== $request->user()->id;

        return $this->ok($this->presenter->present($booking, $request->user(), $isDriverView));
    }

    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookings->confirm($booking, $request->user());

        return $this->ok($this->presenter->present($booking, $request->user(), true), 'Booking confirmed.');
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $booking = $this->bookings->cancel($booking, $request->user(), $data['reason'] ?? null);

        return $this->ok($this->presenter->present($booking, $request->user())->load('cancellation'), 'Booking cancelled.');
    }

    private function assertParticipant(Request $request, Booking $booking): void
    {
        $user = $request->user();

        ($booking->passenger_id === $user->id || $this->bookings->isDriverOrAdmin($booking, $user)) || $this->deny('Not your booking.');
    }
}
