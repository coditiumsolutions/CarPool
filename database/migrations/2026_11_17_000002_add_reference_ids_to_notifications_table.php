<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive only: nullable pointers so a tapped notification can open the right screen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            foreach (['booking_id', 'ride_id', 'request_id', 'offer_id'] as $column) {
                if (! Schema::hasColumn('notifications', $column)) {
                    $table->unsignedBigInteger($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['booking_id', 'ride_id', 'request_id', 'offer_id']);
        });
    }
};
