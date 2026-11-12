<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreRideRequest;
use App\Models\Ride;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends ApiController
{
    private const WITH = ['driver:id,name,gender,profile_image,is_verified', 'vehicle:id,vehicle_type,make,model,color,model_year,registration_no', 'fromCity:id,city_name', 'toCity:id,city_name', 'stops'];

    public function __construct(private readonly RideService $rides)
    {
    }

    /** Passenger search: GET /rides?from_city_id=&to_city_id=&date=&seats=&ride_type=&max_price= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'ride_type' => ['nullable', 'in:intercity,intracity'],
            'from_city_id' => ['nullable', 'integer'],
            'to_city_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'max_price' => ['nullable', 'numeric'],
        ]);

        return $this->paginated($this->rides->search($filters)->paginate($this->perPage()));
    }

    public function show(Ride $ride): JsonResponse
    {
        return $this->ok($ride->load(self::WITH));
    }

    /** Driver's own rides. */
    public function mine(Request $request): JsonResponse
    {
        $page = $request->user()->rides()->with(['vehicle:id,make,model,registration_no', 'fromCity:id,city_name', 'toCity:id,city_name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('ride_status', $s))
            ->latest('departure_datetime')->paginate($this->perPage());

        return $this->paginated($page);
    }

    public function store(StoreRideRequest $request): JsonResponse
    {
        return $this->created($this->rides->create($request->user(), $request->validated()), 'Ride published.');
    }

    /** Bookings on one of the driver's rides. */
    public function bookings(Request $request, Ride $ride): JsonResponse
    {
        ($ride->driver_id === $request->user()->id || $request->user()->isAdmin()) || $this->deny('This is not your ride.');

        return $this->ok($ride->bookings()->with(['passenger:id,name,phone,gender', 'passengers'])->get());
    }

    public function cancel(Request $request, Ride $ride): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->ok($this->rides->cancel($ride, $request->user(), $data['reason'] ?? null), 'Ride cancelled.');
    }

    public function start(Request $request, Ride $ride): JsonResponse
    {
        return $this->ok($this->rides->start($ride, $request->user()), 'Ride started.');
    }

    public function complete(Request $request, Ride $ride): JsonResponse
    {
        return $this->ok($this->rides->complete($ride, $request->user()), 'Ride completed.');
    }

    /** Driver app posts its GPS position while the ride is in progress. */
    public function storeLocation(Request $request, Ride $ride): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return $this->created($this->rides->recordLocation($ride, $request->user(), $data['latitude'], $data['longitude']), 'Location recorded.');
    }

    /** Latest position (and recent trail) for passengers following the ride. */
    public function tracking(Request $request, Ride $ride): JsonResponse
    {
        $this->rides->canViewTracking($ride, $request->user()) || $this->deny('You cannot track this ride.');

        $points = $ride->tracking()->latest('recorded_at')->limit(50)->get(['latitude', 'longitude', 'recorded_at']);

        return $this->ok(['ride_status' => $ride->ride_status, 'latest' => $points->first(), 'trail' => $points->reverse()->values()]);
    }
}
