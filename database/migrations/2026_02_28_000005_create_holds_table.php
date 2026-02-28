<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->onDelete('cascade');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');

            $table->date('check_in_date')->notNull();
            $table->date('check_out_date')->notNull();
            $table->integer('number_of_rooms')->notNull();

            $table->enum('hold_status', ['active', 'expired', 'converted', 'cancelled'])->default('active');
            $table->dateTime('expires_at')->notNull();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
