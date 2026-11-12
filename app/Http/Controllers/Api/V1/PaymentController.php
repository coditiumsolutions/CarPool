<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($request->user()->payments()->with('booking:id,total_amount,booking_status')->latest()->paginate($this->perPage()));
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(config('ride.payment_methods'))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_reference' => ['nullable', 'string', 'max:150'],
        ]);

        return $this->created($this->payments->create($request->user(), $booking, $data), 'Payment submitted. Awaiting confirmation.');
    }

    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        return $this->ok($this->payments->confirm($payment, $request->user()), 'Payment confirmed.');
    }
}
