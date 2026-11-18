<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_documents')) {
            return;
        }

        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('document_image', 255)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('verification_status', 20)->default('pending'); // pending|approved|rejected
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
