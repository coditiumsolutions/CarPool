<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for a passenger's ride request (named to avoid clashing with StoreRideRequest). */
class StoreRideRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isPassenger();
    }

    public function rules(): array
    {
        return [
            'ride_type' => ['required', Rule::in(['intercity', 'intracity'])],
            'from_city_id' => ['required', 'exists:cities,id'],
            'to_city_id' => ['required', 'exists:cities,id'],
            'from_location' => ['required', 'string', 'max:255'],
            'to_location' => ['required', 'string', 'max:255'],
            'from_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'from_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'to_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'to_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'departure_datetime' => ['required', 'date', 'after:now'],
            'required_seats' => ['required', 'integer', 'between:1,10'],
            'max_budget' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
