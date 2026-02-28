# Inquiry System Implementation

## Overview
The booking system has been enhanced to support both booking confirmations and customer inquiries through intelligent email content detection and parsing.

## New Architecture

### Files Added

#### 1. `app/AI/Tools/DetectInputTypeTool.php`
- **Purpose**: Detects whether email content is a booking confirmation or an inquiry
- **Input**: Raw email text content
- **Output**: `{ "ok": true, "type": "booking" | "inquiry" }`
- **Method**: Uses LLM with lightweight prompt for classification

#### 2. `app/AI/Tools/ParseInquiryIntentTool.php`
- **Purpose**: Parses inquiry details from email text
- **Input**: Email text content, optional client name
- **Output**: `{ "ok": true, "inquiry": { structured inquiry data } }`
- **Integration**: Uses `InquiryParserService` for actual parsing

#### 3. `app/Services/InquiryParserService.php`
- **Purpose**: Contains LLM prompt and logic for inquiry data extraction
- **Extracted Fields**:
  - `check_in_date` (string|null)
  - `check_out_date` (string|null)
  - `number_of_guests` (integer|null)
  - `number_of_rooms` (integer|null)
  - `room_type_requested` (string|null)
  - `intent_type` (string|required) - availability | pricing | reservation | general

### Files Modified

#### `app/AI/Agents/BookingAgent.php`
- **Enhanced**: Now handles both booking and inquiry workflows
- **New Tools Added**: `detect_input_type`, `parse_inquiry_intent`
- **Updated Flow**:
  1. `fetch_gmail` → Get email PDF
  2. `extract_pdf_text` → Extract text content
  3. `detect_input_type` → Classify email type
  4. **Branch**:
     - **If booking**: `parse_booking_data` → Return booking JSON
     - **If inquiry**: `parse_inquiry_intent` → Return inquiry JSON

## Execution Flow

### For Booking Emails
```
FetchGmailTool → ExtractPdfTextTool → DetectInputTypeTool (returns "booking") → ParseBookingDataTool → Booking JSON
```

### For Inquiry Emails
```
FetchGmailTool → ExtractPdfTextTool → DetectInputTypeTool (returns "inquiry") → ParseInquiryIntentTool → Inquiry JSON
```

## Response Formats

### Booking Response (Existing)
```json
{
  "guest_name": "John Doe",
  "booking_id": "BK123456",
  "check_in_date": "2025-03-15",
  "check_out_date": "2025-03-18",
  "total_amount": 450.00,
  "guest_email": "john@example.com",
  "guest_phone": "+1234567890"
}
```

### Inquiry Response (New)
```json
{
  "check_in_date": "2025-03-20",
  "check_out_date": "2025-03-25",
  "number_of_guests": 2,
  "number_of_rooms": 1,
  "room_type_requested": "deluxe",
  "intent_type": "availability"
}
```

## Key Features

1. **Non-Breaking**: Existing booking flow remains unchanged
2. **Intelligent Detection**: LLM-powered content classification
3. **Structured Extraction**: Consistent JSON output for both types
4. **Error Handling**: Comprehensive validation and error messages
5. **Extensible**: Easy to add new inquiry types and fields

## Usage Example

```php
// The BookingAgent now automatically handles both types
$agent = new BookingAgent(/* dependencies */);
$result = $agent->run(null, "client_name");

// Result will be either booking data OR inquiry data
// depending on email content detection
```

## Testing

A test file `test_inquiry_system.php` is provided to verify:
- Input type detection for both booking and inquiry content
- Inquiry parsing functionality
- Error handling

## Future Enhancements

The system is designed to easily accommodate:
- Additional inquiry types (cancellation, modification, etc.)
- More sophisticated response generation
- Integration with availability/pricing services
- Automated inquiry response workflows
