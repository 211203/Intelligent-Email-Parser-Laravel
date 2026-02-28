# Updated Process Email API Documentation

## Overview
The email processing system now supports two modes:
1. **Gmail Integration** - Fetches and processes latest unseen Gmail
2. **Direct Content** - Processes email content directly without Gmail

## Base URL
```
http://localhost:8000
```

## Endpoints

### 1. Process Gmail (Original)
**POST** `/process`

Fetches the latest unseen Gmail and processes it. Works with or without PDF attachments.

#### Request Body
```json
{
  "client_name": "your_client_name"  // Required
}
```

#### Response Examples
Same as before, but now handles both PDF and text content.

### 2. Process Content Directly (NEW)
**POST** `/process/content`

Processes email content directly without Gmail fetching.

#### Request Body
```json
{
  "content": "email content here",  // Required
  "client_name": "your_client_name"  // Optional, defaults to 'default'
}
```

#### Response Examples

**Booking Content Response:**
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
    "pdf_path": null,
    "processed_at": "2025-02-28T12:00:00Z"
  }
}
```

**Inquiry Content Response:**
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
    "pdf_path": null,
    "processed_at": "2025-02-28T12:00:00Z"
  }
}
```

**Error Response (Empty Content):**
```json
{
  "success": false,
  "message": "Email content is required"
}
```

### 3. Get Status
**GET** `/process/status`

Returns system statistics and status.

### 4. Test Endpoint
**GET** `/process/test`

Tests the email processing with sample data.

## Processing Workflows

### Gmail Integration Workflow
1. **Fetch Gmail** - Retrieves latest unseen email
2. **Extract Content** - From PDF attachment OR direct text
3. **Detect Type** - AI determines booking vs inquiry
4. **Parse Content** - Extracts structured data
5. **Process Accordingly** - Save inquiry or return booking

### Direct Content Workflow
1. **Receive Content** - Direct email content in request
2. **Detect Type** - AI determines booking vs inquiry
3. **Parse Content** - Extracts structured data
4. **Process Accordingly** - Save inquiry or return booking

## Usage Examples

### Using cURL - Direct Content

**Process Booking Content:**
```bash
curl -X POST http://localhost:8000/process/content \
  -H "Content-Type: application/json" \
  -d '{
    "content": "BOOKING CONFIRMATION\nBooking ID: BK123456\nGuest: John Doe\nCheck-in: 2025-03-15\nCheck-out: 2025-03-18\nTotal Amount: $450.00",
    "client_name": "test_client"
  }'
```

**Process Inquiry Content:**
```bash
curl -X POST http://localhost:8000/process/content \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Hi, I need a room for 2 guests from March 20-25. What are your rates?",
    "client_name": "test_client"
  }'
```

### Using PHP

```php
$client = new GuzzleHttp\Client();

// Process content directly
$response = $client->post('http://localhost:8000/process/content', [
    'json' => [
        'content' => 'Hi, I need a room for 2 guests...',
        'client_name' => 'your_client'
    ]
]);

$result = json_decode($response->getBody(), true);

if ($result['success']) {
    if ($result['type'] === 'booking') {
        // Handle booking data
        $booking = $result['data']['booking'];
    } else {
        // Handle inquiry data
        $inquiry = $result['data']['inquiry'];
        $inquiryId = $result['data']['inquiry_id'];
    }
}
```

### Using JavaScript

```javascript
// Process email content directly
async function processEmailContent(content, clientName = 'default') {
    try {
        const response = await fetch('http://localhost:8000/process/content', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                content: content,
                client_name: clientName
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log(`Processed as ${result.type}:`, result.data);
            return result;
        } else {
            console.error('Processing failed:', result.message);
            return null;
        }
    } catch (error) {
        console.error('Request failed:', error);
        return null;
    }
}

// Usage examples
processEmailContent('BOOKING CONFIRMATION\nBooking ID: BK123456...');
processEmailContent('Hi, I need a room for 2 guests...');
```

## Sample Content for Testing

### Booking Content
```
BOOKING CONFIRMATION
Booking ID: BK123456
Guest: John Doe
Email: john@example.com
Phone: +1234567890
Check-in: 2025-03-15
Check-out: 2025-03-18
Total Amount: $450.00
Room Type: Deluxe Room
Payment Status: Paid
```

### Inquiry Content
```
Hi, I'm looking for accommodation from March 20-25 for 2 guests. We need 1 deluxe room. What are your rates and availability? Please contact me at rahul@gmail.com or 9876543210. Thanks, Rahul
```

### Pricing Inquiry
```
Hello, I need a quote for a family suite for 4 guests from April 1-5 (4 nights). Our budget is around 40000 INR. Please provide best rates. - Priya
```

## Benefits of Direct Content Processing

1. **No Gmail Setup Required** - Works immediately without API configuration
2. **Faster Testing** - Direct content processing for quick testing
3. **Flexible Integration** - Can be integrated with any email source
4. **No Dependencies** - Doesn't require Gmail service or PDF processing
5. **Immediate Results** - Perfect for development and testing

## When to Use Each Endpoint

### Use `/process` when:
- You have Gmail API configured
- You want to process actual emails from Gmail
- You need PDF attachment support
- You want automated email processing

### Use `/process/content` when:
- You're testing the system
- You have email content from another source
- You don't have Gmail API setup yet
- You want to integrate with other email providers
- You need manual content processing

## Error Handling

Both endpoints return appropriate HTTP status codes:
- `200` - Success
- `400` - Bad Request (missing content, validation error)
- `500` - Internal Server Error (AI processing failed)

## Testing

Run the test script to verify functionality:
```bash
php test_process_content.php
```

This will test both booking and inquiry content processing with various examples.
