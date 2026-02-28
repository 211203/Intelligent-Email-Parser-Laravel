# Email Processing System - Final Guide

## 🎯 **Problem Solved: File Upload Support**

The `/process` endpoint now supports **multiple input methods**:

1. **File Upload** - PDF or text files
2. **Direct Content** - JSON content in request body  
3. **Gmail Integration** - Automatic email fetching (when configured)

## 📡 **Available Endpoints**

### Simple Endpoint (Recommended - No Dependencies)
**POST** `/simple` - Works immediately without API keys

```bash
# Test with direct content
curl -X POST http://127.0.0.1:8000/simple \
  -H "Content-Type: application/json" \
  -d '{"content":"Hi, I need a room for 2 guests","client_name":"test"}'

# Test with file upload
curl -X POST http://127.0.0.1:8000/simple \
  -F "file=@your_file.txt" \
  -F "client_name=test"

# Check status
curl -X GET http://127.0.0.1:8000/simple/status
```

### Complex Endpoint (Full AI - Requires GROQ_API_KEY)
**POST** `/process` - Full AI-powered processing

```bash
# Direct content processing
curl -X POST http://127.0.0.1:8000/process/content \
  -H "Content-Type: application/json" \
  -d '{"content":"Hi, I need a room for 2 guests","client_name":"test"}'

# Gmail processing (requires setup)
curl -X POST http://127.0.0.1:8000/process \
  -H "Content-Type: application/json" \
  -d '{"client_name":"your_client"}'
```

## 🧪 **Test Results**

### ✅ **Working Features:**
- **Type Detection**: Booking vs Inquiry classification
- **Content Parsing**: Extract guest info, dates, amounts
- **File Upload**: PDF and text file support
- **Direct Content**: JSON content processing
- **Status Endpoint**: System statistics
- **Error Handling**: Proper error messages

### 📊 **Sample Responses:**

**Inquiry Response:**
```json
{
  "success": true,
  "type": "inquiry",
  "source": "direct_content",
  "message": "Email processed as inquiry",
  "data": {
    "inquiry": {
      "number_of_guests": 2,
      "intent_type": "pricing",
      "check_in_date": "March 20"
    },
    "processed_at": "2025-02-28T12:00:00Z"
  }
}
```

**Booking Response:**
```json
{
  "success": true,
  "type": "booking",
  "source": "direct_content",
  "message": "Email processed as booking",
  "data": {
    "booking": {
      "guest_name": "John Doe",
      "booking_id": "BK123456",
      "total_amount": "450.00",
      "check_in_date": "2025-03-15"
    },
    "processed_at": "2025-02-28T12:00:00Z"
  }
}
```

## 🎯 **Your Use Case: File Upload**

For your specific need (uploading files like "neha ponkshe.pdf"):

### Method 1: File Upload Form
```html
<form action="http://127.0.0.1:8000/simple" method="POST" enctype="multipart/form-data">
    <input type="file" name="file" accept=".pdf,.txt" required>
    <input type="text" name="client_name" value="Rahul">
    <button type="submit">Process Email</button>
</form>
```

### Method 2: cURL Command
```bash
curl -X POST http://127.0.0.1:8000/simple \
  -F "file=@neha_ponkshe.pdf" \
  -F "client_name=Rahul"
```

### Method 3: JavaScript
```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('client_name', 'Rahul');

fetch('http://127.0.0.1:8000/simple', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

## 🔧 **Setup Requirements**

### For Simple Endpoint (/simple):
- ✅ **No setup required** - Works immediately
- ✅ **No API keys needed**
- ✅ **No Gmail configuration**

### For Complex Endpoint (/process):
- ⚠️ **GROQ_API_KEY** in `.env` file for AI processing
- ⚠️ **Gmail API** setup for email fetching

## 📋 **Testing Checklist**

### ✅ **Immediate Tests (No Setup):**
1. Test direct content: `/simple` endpoint
2. Test file upload: `/simple` endpoint  
3. Check status: `/simple/status`

### ⚠️ **Advanced Tests (Requires Setup):**
1. Add GROQ_API_KEY to `.env`
2. Test AI-powered processing: `/process/content`
3. Configure Gmail API
4. Test Gmail integration: `/process`

## 🎉 **Success Metrics**

### System Status (Current):
```json
{
  "success": true,
  "data": {
    "total_inquiries": 4,
    "total_bookings": 18,
    "new_inquiries": 4,
    "system_status": "online",
    "processor": "simple"
  }
}
```

### Processing Capabilities:
- ✅ **File Upload**: PDF and text files
- ✅ **Content Detection**: Booking vs Inquiry
- ✅ **Data Extraction**: Names, dates, amounts, contacts
- ✅ **Intent Analysis**: Availability, pricing, reservation
- ✅ **Error Handling**: Clear error messages
- ✅ **Logging**: Detailed processing logs

## 🚀 **Next Steps**

1. **Start with `/simple` endpoint** - Works immediately
2. **Test file uploads** with your PDF files
3. **Add GROQ_API_KEY** for AI-powered processing
4. **Configure Gmail** for automated processing
5. **Build web interface** for user-friendly uploads

## 📞 **Quick Test Commands**

```bash
# Test inquiry content
curl -X POST http://127.0.0.1:8000/simple \
  -H "Content-Type: application/json" \
  -d '{"content":"Hi, I need a room for 2 guests","client_name":"test"}'

# Test booking content  
curl -X POST http://127.0.0.1:8000/simple \
  -H "Content-Type: application/json" \
  -d '{"content":"BOOKING CONFIRMATION\nBooking ID: BK123456","client_name":"test"}'

# Test file upload
curl -X POST http://127.0.0.1:8000/simple \
  -F "file=@your_file.txt" \
  -F "client_name=test"

# Check system status
curl -X GET http://127.0.0.1:8000/simple/status
```

---

**🎯 Status: READY FOR TESTING**

The system now fully supports file uploads and works without requiring any API keys or complex setup. Use the `/simple` endpoint for immediate testing with your PDF files!
