<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Base class giving every API v1 controller the same JSON envelope. */
abstract class ApiController extends Controller
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function paginated(LengthAwarePaginator $page, string $message = 'OK'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Abort with a JSON error (rendered by bootstrap/app.php). */
    protected function deny(string $message, int $status = 403): never
    {
        throw new HttpException($status, $message);
    }

    protected function perPage(): int
    {
        return min((int) request('per_page', config('ride.per_page')), 50);
    }
}
