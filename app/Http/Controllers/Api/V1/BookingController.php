<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends ApiController
{
    private const WITH = ['ride.fromCity:id,city_name', 'ride.toCity:id,city_name', 'ride.driver:id,name,phone,gender,profile_image', 'rideOffer.driver:id,name,phone,gender,profile_image', 'passengers', 'payments'];

    public function __construct(private readonly BookingService $bookings)
    {
    }

    /** Passenger's own bookings. */
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->bookings()->with(self::WITH)
            ->when($request->query('status'), fn ($q, $s) => $q->where('booking_status', $s))
            ->latest('booking_date')->paginate($this->perPage());

        return $this->paginated($page);
    }

    /** Bookings on the driver's rides and accepted offers. */
    public function driverBookings(Request $request): JsonResponse
    {
        $id = $request->user()->id;

        $page = Booking::with(array_merge(self::WITH, ['passenger:id,name,phone,gender']))
            ->where(fn ($q) => $q->whereHas('ride', fn ($r) => $r->where('driver_id', $id))
                ->orWhereHas('rideOffer', fn ($o) => $o->where('driver_id', $id)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('booking_status', $s))
            ->latest('booking_date')->paginate($this->perPage());

        return $this->paginated($page);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->bookRide(
            $request->user(), $request->integer('ride_id'), $request->integer('seats'), $request->input('passengers', [])
        );

        return $this->created($booking, 'Booking created.');
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->assertParticipant($request, $booking);

        return $this->ok($booking->load(self::WITH));
    }

    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        return $this->ok($this->bookings->confirm($booking, $request->user()), 'Booking confirmed.');
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->ok($this->bookings->cancel($booking, $request->user(), $data['reason'] ?? null)->load('cancellation'), 'Booking cancelled.');
    }

    private function assertParticipant(Request $request, Booking $booking): void
    {
        $user = $request->user();

        ($booking->passenger_id === $user->id || $this->bookings->isDriverOrAdmin($booking, $user)) || $this->deny('Not your booking.');
    }
}
