<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $database = 'connected';
        } catch (\Throwable) {
            $database = 'unavailable';
        }

        return response()->json([
            'success' => $database === 'connected',
            'message' => 'Ride API is running.',
            'data' => [
                'version' => 'v1',
                'database' => $database,
                'timezone' => config('app.timezone'),
                'currency' => config('currency.code'),
                'server_time' => now()->toIso8601String(),
            ],
        ], $database === 'connected' ? 200 : 503);
    }
}
