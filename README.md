# KPN Validation Test API

API untuk menerima dan menyimpan data POST dalam format CSV.

## 🌐 Endpoint

**URL:** `https://kpn-validation-test.ilmuprogram.app`  
**Method:** `POST`  
**Authentication:** Bearer Token

## 🔐 Authentication

Gunakan header `Authorization` dengan Bearer token:

```
Authorization: Bearer kpn-validation-secret-token-2026
```

## 📤 Request

### Headers

| Header | Value |
|--------|-------|
| `Authorization` | `Bearer kpn-validation-secret-token-2026` |
| `Content-Type` | `application/json` |

### Body (JSON)

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "08123456789",
  "message": "Your message here"
}
```

## 📥 Response

### Success (200)

```json
{
  "success": true,
  "message": "Data saved successfully.",
  "filename": "inbound_2026-02-03_15-19-41_abc123.csv",
  "timestamp": "2026-02-03 15:19:41",
  "data_received": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08123456789",
    "message": "Your message here"
  }
}
```

### Error Responses

| HTTP Code | Message |
|-----------|---------|
| 401 | Authorization header is required |
| 401 | Invalid authorization format |
| 403 | Invalid token |
| 405 | Method not allowed (only POST) |
| 400 | Request body is empty |

## 🧪 Example Usage

### cURL

```bash
curl -X POST https://kpn-validation-test.ilmuprogram.app \
  -H "Authorization: Bearer kpn-validation-secret-token-2026" \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","phone":"08123456789"}'
```

### PHP

```php
<?php
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://kpn-validation-test.ilmuprogram.app',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer kpn-validation-secret-token-2026',
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ])
]);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
```

### JavaScript (Fetch)

```javascript
fetch('https://kpn-validation-test.ilmuprogram.app', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer kpn-validation-secret-token-2026',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'John Doe',
    email: 'john@example.com'
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

### Python

```python
import requests

response = requests.post(
    'https://kpn-validation-test.ilmuprogram.app',
    headers={
        'Authorization': 'Bearer kpn-validation-secret-token-2026',
        'Content-Type': 'application/json'
    },
    json={
        'name': 'John Doe',
        'email': 'john@example.com'
    }
)
print(response.json())
```

## 📁 Data Storage

Data yang diterima akan disimpan di folder `inbound/` dalam format CSV dengan nama file:

```
inbound_YYYY-MM-DD_HH-mm-ss_uniqueID.csv
```

### CSV Structure

| Column | Description |
|--------|-------------|
| `_metadata_received_at` | Timestamp penerimaan |
| `_metadata_remote_ip` | IP address pengirim |
| `_metadata_user_agent` | User agent pengirim |
| `_metadata_content_type` | Content type request |
| `...` | Field dari body request |

## 🛠️ Service Management

```bash
# Check status
sudo systemctl status kpn-validation
sudo systemctl status cloudflared

# Restart services
sudo systemctl restart kpn-validation
sudo systemctl restart cloudflared

# View logs
sudo journalctl -u kpn-validation -f
sudo journalctl -u cloudflared -f
```

## 📋 Tech Stack

- **Backend:** PHP 8.x
- **Tunnel:** Cloudflare Tunnel
- **Server:** PHP Built-in Server (localhost:8080)

## 📄 License

MIT License
