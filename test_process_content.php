<?php

echo "=== Testing Process Content Endpoint ===\n\n";

// Sample email contents for testing
$bookingContent = "BOOKING CONFIRMATION
Booking ID: BK123456
Guest: John Doe
Email: john@example.com
Phone: +1234567890
Check-in: 2025-03-15
Check-out: 2025-03-18
Total Amount: $450.00
Room Type: Deluxe Room
Payment Status: Paid";

$inquiryContent = "Hi, I'm looking for accommodation from March 20-25 for 2 guests. We need 1 deluxe room. What are your rates and availability? Please contact me at rahul@gmail.com or 9876543210. Thanks, Rahul";

$pricingInquiry = "Hello, I need a quote for a family suite for 4 guests from April 1-5 (4 nights). Our budget is around 40000 INR. Please provide best rates. - Priya";

echo "📧 Test 1: Booking Content\n";
echo "===========================\n";
echo "Content:\n$bookingContent\n\n";

$curl = curl_init();

$data = json_encode([
    'content' => $bookingContent,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/process/content',
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

echo "📧 Test 2: Inquiry Content\n";
echo "============================\n";
echo "Content:\n$inquiryContent\n\n";

$curl = curl_init();

$data = json_encode([
    'content' => $inquiryContent,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/process/content',
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

echo "📧 Test 3: Pricing Inquiry\n";
echo "==========================\n";
echo "Content:\n$pricingInquiry\n\n";

$curl = curl_init();

$data = json_encode([
    'content' => $pricingInquiry,
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/process/content',
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

echo "📧 Test 4: Empty Content (Error Case)\n";
echo "=====================================\n";

$curl = curl_init();

$data = json_encode([
    'content' => '',
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/process/content',
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

echo "📋 Manual Testing Commands:\n";
echo "==========================\n";
echo "# Test booking content:\n";
echo 'curl -X POST http://localhost:8000/process/content \' -H "Content-Type: application/json" -d \'{"content":"BOOKING CONFIRMATION\nBooking ID: BK123456\nGuest: John Doe","client_name":"test"}\'\'';
echo "\n\n";

echo "# Test inquiry content:\n";
echo 'curl -X POST http://localhost:8000/process/content \' -H "Content-Type: application/json" -d \'{"content":"Hi, I need a room for 2 guests","client_name":"test"}\'\'';
echo "\n\n";

echo "# Check status:\n";
echo "curl -X GET http://localhost:8000/process/status\n\n";

echo "=== Test Complete ===\n";
