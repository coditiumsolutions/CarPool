<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\RideController;
use App\Http\Controllers\Api\V1\RideOfferController;
use App\Http\Controllers\Api\V1\RideRequestController;
use App\Http\Controllers\Api\V1\SavedLocationController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // ---- Public ----
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/cities', [CityController::class, 'index'])->name('cities');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login');

    // ---- Authenticated (Sanctum bearer token) ----
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        // Account
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::put('/password', [AuthController::class, 'changePassword'])->name('password.change');
        Route::apiResource('saved-locations', SavedLocationController::class)->only(['index', 'store', 'destroy'])->parameters(['saved-locations' => 'savedLocation']);

        // Driver: vehicles
        Route::apiResource('vehicles', VehicleController::class);

        // Rides
        Route::get('/rides', [RideController::class, 'index'])->name('rides.index');
        Route::get('/my/rides', [RideController::class, 'mine'])->name('rides.mine');
        Route::post('/rides', [RideController::class, 'store'])->name('rides.store');
        Route::get('/rides/{ride}', [RideController::class, 'show'])->name('rides.show');
        Route::get('/rides/{ride}/bookings', [RideController::class, 'bookings'])->name('rides.bookings');
        Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel'])->name('rides.cancel');
        Route::post('/rides/{ride}/start', [RideController::class, 'start'])->name('rides.start');
        Route::post('/rides/{ride}/complete', [RideController::class, 'complete'])->name('rides.complete');
        Route::post('/rides/{ride}/location', [RideController::class, 'storeLocation'])->name('rides.location');
        Route::get('/rides/{ride}/tracking', [RideController::class, 'tracking'])->name('rides.tracking');

        // Ride requests and offers
        Route::get('/ride-requests', [RideRequestController::class, 'index'])->name('ride-requests.index');
        Route::get('/my/ride-requests', [RideRequestController::class, 'mine'])->name('ride-requests.mine');
        Route::post('/ride-requests', [RideRequestController::class, 'store'])->name('ride-requests.store');
        Route::get('/ride-requests/{rideRequest}', [RideRequestController::class, 'show'])->name('ride-requests.show');
        Route::post('/ride-requests/{rideRequest}/cancel', [RideRequestController::class, 'cancel'])->name('ride-requests.cancel');
        Route::get('/ride-requests/{rideRequest}/offers', [RideRequestController::class, 'offers'])->name('ride-requests.offers');
        Route::post('/ride-requests/{rideRequest}/offers', [RideRequestController::class, 'storeOffer'])->name('ride-requests.offers.store');
        Route::get('/my/offers', [RideRequestController::class, 'myOffers'])->name('offers.mine');
        Route::post('/ride-offers/{rideOffer}/accept', [RideOfferController::class, 'accept'])->name('offers.accept');
        Route::post('/ride-offers/{rideOffer}/reject', [RideOfferController::class, 'reject'])->name('offers.reject');
        Route::post('/ride-offers/{rideOffer}/cancel', [RideOfferController::class, 'cancel'])->name('offers.cancel');

        // Bookings
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/driver/bookings', [BookingController::class, 'driverBookings'])->name('bookings.driver');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

        // Payments, reviews, complaints
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('/payments/{payment}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/bookings/{booking}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::get('/users/{user}/reviews', [ReviewController::class, 'forUser'])->name('reviews.user');
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
});
