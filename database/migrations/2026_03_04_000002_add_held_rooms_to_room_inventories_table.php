<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_inventories', function (Blueprint $table) {
            $table->integer('held_rooms')->default(0)->after('blocked_rooms');
        });
    }

    public function down(): void
    {
        Schema::table('room_inventories', function (Blueprint $table) {
            $table->dropColumn('held_rooms');
        });
    }
};
