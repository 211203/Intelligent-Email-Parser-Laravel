<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_type', 100)->notNull();
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2)->notNull();
            $table->integer('max_occupancy')->notNull();
            $table->integer('total_rooms')->notNull();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
