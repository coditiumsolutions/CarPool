<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\HomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends ApiController
{
    public function __invoke(Request $request, HomeService $home): JsonResponse
    {
        return $this->ok($home->summary($request->user()));
    }
}
