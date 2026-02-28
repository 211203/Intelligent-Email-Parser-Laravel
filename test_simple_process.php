<?php

echo "=== Simple Process Test ===\n\n";

// Test direct content processing
echo "📝 Testing Direct Content:\n";
echo "==========================\n";

$content = "Hi, I'm looking for a room for 2 guests from March 20-25. What are your rates?";

$curl = curl_init();

$data = json_encode([
    'content' => $content,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/process/content',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($data)
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

if ($err) {
    echo "❌ cURL Error: $err\n";
} else {
    echo "📡 HTTP Status: $httpCode\n";
    echo "📄 Response:\n";
    echo $response . "\n\n";
}

// Test booking content
echo "📋 Testing Booking Content:\n";
echo "==========================\n";

$bookingContent = "BOOKING CONFIRMATION
Booking ID: BK123456
Guest: John Doe
Email: john@example.com
Check-in: 2025-03-15
Check-out: 2025-03-18
Total Amount: $450.00";

$curl = curl_init();

$data = json_encode([
    'content' => $bookingContent,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/process/content',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($data)
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

if ($err) {
    echo "❌ cURL Error: $err\n";
} else {
    echo "📡 HTTP Status: $httpCode\n";
    echo "📄 Response:\n";
    echo $response . "\n\n";
}

// Test status endpoint
echo "📊 Testing Status:\n";
echo "==================\n";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/process/status',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => [
        'Accept: application/json'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

if ($err) {
    echo "❌ cURL Error: $err\n";
} else {
    echo "📡 HTTP Status: $httpCode\n";
    echo "📄 Response:\n";
    echo $response . "\n\n";
}

echo "🎯 Quick Test Commands:\n";
echo "========================\n";
echo "# Test inquiry content:\n";
echo 'curl -X POST http://127.0.0.1:8000/process/content -H "Content-Type: application/json" -d \'{"content":"Hi, I need a room for 2 guests","client_name":"test"}\'';
echo "\n\n";

echo "# Test booking content:\n";
echo 'curl -X POST http://127.0.0.1:8000/process/content -H "Content-Type: application/json" -d \'{"content":"BOOKING CONFIRMATION\nBooking ID: BK123456","client_name":"test"}\'';
echo "\n\n";

echo "# Check status:\n";
echo "curl -X GET http://127.0.0.1:8000/process/status\n\n";

echo "=== Test Complete ===\n";
