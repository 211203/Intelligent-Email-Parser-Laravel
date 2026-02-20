<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('guest_name')->nullable();
            $table->string('booking_id')->nullable();
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->enum('source', ['api', 'gmail'])->default('api');
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
