<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends ApiController
{
    /**
     * Register (or refresh) an FCM token. A token identifies one device, so if
     * another account signed in on the same device the token moves to that user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $device = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'device_name' => $data['device_name'] ?? null]
        );

        return $this->ok($device->only(['id', 'platform', 'device_name']), 'Device registered.', $device->wasRecentlyCreated ? 201 : 200);
    }

    /** Called on logout. Idempotent: unknown tokens still return 200. */
    public function destroy(Request $request, string $token): JsonResponse
    {
        DeviceToken::where('token', $token)->where('user_id', $request->user()->id)->delete();

        return $this->ok(null, 'Device removed.');
    }
}
