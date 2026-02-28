<?php

echo "=== Testing Simple Endpoint ===\n\n";

// Test 1: Inquiry content
echo "📝 Test 1: Inquiry Content\n";
echo "==========================\n";

$content = "Hi, I'm looking for a room for 2 guests from March 20-25. What are your rates and availability? Please contact me at rahul@gmail.com. Thanks!";

$curl = curl_init();

$data = json_encode([
    'content' => $content,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/simple',
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

// Test 2: Booking content
echo "📋 Test 2: Booking Content\n";
echo "==========================\n";

$bookingContent = "BOOKING CONFIRMATION
Booking ID: BK123456
Guest: John Doe
Email: john@example.com
Phone: +1-234-567-8900
Check-in: 2025-03-15
Check-out: 2025-03-18
Total Amount: $450.00
Room Type: Deluxe Room
Payment Status: Paid";

$curl = curl_init();

$data = json_encode([
    'content' => $bookingContent,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/simple',
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

// Test 3: Pricing inquiry
echo "💰 Test 3: Pricing Inquiry\n";
echo "==========================\n";

$pricingContent = "Hello, I need a quote for a family suite for 4 guests from April 1-5. What are your rates? My budget is around 40000 INR. - Priya";

$curl = curl_init();

$data = json_encode([
    'content' => $pricingContent,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/simple',
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

// Test 4: Empty content (should fail)
echo "❌ Test 4: Empty Content (Should Fail)\n";
echo "=====================================\n";

$curl = curl_init();

$data = json_encode([
    'content' => '',
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/simple',
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

// Test 5: Status endpoint
echo "📊 Test 5: Status Endpoint\n";
echo "==========================\n";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/simple/status',
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
echo 'curl -X POST http://127.0.0.1:8000/simple -H "Content-Type: application/json" -d \'{"content":"Hi, I need a room for 2 guests","client_name":"test"}\'';
echo "\n\n";

echo "# Test booking content:\n";
echo 'curl -X POST http://127.0.0.1:8000/simple -H "Content-Type: application/json" -d \'{"content":"BOOKING CONFIRMATION\nBooking ID: BK123456","client_name":"test"}\'';
echo "\n\n";

echo "# Check status:\n";
echo "curl -X GET http://127.0.0.1:8000/simple/status\n\n";

echo "📋 File Upload Test:\n";
echo "====================\n";
echo "# Test with file upload:\n";
echo "curl -X POST http://127.0.0.1:8000/simple -F \"file=@your_file.txt\" -F \"client_name=test\"\n\n";

echo "=== Test Complete ===\n";
