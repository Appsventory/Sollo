# Routing Setup Guide

Panduan lengkap setup routing dengan web.php dan api.php.

## Overview

Aplikasi menggunakan sistem routing yang terpisah antara **Web Routes** dan **API Routes** melalui `RouteServiceProvider`.

```
RouteServiceProvider.boot()
├── apiRoutes()  → /api/* (dengan Api middleware)
└── webRoutes()  → /* (dengan Web middleware)
```

---

## File Structure

```
app/Routes/
├── web.php          # Web routes (HTML responses)
└── api.php          # API routes (JSON responses)

app/Middleware/
├── Web.php          # Web middleware (sessions, CSRF, security)
└── Api.php          # API middleware (JSON, validation, CORS)

core/Providers/
└── RouteServiceProvider.php  # Route registration
```

---

## RouteServiceProvider Configuration

Lokasi: `core/Providers/RouteServiceProvider.php`

### API Routes
```php
protected function apiRoutes()
{
    Router::group([
        'prefix' => 'api',
        'middleware' => ['api']
    ], function () {
        require $this->basePath('app/Routes/api.php');
    });
}
```

**Features:**
- Prefix: `/api` (semua route akan dimulai dengan `/api`)
- Middleware: `api` (menjalankan Api middleware)
- Responses: JSON format

### Web Routes
```php
protected function webRoutes()
{
    Router::group([
        'middleware' => ['web']
    ], function () {
        require $this->basePath('app/Routes/web.php');
    });
}
```

**Features:**
- Tanpa prefix (route langsung)
- Middleware: `web` (menjalankan Web middleware)
- Responses: HTML/template format

---

## Defining Routes

### Web Routes (`app/Routes/web.php`)

```php
<?php
use Core\Foundation\Routing\Router;

// Simple route to controller
Router::get('/', 'HomeController@index');

// POST route
Router::post('/submit', 'HomeController@store');

// PUT/PATCH route
Router::put('/users/{id}', 'UserController@update');

// Delete route
Router::delete('/users/{id}', 'UserController@destroy');
```

### API Routes (`app/Routes/api.php`)

```php
<?php
use Core\Foundation\Routing\Router;

// GET - Retrieve resource
Router::get('/users', 'Api\UserController@index');
Router::get('/users/{id}', 'Api\UserController@show');

// POST - Create resource
Router::post('/users', 'Api\UserController@store');

// PUT - Update resource
Router::put('/users/{id}', 'Api\UserController@update');

// DELETE - Delete resource
Router::delete('/users/{id}', 'Api\UserController@destroy');

// Closure returning JSON
Router::get('/status', function () {
    return ['status' => 'ok', 'version' => '1.0'];
});
```

---

## URL Examples

### Web Routes
```
GET     /               → HomeController@index
GET     /about          → AboutController@index
POST    /submit         → HomeController@store
```

### API Routes (dengan prefix `/api`)
```
GET     /api/users      → Api\UserController@index
GET     /api/users/1    → Api\UserController@show (id=1)
POST    /api/users      → Api\UserController@store
PUT     /api/users/1    → Api\UserController@update (id=1)
DELETE  /api/users/1    → Api\UserController@destroy (id=1)
GET     /api/status     → JSON {status: "ok", version: "1.0"}
```

---

## Route Parameters

### Capture Parameters
```php
Router::get('/users/{id}', 'UserController@show');
// URL: /users/123 → parameter id=123
```

### Named Parameters
```php
Router::get('/posts/{slug}/comments/{id}', 'PostController@getComment');
// URL: /posts/hello-world/comments/456 → slug=hello-world, id=456
```

### Optional Parameters
```php
Router::get('/search/{query?}', 'SearchController@index');
// URL: /search → query=null
// URL: /search/sollo → query=sollo
```

### Parameter Constraints
```php
Router::get('/posts/{id:(\d+)}', 'PostController@show');
// URL: /posts/123 → Match (numeric)
// URL: /posts/abc → No match
```

---

## Route Methods

```php
// HTTP Methods
Router::get($uri, $action);
Router::post($uri, $action);
Router::put($uri, $action);
Router::patch($uri, $action);
Router::delete($uri, $action);
Router::options($uri, $action);

// Match multiple methods
Router::match(['GET', 'POST'], $uri, $action);

// Match all methods
Router::any($uri, $action);
```

---

## Custom Middleware

### Add Custom Middleware to Routes

```php
// Add custom middleware ke API group
Router::group([
    'prefix' => 'api',
    'middleware' => ['api', 'auth-api']
], function () {
    require $this->basePath('app/Routes/api.php');
});
```

### Create Custom Middleware

```php
// app/Middleware/AuthApi.php
<?php
namespace App\Middleware;

class AuthApi
{
    public function handle()
    {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($token)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }
}
```

---

## Environment Configuration

`.env` file:

```env
APP_URL=http://nineverse_3.test
APP_ENV=development

# API CORS
API_ALLOWED_ORIGINS=*
# Production: API_ALLOWED_ORIGINS=https://yourdomain.com
```

---

## Best Practices

1. **Organize Routes:**
   - Web routes untuk frontend/template
   - API routes untuk JSON responses

2. **Use Named Routes:**
   ```php
   Router::get('/profile', 'ProfileController@show')->name('profile.show');
   ```

3. **Group Related Routes:**
   ```php
   Router::group(['prefix' => 'admin'], function() {
       Router::get('/', 'AdminController@dashboard');
       Router::get('/users', 'AdminController@users');
   });
   ```

4. **Validate Input:**
   - API: Validate Content-Type (automatic via middleware)
   - Web: Validate CSRF token (automatic via middleware)

5. **Use HTTP Methods Correctly:**
   - GET: Retrieve
   - POST: Create
   - PUT/PATCH: Update
   - DELETE: Delete

---

## Troubleshooting


### CORS Issues
- Check `API_ALLOWED_ORIGINS` in `.env`
- Verify browser sending proper headers
- Test with curl: `curl -v http://localhost:8000/api/users`

### JSON Response Issues
- API middleware sets `Content-Type: application/json`
- Controllers must return array/object for JSON
- Web middleware sets HTML headers

---
