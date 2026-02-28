# Process Email API Documentation

## Overview
The `/process` endpoint automatically fetches the latest unseen Gmail and categorizes it as either a booking or inquiry, then processes it accordingly.

## Base URL
```
http://localhost:8000
```

## Endpoints

### 1. Process Email
**POST** `/process`

Fetches the latest unseen Gmail and processes it based on content type.

#### Request Body
```json
{
  "client_name": "your_client_name"  // Required
}
```

#### Response Examples

**Booking Email Response:**
```json
{
  "success": true,
  "type": "booking",
  "message": "Email processed as booking",
  "data": {
    "booking": {
      "guest_name": "John Doe",
      "booking_id": "BK123456",
      "check_in_date": "2025-03-15",
      "check_out_date": "2025-03-18",
      "total_amount": 450.00,
      "guest_email": "john@example.com",
      "guest_phone": "+1234567890"
    },
    "pdf_path": "/path/to/pdf",
    "processed_at": "2025-02-28T12:00:00Z"
  }
}
```

**Inquiry Email Response:**
```json
{
  "success": true,
  "type": "inquiry",
  "message": "Email processed as inquiry and saved to database",
  "data": {
    "inquiry": {
      "check_in_date": "2025-03-20",
      "check_out_date": "2025-03-25",
      "number_of_guests": 2,
      "number_of_rooms": 1,
      "room_type_requested": "deluxe room",
      "intent_type": "availability"
    },
    "inquiry_id": 123,
    "pdf_path": "/path/to/pdf",
    "processed_at": "2025-02-28T12:00:00Z"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "No new emails found or failed to fetch Gmail",
  "details": {...}
}
```

### 2. Get Status
**GET** `/process/status`

Returns system statistics and status.

#### Response
```json
{
  "success": true,
  "data": {
    "total_inquiries": 4,
    "total_bookings": 10,
    "new_inquiries": 2,
    "last_processed": "2025-02-28T11:30:00Z",
    "system_status": "online"
  }
}
```

### 3. Test Endpoint
**GET** `/process/test`

Tests the email processing with sample data.

#### Query Parameters
- `type` (optional): `booking` or `inquiry` (default: `inquiry`)

#### Response
```json
{
  "success": true,
  "test_type": "inquiry",
  "detected_type": "inquiry",
  "parsed_data": {
    "ok": true,
    "inquiry": {
      "check_in_date": "2025-03-20",
      "check_out_date": "2025-03-25",
      "number_of_guests": 2,
      "intent_type": "availability"
    }
  },
  "sample_content": "Hi, I'm looking for a room..."
}
```

## Processing Workflow

1. **Fetch Gmail** - Retrieves latest unseen email
2. **Extract PDF** - Converts email attachment to text
3. **Detect Type** - AI determines if it's booking or inquiry
4. **Parse Content** - Extracts structured data
5. **Process Accordingly**:
   - **Booking**: Returns parsed booking data
   - **Inquiry**: Saves to database and returns inquiry ID

## Setup Requirements

### Environment Variables
```env
GROQ_API_KEY=your_groq_api_key_here
GROQ_BASE_URL=https://api.groq.com
GROQ_MODEL=llama-3.3-70b-versatile

# Gmail API (if using Gmail integration)
GMAIL_CLIENT_ID=your_gmail_client_id
GMAIL_CLIENT_SECRET=your_gmail_client_secret
GMAIL_REDIRECT_URI=your_redirect_uri
```

### Gmail Service Configuration
The system uses `GmailService` to fetch emails. Ensure:
- Gmail API is enabled in your Google Cloud project
- OAuth credentials are configured
- Service account has access to the target Gmail account

## Testing

### Using cURL

**Test Status:**
```bash
curl -X GET http://localhost:8000/process/status
```

**Test Parsing:**
```bash
curl -X GET http://localhost:8000/process/test?type=inquiry
```

**Process Email:**
```bash
curl -X POST http://localhost:8000/process \
  -H "Content-Type: application/json" \
  -d '{"client_name": "your_client"}'
```

### Using PHP

```php
$client = new GuzzleHttp\Client();

// Process email
$response = $client->post('http://localhost:8000/process', [
    'json' => ['client_name' => 'your_client']
]);

$result = json_decode($response->getBody(), true);
```

## Error Handling

The API returns appropriate HTTP status codes:
- `200` - Success
- `400` - Bad Request (no emails, validation error)
- `500` - Internal Server Error (AI processing failed)

## Logging

All processing steps are logged to `storage/logs/laravel.log`:
```
[2025-02-28 12:00:00] local.INFO: Starting email processing {"client_name": "test_client"}
[2025-02-28 12:00:01] local.INFO: Gmail fetched successfully {"pdf_path": "/path/to/pdf"}
[2025-02-28 12:00:02] local.INFO: Email type detected {"type": "inquiry"}
[2025-02-28 12:00:03] local.INFO: Inquiry saved successfully {"inquiry_id": 123}
```

## Rate Limiting

Consider implementing rate limiting for production:
```php
// In routes/web.php or routes/api.php
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/process', [ProcessEmailController::class, 'process']);
});
```

## Security

- Validate all input parameters
- Sanitize email content before processing
- Implement authentication for production use
- Use HTTPS in production
- Consider API key authentication for external access
