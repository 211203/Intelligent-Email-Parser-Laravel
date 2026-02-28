<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->enum('source_type', ['email', 'pdf', 'form', 'whatsapp'])->notNull();
            $table->string('client_name', 100)->notNull();
            $table->string('guest_name', 150)->nullable();
            $table->string('guest_email', 150)->nullable();
            $table->string('guest_phone', 20)->nullable();

            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->integer('number_of_guests')->nullable();
            $table->integer('number_of_rooms')->nullable();
            $table->string('room_type_requested', 100)->nullable();

            $table->decimal('budget_amount', 10, 2)->nullable();
            $table->string('currency', 10)->default('INR');

            $table->enum('inquiry_status', ['new', 'availability_checked', 'quoted', 'hold_created', 'converted', 'closed'])->default('new');
            $table->enum('intent_type', ['availability', 'pricing', 'reservation', 'general'])->notNull();

            $table->longText('raw_content')->nullable();
            $table->json('parsed_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
