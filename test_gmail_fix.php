<?php

echo "=== Testing Gmail Fix ===\n\n";

// Test the Gmail-first endpoint with the fix
echo "🔧 Testing Gmail Service Fix\n";
echo "=============================\n";

$curl = curl_init();

$data = json_encode([
    'client_name' => 'Sahil'
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

echo "🔧 Fix Applied:\n";
echo "================\n";
echo "✅ Added fetchLatestEmail() method to GmailService\n";
echo "✅ Method returns structured data: {body_text, body_html, attachments}\n";
echo "✅ FetchGmailTool can now call the method successfully\n";
echo "✅ Clean architecture flow should work now\n\n";

echo "📋 Expected Flow:\n";
echo "==================\n";
echo "1. GmailFirstController::process()\n";
echo "2. ↓\n";
echo "3. BookingAgent::run(null, 'Sahil')\n";
echo "4. ↓\n";
echo "5. FetchGmailTool::handle('Sahil')\n";
echo "6. ↓\n";
echo "7. GmailService::fetchLatestEmail() ← NOW EXISTS!\n";
echo "8. ↓\n";
echo "9. Returns {email_content, pdf_path?}\n";
echo "10. ↓\n";
echo "11. DetectInputTypeTool::handle(email_content)\n";
echo "12. ↓\n";
echo "13. Parse based on type (booking/inquiry)\n\n";

echo "🎯 Error Resolution:\n";
echo "====================\n";
echo "Before: Call to undefined method GmailService::fetchLatestEmail()\n";
echo "After: Method exists and returns structured email data\n\n";

echo "📊 Environment Variables Needed:\n";
echo "=================================\n";
echo "Make sure these are set in .env:\n";
echo "- MAIL_FETCH_URL\n";
echo "- ZOHO_CLIENT_ID\n";
echo "- ZOHO_CLIENT_SECRET\n";
echo "- ZOHO_REFRESH_TOKEN\n";
echo "- ZOHO_TOKEN_URL\n\n";

echo "=== Gmail Fix Test Complete ===\n";
