<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->except('device_name') + ['status' => 'active', 'is_verified' => false]);
        $token = $this->auth->issueToken($user, $request->input('device_name', 'mobile'));

        return $this->created(
            ['token' => $token, 'token_type' => 'Bearer', 'user' => $this->present($user)],
            'Registration successful.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->auth->authenticate($request->input('login'), $request->input('password'), $request->ip());
        } catch (ValidationException $e) {
            return $this->fail($e->errors()['login'][0], 401);
        }

        $token = $this->auth->issueToken($user, $request->input('device_name', 'mobile'));

        return $this->ok(['token' => $token, 'token_type' => 'Bearer', 'user' => $this->present($user)], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out successfully.');
    }

    public function user(Request $request): JsonResponse
    {
        return $this->ok($this->present($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'regex:/^(03\d{9}|\+923\d{9})$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:-16 years'],
            'city_id' => ['sometimes', 'nullable', 'exists:cities,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cnic' => ['sometimes', 'nullable', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'profile_image' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->update($data);

        return $this->ok($this->present($user->refresh()), 'Profile updated.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->fail('Current password is incorrect.', 422);
        }

        $user->update(['password' => $data['password']]);
        // Sign out every other device.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return $this->ok(null, 'Password changed.');
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /** CNIC and password are never returned (also hidden on the model). */
    private function present(User $user): array
    {
        return $user->only([
            'id', 'name', 'email', 'phone', 'user_type', 'gender', 'date_of_birth', 'profile_image',
            'city_id', 'address', 'is_verified', 'status',
        ]);
    }
}
