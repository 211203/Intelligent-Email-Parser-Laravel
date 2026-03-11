<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_inventories', function (Blueprint $table) {
            // Add a unique constraint on room_id+date.
            // MySQL allows having both a regular index and unique index on the same columns.
            // The FK constraint relies on the regular index so we keep it and just add unique.
            $table->unique(['room_id', 'date'], 'room_inventories_room_id_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('room_inventories', function (Blueprint $table) {
            $table->dropUnique('room_inventories_room_id_date_unique');
        });
    }
};
