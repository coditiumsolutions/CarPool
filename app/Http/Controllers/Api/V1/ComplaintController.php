<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Booking;
use App\Models\Complaint;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($request->user()->complaints()->latest()->paginate($this->perPage()));
    }

    public function store(Request $request, BookingService $bookings): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
        ]);

        $booking = Booking::findOrFail($data['booking_id']);
        $user = $request->user();
        ($booking->passenger_id === $user->id || $booking->driverId() === $user->id) || $this->deny('You were not part of this booking.');

        $complaint = Complaint::create($data + ['user_id' => $user->id, 'complaint_status' => 'open']);

        return $this->created($complaint, 'Complaint submitted.');
    }
}
