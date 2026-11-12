<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends ApiController
{
    /** Passenger reviews the driver, or the driver reviews the passenger, after a completed booking. */
    public function store(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $driverId = $booking->driverId();

        $reviewee = match (true) {
            $booking->passenger_id === $user->id => $driverId,
            $driverId === $user->id => $booking->passenger_id,
            default => $this->deny('You were not part of this booking.'),
        };

        $booking->booking_status === Booking::STATUS_COMPLETED || $this->deny('You can only review completed bookings.', 409);
        Review::where('booking_id', $booking->id)->where('reviewer_id', $user->id)->exists()
            && $this->deny('You already reviewed this booking.', 409);

        $review = Review::create($data + ['booking_id' => $booking->id, 'reviewer_id' => $user->id, 'reviewee_id' => $reviewee]);

        return $this->created($review, 'Review submitted.');
    }

    public function forUser(User $user): JsonResponse
    {
        $reviews = Review::where('reviewee_id', $user->id)->with('reviewer:id,name,profile_image')->latest()->paginate($this->perPage());

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reviews->items(),
            'summary' => ['average_rating' => round((float) Review::where('reviewee_id', $user->id)->avg('rating'), 2), 'total' => $reviews->total()],
            'meta' => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'per_page' => $reviews->perPage(), 'total' => $reviews->total()],
        ]);
    }
}
