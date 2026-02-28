<?php

echo "=== Testing File Upload Endpoint ===\n\n";

// Test 1: Direct content (no file)
echo "📝 Test 1: Direct Content (No File)\n";
echo "=====================================\n";

$curl = curl_init();

$data = json_encode([
    'content' => "Hi, I'm looking for a room for 2 guests from March 20-25. What are your rates?",
    'client_name' => 'test_client'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/process',
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

// Test 2: Create a sample text file and upload it
echo "📁 Test 2: File Upload\n";
echo "========================\n";

// Create a sample text file
$sampleContent = "BOOKING CONFIRMATION
Booking ID: BK123456
Guest: John Doe
Email: john@example.com
Check-in: 2025-03-15
Check-out: 2025-03-18
Total Amount: $450.00
Room Type: Deluxe Room";

$tempFile = 'temp_booking.txt';
file_put_contents($tempFile, $sampleContent);

echo "Created temporary file: $tempFile\n";
echo "File content:\n$sampleContent\n\n";

// Upload the file using cURL
$curl = curl_init();

$postData = [
    'file' => new CURLFile($tempFile, 'booking.txt', 'text/plain'),
    'client_name' => 'test_client'
];

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/process',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

// Clean up temp file
unlink($tempFile);

if ($err) {
    echo "❌ cURL Error: $err\n";
} else {
    echo "📡 HTTP Status: $httpCode\n";
    echo "📄 Response:\n";
    echo $response . "\n\n";
}

// Test 3: Empty request (should fail)
echo "❌ Test 3: Empty Request (Should Fail)\n";
echo "====================================\n";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/process',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => [],
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

echo "📋 Manual Testing Instructions:\n";
echo "===============================\n";
echo "# Test with direct content:\n";
echo 'curl -X POST http://127.0.0.1:8000/process \' -H "Content-Type: application/json" -d \'{"content":"Hi, I need a room","client_name":"test"}\'\'';
echo "\n\n";

echo "# Test with file upload:\n";
echo "curl -X POST http://127.0.0.1:8000/process -F \"file=@your_file.txt\" -F \"client_name=test\"";
echo "\n\n";

echo "# Check status:\n";
echo "curl -X GET http://127.0.0.1:8000/process/status\n\n";

echo "🎯 Expected Behavior:\n";
echo "====================\n";
echo "✅ Direct content: Should process immediately\n";
echo "✅ File upload: Should extract and process file content\n";
echo "✅ Empty request: Should return error with helpful message\n";
echo "✅ All responses: Include 'source' field indicating input type\n";

echo "\n=== Test Complete ===\n";
