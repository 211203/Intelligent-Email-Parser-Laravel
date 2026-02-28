<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\AI\Agents\BookingAgent;
use App\AI\Tools\DetectInputTypeTool;
use App\AI\Tools\ParseInquiryIntentTool;
use App\Services\InquiryParserService;

// Test the new inquiry system
echo "=== Testing Inquiry Detection System ===\n\n";

// Test 1: DetectInputTypeTool with booking content
echo "Test 1: DetectInputTypeTool with booking content\n";
$bookingContent = "Booking Confirmation
Booking ID: BK123456
Guest: John Doe
Check-in: 2025-03-15
Check-out: 2025-03-18
Total Amount: $450.00
Payment Status: Paid";

$detectTool = new DetectInputTypeTool();
try {
    $result = $detectTool->handle($bookingContent);
    echo "✅ Detection result: " . json_encode($result) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: DetectInputTypeTool with inquiry content
echo "Test 2: DetectInputTypeTool with inquiry content\n";
$inquiryContent = "Hi, I'm looking for a room from March 20-25 for 2 guests. Do you have any availability and what are your rates?";

try {
    $result = $detectTool->handle($inquiryContent);
    echo "✅ Detection result: " . json_encode($result) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: InquiryParserService
echo "Test 3: InquiryParserService\n";
$inquiryService = new InquiryParserService();
try {
    $result = $inquiryService->parse($inquiryContent, "Test Client");
    echo "✅ Inquiry parsing result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
