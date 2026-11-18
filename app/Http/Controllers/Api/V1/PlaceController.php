<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends ApiController
{
    /** Area suggestions inside a city: GET /places?city_id=1&q=gul */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $like = fn (string $v) => '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($v)).'%';

        $places = Place::query()
            ->when($data['city_id'] ?? null, fn ($q, $v) => $q->where('city_id', $v))
            ->when($data['q'] ?? null, fn ($q, $v) => $q->whereRaw('LOWER(name) LIKE ?', [$like($v)]))
            ->orderBy('name')->limit($data['limit'] ?? 20)
            ->get(['id', 'city_id', 'name', 'latitude', 'longitude']);

        return $this->ok($places);
    }
}
