<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isDriver();
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
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
            'arrival_datetime' => ['nullable', 'date', 'after:departure_datetime'],
            'total_seats' => ['required', 'integer', 'between:1,50'],
            'price_per_seat' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'smoking_allowed' => ['boolean'],
            'pets_allowed' => ['boolean'],
            'luggage_allowed' => ['boolean'],
            'gender_preference' => ['nullable', Rule::in(['any', 'male', 'female'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'stops' => ['nullable', 'array', 'max:20'],
            'stops.*.stop_name' => ['required_with:stops', 'string', 'max:150'],
            'stops.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
