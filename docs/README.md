# Sollo 3 Documentation

Dokumentasi lengkap untuk Sollo 3 framework.

## 📚 Documentation Files

### Getting Started

- **[file-organization.md](file-organization.md)** - 📁 File dan folder organization guide
- **[routing-setup.md](routing-setup.md)** - 🔀 Setup dan konfigurasi routing dengan web.php dan api.php

### Core Features

- **[middleware.md](middleware.md)** - Middleware system reference
- **[middleware-implementation.md](middleware-implementation.md)** - Detail implementasi Web & Api middleware
- **[database.md](database.md)** - Database migrations dan seeders
- **[request.md](request.md)** - HTTP Request handling
- **[router.md](router.md)** - Router API documentation

### Testing & Tools

- **[testing.md](testing.md)** - 🧪 Guide untuk testing aplikasi
- **[nixs.md](nixs.md)** - Nixs template engine
- **[fany_CLI.md](fany_CLI.md)** - Fany CLI commands

---

## 🚀 Quick Start

### 1. Understand File Organization
Lihat: [file-organization.md](file-organization.md)

Keep root directory clean:
- 📝 Documentation → `docs/`
- 🧪 Tests → `storage/test/`
- 📜 Routes → `app/Routes/`
- 🎮 Controllers → `app/Controllers/`

### 2. Setup Routes
Lihat: [routing-setup.md](routing-setup.md)

```php
// app/Routes/web.php
Router::get('/', 'HomeController@index');

// app/Routes/api.php
Router::get('/users', 'Api\UserController@index');
```

### 3. Understand Middleware
Lihat: [middleware-implementation.md](middleware-implementation.md)

- **Web Middleware**: Sessions, CSRF, security headers
- **API Middleware**: JSON, CORS, validation, logging

### 4. Write Tests
Lihat: [testing.md](testing.md)

```bash
# Test routes
php storage/test/test_routes.php

# Test API
php storage/test/test_api.php

# Test with PHP server
php -S localhost:8000 -t public
```

---

## 📋 Directory Structure

```
docs/
├── README.md                          # This file
├── file-organization.md               # File structure guide 📁
├── routing-setup.md                   # Routing configuration
├── middleware.md                      # Middleware guide
├── middleware-implementation.md       # Middleware details
├── testing.md                         # Testing guide
├── database.md                        # Database operations
├── request.md                         # Request handling
├── router.md                          # Router reference
├── nixs.md                            # Template engine
└── fany_CLI.md                        # CLI commands
```

---

## 🔗 URL Examples

### Web Routes
```
GET  /                  → Homepage
GET  /aaa               → Welcome page
```

### API Routes
```
GET  /api/a             → API status
GET  /api/users         → List users
POST /api/users         → Create user
```

---

## 🧪 Testing

All test files located in `storage/test/`:

```bash
cd /Users/user/Documents/webapps/Sollo_3

# Test route registration
php storage/test/test_routes.php

# Test API endpoint
php storage/test/test_api.php

# Test middleware
php storage/test/test_middleware.php
php storage/test/test_web.php
```

---

## 📝 Configuration

Environment variables in `.env`:

```env
APP_ENV=development
APP_URL=http://localhost:8000

# API CORS
API_ALLOWED_ORIGINS=*
```

---

## 🔒 Security Features

### Web Middleware
✅ Session management
✅ CSRF token generation
✅ Security headers (XSS, clickjacking protection)
✅ Request logging

### API Middleware
✅ JSON response enforcement
✅ CORS headers
✅ Request validation
✅ API logging with IP detection

---

## 📖 Learn More

- Start with [file-organization.md](file-organization.md) to understand the project structure
- See individual documentation files for detailed information
- All test files are in `storage/test/`
- Logs are in `storage/logs/`

---

## 🤝 Contributing

When adding new features:
1. Place documentation in `docs/`
2. Place test files in `storage/test/`
3. Keep root directory clean
4. Update this README with new documentation links

