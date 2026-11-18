<?php

namespace Tests\Support;

use App\Models\Booking;
use App\Models\City;
use App\Models\Review;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Laravel\Sanctum\Sanctum;

trait MakesData
{
    private int $seq = 0;

    protected function user(string $type = 'passenger', array $o = []): User
    {
        $n = ++$this->seq;

        return User::create($o + [
            'name' => "User $n",
            'email' => "user$n@test.local",
            'phone' => sprintf('0300%07d', $n),
            'password' => 'Password123',
            'user_type' => $type,
            'status' => 'active',
        ]);
    }

    protected function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    /** A real 1x1 PNG (the GD extension is not required, unlike UploadedFile::fake()->image()). */
    protected function pngFile(string $name = 'photo.png'): \Illuminate\Http\UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $png);
    }

    protected function city(string $name = 'Karachi'): City
    {
        return City::create(['city_name' => $name]);
    }

    protected function vehicle(User $driver, array $o = []): Vehicle
    {
        $n = ++$this->seq;

        return Vehicle::create($o + [
            'user_id' => $driver->id, 'vehicle_type' => 'Car', 'make' => 'Toyota', 'model' => 'Corolla',
            'registration_no' => "REG-$n", 'color' => 'White', 'model_year' => 2022, 'total_seats' => 4,
            'is_verified' => true, 'status' => 'active',
        ]);
    }

    protected function ride(User $driver, Vehicle $vehicle, array $o = []): Ride
    {
        return Ride::create($o + [
            'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'ride_type' => 'intercity',
            'from_location' => 'Gulshan, Karachi', 'to_location' => 'Johar Town, Lahore',
            'departure_datetime' => now()->addDays(2)->setTime(9, 0), 'total_seats' => 4, 'available_seats' => 4,
            'price_per_seat' => 4500, 'ride_status' => 'scheduled',
        ]);
    }

    protected function booking(Ride $ride, User $passenger, string $status = 'pending', array $o = []): Booking
    {
        return Booking::create($o + [
            'ride_id' => $ride->id, 'passenger_id' => $passenger->id, 'booking_date' => now(),
            'total_seats' => 1, 'price_per_seat' => $ride->price_per_seat, 'total_amount' => $ride->price_per_seat,
            'payment_status' => 'pending', 'booking_status' => $status,
        ]);
    }

    protected function review(Booking $booking, User $reviewer, User $reviewee, int $rating): Review
    {
        return Review::create([
            'booking_id' => $booking->id, 'reviewer_id' => $reviewer->id, 'reviewee_id' => $reviewee->id, 'rating' => $rating,
        ]);
    }
}
