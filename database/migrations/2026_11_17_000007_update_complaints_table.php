<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds `category` and relaxes booking_id to NULL so general (non-booking) complaints are possible.
// No data is changed. Re-running is harmless.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (! Schema::hasColumn('complaints', 'category')) {
                $table->string('category', 50)->nullable();
            }
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
