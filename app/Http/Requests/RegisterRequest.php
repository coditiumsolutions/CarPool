<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^(03\d{9}|\+923\d{9})$/', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            // "admin" is deliberately not allowed here.
            'user_type' => ['required', Rule::in(['driver', 'passenger', 'both'])],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:-16 years'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'cnic' => ['nullable', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Pakistani mobile number, e.g. 03001234567.',
            'cnic.regex' => 'Enter a valid CNIC, e.g. 42101-1234567-1.',
        ];
    }
}
