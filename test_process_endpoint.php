<?php

echo "=== Testing /process Endpoint ===\n\n";

// Test 1: Test endpoint without API key
echo "🧪 Test 1: Test endpoint (without API key)\n";
echo "-----------------------------------------\n";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/process/test',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
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

// Test 2: Status endpoint
echo "📊 Test 2: Status endpoint\n";
echo "-------------------------\n";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/process/status',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
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

// Test 3: Process endpoint (will need Gmail setup)
echo "🔄 Test 3: Process endpoint (full workflow)\n";
echo "-------------------------------------------\n";

$data = json_encode(['client_name' => 'test_client']);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/process',
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

echo "📋 Manual Testing Instructions:\n";
echo "===============================\n";
echo "1. Start Laravel server:\n";
echo "   php artisan serve\n\n";
echo "2. Test endpoints:\n";
echo "   GET  http://localhost:8000/process/status\n";
echo "   GET  http://localhost:8000/process/test\n";
echo "   POST http://localhost:8000/process\n\n";
echo "3. For POST endpoint, send:\n";
echo '   {"client_name": "your_client_name"}' . "\n\n";
echo "4. Check logs for debugging:\n";
echo "   tail -f storage/logs/laravel.log\n\n";

echo "=== Test Complete ===\n";
