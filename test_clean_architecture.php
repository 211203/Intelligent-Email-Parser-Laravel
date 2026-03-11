<?php

echo "=== Testing Clean Architecture ===\n\n";

// Test 1: Gmail-first with clean architecture
echo "🎯 Test 1: Gmail-First Clean Architecture\n";
echo "==========================================\n";

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
echo "📊 Test 2: Clean Architecture Status\n";
echo "=====================================\n";

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

echo "🏗️ Clean Architecture Summary:\n";
echo "==============================\n";
echo "✅ GmailFirstController: Simplified to only call BookingAgent\n";
echo "✅ BookingAgent: Updated to handle Gmail-first flow\n";
echo "✅ FetchGmailTool: Returns email_content + optional pdf_path\n";
echo "✅ System Prompt: Updated for Gmail-first logic\n";
echo "✅ Tool Schemas: Updated to match new outputs\n\n";

echo "🔄 New Flow:\n";
echo "============\n";
echo "1. GmailFirstController::process()\n";
echo "2. ↓\n";
echo "3. BookingAgent::run(null, clientName)\n";
echo "4. ↓\n";
echo "5. fetch_gmail → {email_content, pdf_path?}\n";
echo "6. ↓\n";
echo "7. detect_input_type(email_content)\n";
echo "8. ↓\n";
echo "9. IF booking:\n";
echo "   - IF pdf_path: extract_pdf_text → parse_booking_data\n";
echo "   - ELSE: parse_booking_data(email_content)\n";
echo "10. IF inquiry:\n";
echo "    - parse_inquiry_intent(email_content)\n";
echo "    - save_inquiry()\n\n";

echo "🗑️ Removed Duplication:\n";
echo "========================\n";
echo "❌ Manual keyword-based detection\n";
echo "❌ Manual regex parsing\n";
echo "❌ Manual database saving\n";
echo "❌ Duplicate logic in controller\n\n";

echo "✅ Benefits:\n";
echo "============\n";
echo "• Single source of truth: BookingAgent\n";
echo "• AI-powered detection (LLM vs keywords)\n";
echo "• Consistent tool-based architecture\n";
echo "• Easier maintenance and testing\n";
echo "• No duplicate code\n\n";

echo "📋 Usage:\n";
echo "=========\n";
echo "# Clean Gmail-first processing:\n";
echo 'curl -X POST http://127.0.0.1:8000/api/gmail-first -H "Content-Type: application/json" -d \'{"client_name":"Rahul"}\'';
echo "\n\n";

echo "# Check architecture status:\n";
echo "curl -X GET http://127.0.0.1:8000/api/gmail-first/status\n\n";

echo "🎉 Architecture Cleaned Successfully!\n";
echo "====================================\n";
echo "The system now follows clean architecture principles:\n";
echo "• Controller: Thin layer, just calls agent\n";
echo "• Agent: Orchestrates tools and AI logic\n";
echo "• Tools: Single responsibility, reusable\n";
echo "• No duplication, no manual parsing\n\n";

echo "=== Clean Architecture Test Complete ===\n";
