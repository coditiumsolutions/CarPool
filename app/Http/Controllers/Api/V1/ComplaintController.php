<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Booking;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($request->user()->complaints()->latest()->paginate($this->perPage()));
    }

    /**
     * booking_id is optional (general complaints). complaint_status starts as "open" and
     * is later set by an admin: open | in_progress | resolved | rejected.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'category' => ['nullable', 'in:'.implode(',', Complaint::CATEGORIES)],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
        ]);

        $user = $request->user();

        if (! empty($data['booking_id'])) {
            $booking = Booking::findOrFail($data['booking_id']);
            ($booking->passenger_id === $user->id || $booking->driverId() === $user->id) || $this->deny('You were not part of this booking.');
        }

        $complaint = Complaint::create($data + ['user_id' => $user->id, 'complaint_status' => 'open']);

        return $this->created($complaint, 'Complaint submitted.');
    }
}
