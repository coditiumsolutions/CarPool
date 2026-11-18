<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    private const PHONE_RULE = '/^(03\d{9}|\+923\d{9})$/';

    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->except('device_name') + ['status' => 'active', 'is_verified' => false]);
        $token = $this->auth->issueToken($user, $request->input('device_name', 'mobile'));

        return $this->created(
            ['token' => $token, 'token_type' => 'Bearer', 'user' => new UserResource($user)],
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

        return $this->ok(['token' => $token, 'token_type' => 'Bearer', 'user' => new UserResource($user)], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out successfully.');
    }

    public function user(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'regex:'.self::PHONE_RULE, Rule::unique('users', 'phone')->ignore($user->id)],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:-16 years'],
            'city_id' => ['sometimes', 'nullable', 'exists:cities,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cnic' => ['sometimes', 'nullable', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'profile_image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'regex:'.self::PHONE_RULE],
            // Upgrade only: passenger/driver -> both. Downgrades and "admin" are rejected.
            'user_type' => ['sometimes', Rule::in([User::TYPE_BOTH])],
        ], [
            'user_type.in' => 'Account type can only be upgraded to "both".',
            'emergency_contact_phone.regex' => 'Enter a valid Pakistani mobile number, e.g. 03001234567.',
        ]);

        if (isset($data['user_type']) && $user->isAdmin()) {
            throw ValidationException::withMessages(['user_type' => 'Administrator accounts cannot change account type.']);
        }

        $user->update($data);

        return $this->ok(new UserResource($user->refresh()), 'Profile updated.');
    }

    /** Multipart upload: field "photo" (or "image"), jpg/png/webp up to 4 MB. */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required_without:image', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image' => ['required_without:photo', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $file = $request->file('photo') ?? $request->file('image');
        $old = $user->getRawOriginal('profile_image');

        $path = $file->store('profile-photos', 'public');
        $user->update(['profile_image' => $path]);

        if ($old && ! str_starts_with($old, 'http')) {
            Storage::disk('public')->delete($old);
        }

        return $this->ok(
            ['profile_image' => $user->refresh()->profile_image, 'user' => new UserResource($user)],
            'Profile photo updated.'
        );
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
}
