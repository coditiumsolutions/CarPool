<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;

class DashboardService
{
    /** System-wide statistics for the admin dashboard (real database queries). */
    public function adminStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_drivers' => User::whereIn('user_type', [User::TYPE_DRIVER, User::TYPE_BOTH])->count(),
            'total_passengers' => User::whereIn('user_type', [User::TYPE_PASSENGER, User::TYPE_BOTH])->count(),
            'total_vehicles' => Vehicle::count(),
            'total_rides' => Ride::count(),
            'active_rides' => Ride::whereIn('ride_status', Ride::ACTIVE_STATUSES)->count(),
            'total_bookings' => Booking::count(),
            'pending_bookings' => Booking::where('booking_status', Booking::STATUS_PENDING)->count(),
        ];
    }

    /** Personal statistics for driver / passenger users. */
    public function userStats(User $user): array
    {
        $stats = [];

        if ($user->isDriver()) {
            $stats['my_vehicles'] = $user->vehicles()->count();
            $stats['my_rides'] = $user->rides()->count();
            $stats['my_active_rides'] = $user->rides()->whereIn('ride_status', Ride::ACTIVE_STATUSES)->count();
        }

        if ($user->isPassenger()) {
            $stats['my_bookings'] = $user->bookings()->count();
            $stats['my_pending_bookings'] = $user->bookings()->where('booking_status', Booking::STATUS_PENDING)->count();
            $stats['my_ride_requests'] = $user->rideRequests()->count();
        }

        return $stats;
    }

    public function recentUsers(int $limit = 5)
    {
        return User::with('city')->latest()->limit($limit)->get();
    }

    public function recentBookings(int $limit = 5)
    {
        return Booking::with('passenger')->latest()->limit($limit)->get();
    }
}
