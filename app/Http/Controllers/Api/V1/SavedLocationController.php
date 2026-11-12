<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SavedLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedLocationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok($request->user()->savedLocations()->with('city:id,city_name')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:100'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return $this->created($request->user()->savedLocations()->create($data));
    }

    public function destroy(Request $request, SavedLocation $savedLocation): JsonResponse
    {
        $savedLocation->user_id === $request->user()->id || $this->deny('Not your saved location.');
        $savedLocation->delete();

        return $this->ok(null, 'Deleted.');
    }
}
