<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreVehicleRequest extends ApiFormRequest
{
    protected function authorizationMessage(): string
    {
        return 'Only driver accounts can add vehicles. Switch your account type to both in your profile.';
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->isDriver();
    }

    public function rules(): array
    {
        $id = $this->route('vehicle');

        return [
            'vehicle_type' => ['required', 'string', 'max:50'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'registration_no' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'registration_no')->ignore($id)],
            'color' => ['nullable', 'string', 'max:50'],
            'model_year' => ['nullable', 'integer', 'between:1990,'.(date('Y') + 1)],
            'total_seats' => ['required', 'integer', 'between:1,50'],
        ];
    }
}
