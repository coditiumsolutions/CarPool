<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 17 application tables already exist in the real database (created by SQL scripts),
 * so migrations do not create them. Tests run on in-memory SQLite and need them built here.
 * Mirrors the ORIGINAL production schema; the project's migrations then add the new columns/tables.
 */
class TestSchema
{
    public static function create(): void
    {
        $ts = function (Blueprint $t, bool $updated = true) {
            $t->timestamp('created_at')->nullable()->useCurrent();
            if ($updated) {
                $t->timestamp('updated_at')->nullable()->useCurrent();
            }
        };

        Schema::create('cities', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->string('city_name', 100);
            $t->string('country', 100)->default('Pakistan');
            $t->boolean('status')->default(1);
            $ts($t);
        });

        Schema::create('users', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->string('name', 150);
            $t->string('email', 150)->nullable()->unique();
            $t->string('phone', 30)->unique();
            $t->string('password');
            $t->string('user_type')->default('passenger');
            $t->string('gender')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('profile_image')->nullable();
            $t->unsignedBigInteger('city_id')->nullable();
            $t->string('address')->nullable();
            $t->string('cnic')->nullable();
            $t->boolean('is_verified')->default(0);
            $t->string('status')->default('active');
            $ts($t);
        });

        Schema::create('user_documents', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('document_type', 50);
            $t->string('document_number', 100)->nullable();
            $t->string('document_image')->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('verification_status')->default('pending');
            $ts($t);
        });

        Schema::create('vehicles', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('vehicle_type', 50);
            $t->string('make', 100)->nullable();
            $t->string('model', 100)->nullable();
            $t->string('registration_no', 50)->unique();
            $t->string('color', 50)->nullable();
            $t->integer('model_year')->nullable();
            $t->unsignedInteger('total_seats');
            $t->boolean('is_verified')->default(0);
            $t->string('status')->default('active');
            $ts($t);
        });

        Schema::create('saved_locations', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('location_name', 100);
            $t->unsignedBigInteger('city_id')->nullable();
            $t->string('address');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $ts($t, false);
        });

        Schema::create('rides', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('driver_id');
            $t->unsignedBigInteger('vehicle_id');
            $t->string('ride_type');
            $t->unsignedBigInteger('from_city_id')->nullable();
            $t->unsignedBigInteger('to_city_id')->nullable();
            $t->string('from_location');
            $t->string('to_location');
            $t->decimal('from_latitude', 10, 7)->nullable();
            $t->decimal('from_longitude', 10, 7)->nullable();
            $t->decimal('to_latitude', 10, 7)->nullable();
            $t->decimal('to_longitude', 10, 7)->nullable();
            $t->dateTime('departure_datetime');
            $t->dateTime('arrival_datetime')->nullable();
            $t->unsignedInteger('total_seats');
            $t->unsignedInteger('available_seats');
            $t->decimal('price_per_seat', 12, 2);
            $t->boolean('smoking_allowed')->default(0);
            $t->boolean('pets_allowed')->default(0);
            $t->boolean('luggage_allowed')->default(1);
            $t->string('gender_preference')->default('any');
            $t->text('notes')->nullable();
            $t->string('ride_status')->default('scheduled');
            $ts($t);
        });

        Schema::create('ride_stops', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('ride_id');
            $t->string('stop_name', 150);
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->integer('stop_order')->default(1);
            $ts($t, false);
        });

        Schema::create('ride_requests', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('passenger_id');
            $t->string('ride_type');
            $t->unsignedBigInteger('from_city_id')->nullable();
            $t->unsignedBigInteger('to_city_id')->nullable();
            $t->string('from_location');
            $t->string('to_location');
            $t->decimal('from_latitude', 10, 7)->nullable();
            $t->decimal('from_longitude', 10, 7)->nullable();
            $t->decimal('to_latitude', 10, 7)->nullable();
            $t->decimal('to_longitude', 10, 7)->nullable();
            $t->dateTime('departure_datetime');
            $t->unsignedInteger('required_seats')->default(1);
            $t->decimal('max_budget', 12, 2)->nullable();
            $t->text('notes')->nullable();
            $t->string('request_status')->default('open');
            $ts($t);
        });

        Schema::create('ride_offers', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('request_id');
            $t->unsignedBigInteger('driver_id');
            $t->unsignedBigInteger('vehicle_id');
            $t->decimal('offered_price', 12, 2);
            $t->unsignedInteger('offered_seats')->default(1);
            $t->text('message')->nullable();
            $t->string('offer_status')->default('pending');
            $ts($t);
        });

        Schema::create('bookings', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('ride_id')->nullable();
            $t->unsignedBigInteger('ride_offer_id')->nullable();
            $t->unsignedBigInteger('passenger_id');
            $t->dateTime('booking_date')->nullable()->useCurrent();
            $t->unsignedInteger('total_seats');
            $t->decimal('price_per_seat', 12, 2);
            $t->decimal('total_amount', 12, 2);
            $t->string('payment_status')->default('pending');
            $t->string('booking_status')->default('pending');
            $ts($t);
        });

        Schema::create('booking_passengers', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->string('passenger_name', 150);
            $t->string('passenger_phone', 30)->nullable();
            $ts($t, false);
        });

        Schema::create('booking_cancellations', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->unsignedBigInteger('cancelled_by');
            $t->text('reason')->nullable();
            $t->decimal('refund_amount', 12, 2)->default(0);
            $ts($t, false);
        });

        Schema::create('payments', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->unsignedBigInteger('user_id');
            $t->string('payment_method', 50)->nullable();
            $t->string('transaction_reference', 150)->nullable();
            $t->decimal('amount', 12, 2);
            $t->decimal('commission_amount', 12, 2)->default(0);
            $t->decimal('refund_amount', 12, 2)->default(0);
            $t->string('payment_status')->default('pending');
            $t->dateTime('payment_date')->nullable();
            $ts($t, false);
        });

        Schema::create('notifications', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('title', 200);
            $t->text('message');
            $t->string('notification_type', 50)->nullable();
            $t->boolean('is_read')->default(0);
            $ts($t, false);
        });

        Schema::create('ride_tracking', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ride_id');
            $t->unsignedBigInteger('driver_id');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->dateTime('recorded_at')->nullable()->useCurrent();
        });

        Schema::create('reviews', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->unsignedBigInteger('reviewer_id');
            $t->unsignedBigInteger('reviewee_id');
            $t->unsignedTinyInteger('rating');
            $t->text('comments')->nullable();
            $ts($t, false);
        });

        // booking_id is NOT NULL here, exactly like production before the new migration relaxes it.
        Schema::create('complaints', function (Blueprint $t) use ($ts) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->unsignedBigInteger('user_id');
            $t->string('subject', 200);
            $t->text('description');
            $t->string('complaint_status')->default('open');
            $ts($t);
        });
    }
}
