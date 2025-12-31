# Middleware Implementation Guide

## Web Middleware (`app/Middleware/Web.php`)

Menangani logic yang spesifik untuk **web routes** (halaman HTML).

### Features yang Diimplementasi:

#### 1. **Session Management**
```php
Session::start();
```
- Memastikan session dimulai untuk setiap request web
- Session automatic start dengan pengaturan security yang tepat

#### 2. **CSRF Token Initialization**
```php
CSRF_TOKEN = Session::get('_token')
```
- Generate dan initialize CSRF token untuk form protection
- Token tersedia di variable `CSRF_TOKEN` untuk digunakan di template

**Penggunaan di Form:**
```html
<form method="POST" action="/submit">
    <input type="hidden" name="_token" value="<?php echo CSRF_TOKEN; ?>">
    <!-- form fields -->
</form>
```

#### 3. **Security Headers**
Set berbagai HTTP header untuk melindungi aplikasi:
- `X-Frame-Options: SAMEORIGIN` - Prevent clickjacking
- `X-Content-Type-Options: nosniff` - Prevent MIME sniffing
- `X-XSS-Protection` - Enable XSS protection di browser
- `Referrer-Policy` - Control referrer information
- `Content-Security-Policy` (production only) - Control resource loading

#### 4. **Request Logging**
Catat setiap request ke file log (development only):
```
File: storage/logs/web_YYYY-MM-DD.log
Format: [2025-12-10 14:30:45] GET /
```

---

## API Middleware (`app/Middleware/Api.php`)

Menangani logic yang spesifik untuk **API routes** (JSON responses).

### Features yang Diimplementasi:

#### 1. **JSON Response Headers**
```php
Content-Type: application/json; charset=UTF-8
Accept: application/json
```
- Memastikan semua response adalah JSON

#### 2. **API Security Headers**
- `X-Content-Type-Options: nosniff` - Prevent MIME sniffing
- `Cache-Control` - Disable caching untuk API responses
- `X-Frame-Options: DENY` - Stricter than web (prevent framing)
- **CORS Headers** untuk cross-origin requests:
  ```
  Access-Control-Allow-Origin: * (atau dari env)
  Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
  Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
  ```

#### 3. **Request Validation**
Validasi `Content-Type` untuk POST/PUT/PATCH requests:
```php
// Jika request punya body, harus application/json
if (method is POST/PUT/PATCH) {
    if (Content-Type != application/json) {
        return 415 Unsupported Media Type
    }
}
```

#### 4. **Comprehensive Logging**
Catat setiap API request dengan detail:
```
File: storage/logs/api_YYYY-MM-DD.log
Format: [2025-12-10 14:30:45] 192.168.1.1 GET /api/users
```
Mencatat: timestamp, IP address, method, URI

#### 5. **Client IP Detection**
Deteksi IP address dengan intelligent fallback:
- Check `HTTP_CLIENT_IP` (shared internet)
- Check `HTTP_X_FORWARDED_FOR` (proxy)
- Fallback ke `REMOTE_ADDR`

---

## Environment Configuration

Tambahkan ke `.env`:

```env
# For API CORS
API_ALLOWED_ORIGINS=*
# atau untuk production:
# API_ALLOWED_ORIGINS=https://yourdomain.com

# Environment
APP_ENV=development
# Atau production untuk enable CSP header yang ketat
```

---

## Log Files

Middleware akan otomatis membuat log directory jika tidak ada:

```
storage/logs/
├── web_2025-12-10.log    # Web requests (development only)
├── web_2025-12-11.log
├── api_2025-12-10.log    # API requests (all environments)
└── api_2025-12-11.log
```

---

## Testing

### Test Web Middleware
```bash
# Access web route
curl http://localhost:8000/

# Check logs
tail -f storage/logs/web_*.log
```

### Test API Middleware
```bash
# GET request
curl http://localhost:8000/api/a

# POST request (with JSON)
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{"name":"John"}'

# POST request (without JSON - should error)
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: text/plain" \
  -d 'invalid'

# Check logs
tail -f storage/logs/api_*.log
```

---

## Customization

### Add Authentication Middleware
Extend `Api.php` untuk add Bearer token validation:

```php
protected function validateAuthentication(): void
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
```

### Add Rate Limiting
Implement rate limiting logic di API middleware:

```php
protected function checkRateLimit(): void
{
    $ip = $this->getClientIp();
    $key = "api:ratelimit:$ip";
    // Implementasi dengan cache/redis
}
```

---

## Security Checklist

✅ Session protection (httponly cookies, secure flag)
✅ CSRF token generation dan protection
✅ Security headers (XSS, clickjacking, MIME sniffing)
✅ Content-Security-Policy (production)
✅ CORS protection
✅ Request validation
✅ Comprehensive logging
✅ Client IP detection (proxy-aware)

