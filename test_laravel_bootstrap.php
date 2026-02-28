<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

echo "=== Laravel Database Test ===\n\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $tables = DB::select('SHOW TABLES');
    echo "✅ Database connected successfully\n\n";
    
    echo "Available tables:\n";
    foreach ($tables as $table) {
        foreach ($table as $tableName) {
            echo "- $tableName\n";
        }
    }
    echo "\n";
    
    // Test if inquiry tables exist
    $inquiryTables = ['inquiries', 'rooms', 'room_inventories', 'inquiry_quotes', 'holds'];
    
    echo "Checking inquiry system tables:\n";
    foreach ($inquiryTables as $table) {
        $exists = DB::select("SHOW TABLES LIKE '$table'");
        if ($exists) {
            echo "✅ $table exists\n";
        } else {
            echo "❌ $table missing\n";
        }
    }
    echo "\n";
    
    // Test if we can query inquiries table
    try {
        $count = DB::table('inquiries')->count();
        echo "✅ Can query inquiries table (current count: $count)\n";
    } catch (Exception $e) {
        echo "❌ Cannot query inquiries table: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
