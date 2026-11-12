<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreRideRequestRequest;
use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideRequestController extends ApiController
{
    private const WITH = ['passenger:id,name,gender,profile_image', 'fromCity:id,city_name', 'toCity:id,city_name'];

    /** Open requests that drivers can bid on. */
    public function index(Request $request): JsonResponse
    {
        $request->user()->isDriver() || $this->deny('Only drivers can browse ride requests.');

        $page = RideRequest::with(self::WITH)->withCount('offers')
            ->where('request_status', 'open')->where('departure_datetime', '>', now())
            ->when($request->query('from_city_id'), fn ($q, $v) => $q->where('from_city_id', $v))
            ->when($request->query('to_city_id'), fn ($q, $v) => $q->where('to_city_id', $v))
            ->orderBy('departure_datetime')->paginate($this->perPage());

        return $this->paginated($page);
    }

    public function mine(Request $request): JsonResponse
    {
        $page = $request->user()->rideRequests()->with(self::WITH)->withCount('offers')->latest()->paginate($this->perPage());

        return $this->paginated($page);
    }

    public function store(StoreRideRequestRequest $request): JsonResponse
    {
        $rideRequest = $request->user()->rideRequests()->create($request->validated() + ['request_status' => 'open']);

        return $this->created($rideRequest, 'Ride request posted.');
    }

    public function show(Request $request, RideRequest $rideRequest): JsonResponse
    {
        $user = $request->user();
        ($rideRequest->passenger_id === $user->id || $user->isDriver() || $user->isAdmin()) || $this->deny('Not allowed.');

        return $this->ok($rideRequest->load(self::WITH));
    }

    public function cancel(Request $request, RideRequest $rideRequest): JsonResponse
    {
        $rideRequest->passenger_id === $request->user()->id || $this->deny('This is not your request.');

        if ($rideRequest->request_status !== 'open') {
            $this->deny('Only open requests can be cancelled.', 409);
        }

        $rideRequest->update(['request_status' => 'cancelled']);
        $rideRequest->offers()->where('offer_status', 'pending')->update(['offer_status' => 'rejected']);

        return $this->ok($rideRequest, 'Request cancelled.');
    }

    /** Passenger views offers received on their request. */
    public function offers(Request $request, RideRequest $rideRequest): JsonResponse
    {
        $rideRequest->passenger_id === $request->user()->id || $this->deny('This is not your request.');

        return $this->ok($rideRequest->offers()->with(['driver:id,name,gender,profile_image,is_verified', 'vehicle:id,vehicle_type,make,model,color,model_year'])->latest()->get());
    }

    /** Driver sends an offer on an open request. */
    public function storeOffer(Request $request, RideRequest $rideRequest): JsonResponse
    {
        $driver = $request->user();
        $driver->isDriver() || $this->deny('Only drivers can make offers.');

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'offered_price' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'offered_seats' => ['nullable', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['offered_seats'] ??= $rideRequest->required_seats;

        $vehicle = Vehicle::where('id', $data['vehicle_id'])->where('user_id', $driver->id)->where('status', 'active')->first();
        $vehicle || $this->deny('Choose one of your active vehicles.', 422);
        $data['offered_seats'] <= $vehicle->total_seats || $this->deny('Vehicle does not have that many seats.', 422);

        $rideRequest->request_status === 'open' || $this->deny('This request is no longer open.', 409);
        $rideRequest->passenger_id !== $driver->id || $this->deny('You cannot offer on your own request.', 422);
        $rideRequest->offers()->where('driver_id', $driver->id)->whereIn('offer_status', ['pending', 'accepted'])->exists()
            && $this->deny('You already made an offer on this request.', 409);

        $offer = $rideRequest->offers()->create($data + ['driver_id' => $driver->id, 'offer_status' => 'pending']);

        app(\App\Services\NotificationService::class)->notify(
            $rideRequest->passenger_id, 'New Offer', "{$driver->name} offered {$data['offered_price']} for your ride request.", 'offer'
        );

        return $this->created($offer, 'Offer sent.');
    }

    /** Driver's own offers. */
    public function myOffers(Request $request): JsonResponse
    {
        $page = RideOffer::where('driver_id', $request->user()->id)->with(['request.fromCity:id,city_name', 'request.toCity:id,city_name', 'vehicle:id,make,model,registration_no'])
            ->latest()->paginate($this->perPage());

        return $this->paginated($page);
    }
}
