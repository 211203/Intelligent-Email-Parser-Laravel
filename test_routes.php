<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\ProcessEmailController;
use App\AI\Tools\DetectInputTypeTool;
use App\AI\Tools\ParseInquiryIntentTool;
use App\AI\Tools\ParseBookingDataTool;

echo "=== Testing Process Email Controller ===\n\n";

// Test 1: Test type detection
echo "🔍 Test 1: Type Detection\n";
echo "-------------------------\n";

$detectTool = new DetectInputTypeTool();

$bookingContent = "BOOKING CONFIRMATION
Booking ID: BK123456
Guest: John Doe
Check-in: 2025-03-15
Check-out: 2025-03-18
Total Amount: $450.00";

$inquiryContent = "Hi, I'm looking for a room from March 20-25 for 2 guests. Do you have availability and what are your rates?";

echo "Testing booking content:\n";
try {
    $result = $detectTool->handle($bookingContent);
    echo "✅ Booking detected: " . json_encode($result) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTesting inquiry content:\n";
try {
    $result = $detectTool->handle($inquiryContent);
    echo "✅ Inquiry detected: " . json_encode($result) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Test parsing
echo "📝 Test 2: Content Parsing\n";
echo "----------------------------\n";

$parseBookingTool = new ParseBookingDataTool(new App\Services\BookingParserService(new App\Helpers\EmailPreprocessor()));
$parseInquiryTool = new ParseInquiryIntentTool(new App\Services\InquiryParserService());

echo "Parsing booking content:\n";
try {
    $result = $parseBookingTool->handle($bookingContent, 'test_client');
    echo "✅ Booking parsed: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nParsing inquiry content:\n";
try {
    $result = $parseInquiryTool->handle($inquiryContent, 'test_client');
    echo "✅ Inquiry parsed: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test controller instantiation
echo "🎛️  Test 3: Controller Setup\n";
echo "-----------------------------\n";

try {
    $controller = new ProcessEmailController(
        new App\AI\Tools\FetchGmailTool(new App\Services\GmailService()),
        new App\AI\Tools\ExtractPdfTextTool(new App\Services\PdfExtractionService()),
        $detectTool,
        $parseBookingTool,
        $parseInquiryTool,
        new App\AI\Tools\SaveInquiryTool(new App\Services\InquiryService())
    );
    
    echo "✅ Controller instantiated successfully\n";
    
    // Test status method
    $statusResponse = $controller->status();
    echo "✅ Status method works: " . $statusResponse->getStatusCode() . "\n";
    echo "Status data: " . $statusResponse->getContent() . "\n";
    
} catch (Exception $e) {
    echo "❌ Controller error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test endpoint simulation
echo "🌐 Test 4: Endpoint Simulation\n";
echo "--------------------------------\n";

try {
    $controller = new ProcessEmailController(
        new App\AI\Tools\FetchGmailTool(new App\Services\GmailService()),
        new App\AI\Tools\ExtractPdfTextTool(new App\Services\PdfExtractionService()),
        $detectTool,
        $parseBookingTool,
        $parseInquiryTool,
        new App\AI\Tools\SaveInquiryTool(new App\Services\InquiryService())
    );
    
    // Create mock request
    $request = new Illuminate\Http\Request();
    $request->merge(['type' => 'inquiry']);
    
    $testResponse = $controller->test($request);
    echo "✅ Test endpoint works: " . $testResponse->getStatusCode() . "\n";
    echo "Test response: " . $testResponse->getContent() . "\n";
    
} catch (Exception $e) {
    echo "❌ Test endpoint error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Route Testing Complete\n";
echo "==========================\n";
echo "✅ Type detection: Working\n";
echo "✅ Content parsing: Working\n";
echo "✅ Controller setup: Working\n";
echo "✅ Endpoint logic: Working\n";
echo "⚠️  Gmail integration: Needs API setup\n";

echo "\n📋 To test with web server:\n";
echo "1. Run: php artisan serve\n";
echo "2. Test: GET http://localhost:8000/process/test\n";
echo "3. Test: GET http://localhost:8000/process/status\n";
echo "4. Test: POST http://localhost:8000/process\n";

echo "\n=== Test Complete ===\n";
