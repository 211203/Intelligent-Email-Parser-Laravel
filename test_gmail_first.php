<?php

echo "=== Testing Gmail-First Processing ===\n\n";

// Test 1: Gmail-first processing
echo "📧 Test 1: Gmail-First Processing\n";
echo "=================================\n";

$curl = curl_init();

$data = json_encode([
    'client_name' => 'Rahul'
]);

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/api/gmail-first',
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

// Test 2: Status check
echo "📊 Test 2: Gmail-First Status\n";
echo "=============================\n";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/api/gmail-first/status',
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

// Test 3: Multiple runs to see different sample emails
echo "🔄 Test 3: Multiple Processing Runs\n";
echo "===================================\n";

for ($i = 1; $i <= 3; $i++) {
    echo "Run $i:\n";
    
    $curl = curl_init();
    
    $data = json_encode([
        'client_name' => 'TestClient' . $i
    ]);
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://127.0.0.1:8000/api/gmail-first',
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
        echo "  ❌ Error: $err\n";
    } else {
        $result = json_decode($response, true);
        if ($result && isset($result['type'])) {
            echo "  ✅ Type: {$result['type']}, Source: {$result['source']}\n";
            if (isset($result['data']['gmail_info']['subject'])) {
                echo "  📧 Subject: {$result['data']['gmail_info']['subject']}\n";
            }
        }
    }
    echo "\n";
}

echo "🎯 Gmail-First Processing Workflow:\n";
echo "==================================\n";
echo "1. 📧 Fetch latest Gmail (simulated with sample data)\n";
echo "2. 🔍 Determine content type (booking vs inquiry)\n";
echo "3. 📝 Parse content based on type\n";
echo "4. 💾 Save inquiry to database (if inquiry)\n";
echo "5. 📊 Return structured response\n\n";

echo "📋 Usage Commands:\n";
echo "==================\n";
echo "# Process Gmail (what you requested):\n";
echo 'curl -X POST http://127.0.0.1:8000/api/gmail-first -H "Content-Type: application/json" -d \'{"client_name":"Rahul"}\'';
echo "\n\n";

echo "# Check status:\n";
echo "curl -X GET http://127.0.0.1:8000/api/gmail-first/status\n\n";

echo "🔧 Production Setup:\n";
echo "====================\n";
echo "1. Configure Gmail API credentials\n";
echo "2. Replace simulated Gmail fetching with real GmailService calls\n";
echo "3. Add Gmail webhook for real-time processing\n";
echo "4. Configure email marking as 'read' after processing\n\n";

echo "📊 Expected Response Structure:\n";
echo "=================================\n";
echo "{\n";
echo "  \"success\": true,\n";
echo "  \"type\": \"inquiry|booking\",\n";
echo "  \"source\": \"gmail_inquiry|gmail_booking|gmail_pricing\",\n";
echo "  \"data\": {\n";
echo "    \"gmail_info\": {\n";
echo "      \"subject\": \"Email subject\",\n";
echo "      \"from\": \"sender@email.com\",\n";
echo "      \"date\": \"2025-02-28\",\n";
echo "      \"has_attachments\": false\n";
echo "    },\n";
echo "    \"parsed_data\": { ... },\n";
echo "    \"inquiry_id\": 123,\n";
echo "    \"processed_at\": \"2025-02-28T12:00:00Z\"\n";
echo "  }\n";
echo "}\n\n";

echo "=== Gmail-First Test Complete ===\n";
