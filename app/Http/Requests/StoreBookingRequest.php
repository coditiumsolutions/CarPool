<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isPassenger();
    }

    public function rules(): array
    {
        return [
            'ride_id' => ['required', 'integer', 'exists:rides,id'],
            'seats' => ['required', 'integer', 'min:1', 'max:10'],
            'passengers' => ['nullable', 'array', 'max:10'],
            'passengers.*.passenger_name' => ['required_with:passengers', 'string', 'max:150'],
            'passengers.*.passenger_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
