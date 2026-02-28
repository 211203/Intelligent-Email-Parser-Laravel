<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InquirySystemSeeder extends Seeder
{
    public function run(): void
    {
        // Insert rooms
        DB::table('rooms')->insert([
            [
                'room_type' => 'Standard Room',
                'description' => 'Basic room with queen bed',
                'base_price' => 3000.00,
                'max_occupancy' => 2,
                'total_rooms' => 20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_type' => 'Deluxe Room',
                'description' => 'Deluxe room with balcony',
                'base_price' => 5000.00,
                'max_occupancy' => 3,
                'total_rooms' => 15,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_type' => 'Executive Room',
                'description' => 'Executive business class room',
                'base_price' => 7000.00,
                'max_occupancy' => 3,
                'total_rooms' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_type' => 'Family Suite',
                'description' => 'Large suite for families',
                'base_price' => 10000.00,
                'max_occupancy' => 5,
                'total_rooms' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_type' => 'Presidential Suite',
                'description' => 'Luxury premium suite',
                'base_price' => 25000.00,
                'max_occupancy' => 4,
                'total_rooms' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert room inventories
        DB::table('room_inventories')->insert([
            [
                'room_id' => 2, // Deluxe Room
                'date' => '2026-03-10',
                'total_rooms' => 15,
                'booked_rooms' => 5,
                'blocked_rooms' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => 2, // Deluxe Room
                'date' => '2026-03-11',
                'total_rooms' => 15,
                'booked_rooms' => 6,
                'blocked_rooms' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => 3, // Executive Room
                'date' => '2026-03-20',
                'total_rooms' => 10,
                'booked_rooms' => 3,
                'blocked_rooms' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => 4, // Family Suite
                'date' => '2026-04-01',
                'total_rooms' => 5,
                'booked_rooms' => 2,
                'blocked_rooms' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => 1, // Standard Room
                'date' => '2026-03-10',
                'total_rooms' => 20,
                'booked_rooms' => 10,
                'blocked_rooms' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert inquiries
        DB::table('inquiries')->insert([
            [
                'source_type' => 'email',
                'client_name' => 'ABC Travels',
                'guest_name' => 'Rahul Sharma',
                'guest_email' => 'rahul@gmail.com',
                'guest_phone' => '9876543210',
                'check_in_date' => '2026-03-10',
                'check_out_date' => '2026-03-12',
                'number_of_guests' => 2,
                'number_of_rooms' => 1,
                'room_type_requested' => 'Deluxe Room',
                'budget_amount' => 15000.00,
                'currency' => 'INR',
                'inquiry_status' => 'new',
                'intent_type' => 'availability',
                'raw_content' => 'Need 1 deluxe room for 2 nights',
                'parsed_json' => json_encode(['guests' => 2, 'room_type' => 'Deluxe Room']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'source_type' => 'form',
                'client_name' => 'Priya Desai',
                'guest_name' => 'Priya Desai',
                'guest_email' => 'priya@gmail.com',
                'guest_phone' => '9123456780',
                'check_in_date' => '2026-04-01',
                'check_out_date' => '2026-04-05',
                'number_of_guests' => 4,
                'number_of_rooms' => 2,
                'room_type_requested' => 'Family Suite',
                'budget_amount' => 40000.00,
                'currency' => 'INR',
                'inquiry_status' => 'new',
                'intent_type' => 'pricing',
                'raw_content' => 'Looking for family suite',
                'parsed_json' => json_encode(['rooms' => 2, 'nights' => 4]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'source_type' => 'whatsapp',
                'client_name' => 'Corporate Co',
                'guest_name' => 'Amit Mehta',
                'guest_email' => null,
                'guest_phone' => '9988776655',
                'check_in_date' => '2026-03-20',
                'check_out_date' => '2026-03-22',
                'number_of_guests' => 3,
                'number_of_rooms' => 2,
                'room_type_requested' => 'Executive Room',
                'budget_amount' => 25000.00,
                'currency' => 'INR',
                'inquiry_status' => 'new',
                'intent_type' => 'reservation',
                'raw_content' => 'Need executive rooms for business trip',
                'parsed_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'source_type' => 'pdf',
                'client_name' => 'Global Tours',
                'guest_name' => 'Sneha Kapoor',
                'guest_email' => 'sneha@gmail.com',
                'guest_phone' => '9001122334',
                'check_in_date' => '2026-05-15',
                'check_out_date' => '2026-05-18',
                'number_of_guests' => 2,
                'number_of_rooms' => 1,
                'room_type_requested' => 'Suite',
                'budget_amount' => 60000.00,
                'currency' => 'INR',
                'inquiry_status' => 'new',
                'intent_type' => 'pricing',
                'raw_content' => 'Please quote best rate for suite',
                'parsed_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert inquiry quotes
        DB::table('inquiry_quotes')->insert([
            [
                'inquiry_id' => 1,
                'room_id' => 2, // Deluxe Room
                'price_per_night' => 5000.00,
                'number_of_nights' => 2,
                'subtotal_amount' => 10000.00,
                'tax_amount' => 1800.00,
                'discount_amount' => 0.00,
                'total_amount' => 11800.00,
                'currency' => 'INR',
                'quote_status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'inquiry_id' => 2,
                'room_id' => 4, // Family Suite
                'price_per_night' => 10000.00,
                'number_of_nights' => 4,
                'subtotal_amount' => 40000.00,
                'tax_amount' => 7200.00,
                'discount_amount' => 2000.00,
                'total_amount' => 45200.00,
                'currency' => 'INR',
                'quote_status' => 'generated',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'inquiry_id' => 3,
                'room_id' => 3, // Executive Room
                'price_per_night' => 7000.00,
                'number_of_nights' => 2,
                'subtotal_amount' => 14000.00,
                'tax_amount' => 2520.00,
                'discount_amount' => 1000.00,
                'total_amount' => 15520.00,
                'currency' => 'INR',
                'quote_status' => 'accepted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'inquiry_id' => 4,
                'room_id' => 5, // Presidential Suite
                'price_per_night' => 25000.00,
                'number_of_nights' => 3,
                'subtotal_amount' => 75000.00,
                'tax_amount' => 13500.00,
                'discount_amount' => 5000.00,
                'total_amount' => 83500.00,
                'currency' => 'INR',
                'quote_status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert holds
        DB::table('holds')->insert([
            [
                'inquiry_id' => 1,
                'room_id' => 2, // Deluxe Room
                'check_in_date' => '2026-03-10',
                'check_out_date' => '2026-03-12',
                'number_of_rooms' => 1,
                'hold_status' => 'active',
                'expires_at' => Carbon::parse('2026-03-01 23:59:00'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'inquiry_id' => 2,
                'room_id' => 4, // Family Suite
                'check_in_date' => '2026-04-01',
                'check_out_date' => '2026-04-05',
                'number_of_rooms' => 2,
                'hold_status' => 'converted',
                'expires_at' => Carbon::parse('2026-03-25 23:59:00'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'inquiry_id' => 3,
                'room_id' => 3, // Executive Room
                'check_in_date' => '2026-03-20',
                'check_out_date' => '2026-03-22',
                'number_of_rooms' => 2,
                'hold_status' => 'expired',
                'expires_at' => Carbon::parse('2026-03-15 23:59:00'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'inquiry_id' => 4,
                'room_id' => 5, // Presidential Suite
                'check_in_date' => '2026-05-15',
                'check_out_date' => '2026-05-18',
                'number_of_rooms' => 1,
                'hold_status' => 'active',
                'expires_at' => Carbon::parse('2026-05-01 23:59:00'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
