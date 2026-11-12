<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok($request->user()->vehicles()->latest()->get());
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        // New vehicles start unverified; an admin verifies them.
        $vehicle = $request->user()->vehicles()->create($request->validated() + ['is_verified' => false, 'status' => 'active']);

        return $this->created($vehicle);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        return $this->ok($this->owned($request, $vehicle));
    }

    public function update(StoreVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->owned($request, $vehicle)->update($request->validated());

        return $this->ok($vehicle->refresh(), 'Vehicle updated.');
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->owned($request, $vehicle);

        if ($vehicle->rides()->exists() || $vehicle->rideOffers()->exists()) {
            // Keep history intact: deactivate instead of deleting.
            $vehicle->update(['status' => 'inactive']);

            return $this->ok($vehicle, 'Vehicle has ride history, so it was deactivated instead of deleted.');
        }

        $vehicle->delete();

        return $this->ok(null, 'Vehicle deleted.');
    }

    private function owned(Request $request, Vehicle $vehicle): Vehicle
    {
        $vehicle->user_id === $request->user()->id || $this->deny('This is not your vehicle.');

        return $vehicle;
    }
}
