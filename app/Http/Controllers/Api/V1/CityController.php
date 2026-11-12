<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\City;
use Illuminate\Http\JsonResponse;

class CityController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->ok(City::where('status', 1)->orderBy('city_name')->get(['id', 'city_name', 'country']));
    }
}
