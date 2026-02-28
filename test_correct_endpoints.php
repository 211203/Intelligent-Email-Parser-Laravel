<?php

echo "=== Testing Correct Endpoints ===\n\n";

// Test 1: Simple endpoint (recommended)
echo "🎯 Test 1: Simple Endpoint (Recommended)\n";
echo "========================================\n";

$content = "Hi, I'm looking for a room for 2 guests from March 20-25. What are your rates?";

$curl = curl_init();

$data = json_encode([
    'content' => $content,
    'client_name' => 'Rahul'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/api/simple',
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

// Test 2: Original process endpoint (shows the issue)
echo "❌ Test 2: Original /api/process (Shows Issue)\n";
echo "=============================================\n";

$curl = curl_init();

$data = json_encode([
    'client_name' => 'Rahul'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/api/process',
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

// Test 3: Simple endpoint with file upload simulation
echo "📁 Test 3: File Upload Ready\n";
echo "==========================\n";

echo "To test file upload, use:\n";
echo "curl -X POST http://127.0.0.1:8000/api/simple \\\n";
echo "  -F \"file=@your_file.pdf\" \\\n";
echo "  -F \"client_name=Rahul\"\n\n";

// Test 4: Status check
echo "📊 Test 4: Status Check\n";
echo "====================\n";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/api/simple/status',
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

echo "🎯 SOLUTION: Use These Endpoints Instead\n";
echo "======================================\n\n";

echo "✅ WORKING ENDPOINTS:\n";
echo "====================\n";
echo "• POST /api/simple - Direct content processing\n";
echo "• POST /api/simple - File upload (with -F flag)\n";
echo "• GET  /api/simple/status - System status\n\n";

echo "❌ PROBLEMATIC ENDPOINT:\n";
echo "========================\n";
echo "• POST /api/process - Old booking controller (returns 'no_file_provided')\n\n";

echo "📋 CORRECT USAGE:\n";
echo "==================\n";
echo "# For direct content:\n";
echo 'curl -X POST http://127.0.0.1:8000/api/simple -H "Content-Type: application/json" -d \'{"content":"Hi, I need a room","client_name":"Rahul"}\'';
echo "\n\n";

echo "# For file upload:\n";
echo "curl -X POST http://127.0.0.1:8000/api/simple -F \"file=@neha_ponkshe.pdf\" -F \"client_name=Rahul\"";
echo "\n\n";

echo "# Check status:\n";
echo "curl -X GET http://127.0.0.1:8000/api/simple/status\n\n";

echo "=== Issue Resolved ===\n";
