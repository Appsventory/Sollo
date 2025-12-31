# 🛣️ Router Class

A powerful routing system for handling HTTP requests with dynamic parameters, middleware support, and flexible controller actions.

## ✨ Features

- 🌐 **HTTP Methods** - Support for GET, POST, PUT, DELETE, and ANY
- 🔗 **Dynamic Parameters** - URL parameters like `/user/{id}` 
- 🛡️ **Middleware Chain** - Execute middleware before controllers
- 🎯 **Flexible Actions** - Support string (`Controller@method`) and array formats
- 🔄 **Method Override** - Override HTTP method via `_method` form field
- 📦 **Route Registration** - Simple fluent API for route definition
- ⚡ **Pattern Matching** - Regex-based URL matching with parameters
- 🚫 **404 Handling** - Automatic fallback to ErrorController or default message


## 💡 Basic Usage

### Simple Routes
```php
use App\Core\Router;

// Basic GET route
Router::get('/', 'HomeController@index');

// POST route
Router::post('/users', 'UserController@store');

// Route with parameter
Router::get('/user/{id}', 'UserController@show');

// Array format action
Router::get('/profile', ['ProfileController', 'index']);
```

### Routes with Middleware
```php
// Single middleware
Router::get('/dashboard', 'DashboardController@index')
       ->middleware('AuthMiddleware');

// Multiple middleware (chained)
Router::post('/admin/users', 'AdminController@createUser')
       ->middleware('AuthMiddleware')
       ->middleware('AdminMiddleware');
```

### ANY Method Route
```php
// Accepts GET, POST, PUT, DELETE
Router::any('/api/resource', 'ApiController@handle');
```

## 🛡️ Middleware Formats

The router supports three middleware formats:

### 1. Simple Middleware
```php
Router::get('/protected', 'Controller@method')
       ->middleware('AuthMiddleware');
```
**Calls:** `new AuthMiddleware()->handle()`

### 2. Static Method with Parameters
```php
Router::get('/api/data', 'ApiController@getData')
       ->middleware('Throttle@check:60&1');
```
**Calls:** `Throttle::check('60', '1')`

### 3. Instance Method with Parameters
```php
Router::get('/admin', 'AdminController@index')
       ->middleware('RoleMiddleware#admin');
```
**Calls:** `new RoleMiddleware()->handle('admin')`

## 📝 Writing Middleware Files

### Basic Middleware Structure
Create middleware files in `app/Middleware/` directory:

```php
<?php
namespace App\Middleware;

class AuthMiddleware
{
    public function handle()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
    }
}
```

### Middleware with Parameters
```php
<?php
namespace App\Middleware;

class RoleMiddleware
{
    public function handle($role)
    {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }
    }
}
```

### Static Middleware Methods
```php
<?php
namespace App\Middleware;

class Throttle
{
    public static function check($requests, $minutes)
    {
        $key = $_SERVER['REMOTE_ADDR'];
        $current = $_SESSION['throttle'][$key] ?? 0;
        
        if ($current >= $requests) {
            http_response_code(429);
            echo 'Too Many Requests';
            exit;
        }
        
        $_SESSION['throttle'][$key] = $current + 1;
    }
}
```

### Multiple Parameter Middleware
```php
<?php
namespace App\Middleware;

class Permission
{
    public static function check($permission, $fallback = null)
    {
        $userPermissions = $_SESSION['user']['permissions'] ?? [];
        
        if (!in_array($permission, $userPermissions)) {
            if ($fallback) {
                header("Location: /{$fallback}");
            } else {
                http_response_code(403);
                echo 'Permission Denied';
            }
            exit;
        }
    }
}
```

### CORS Middleware
```php
<?php
namespace App\Middleware;

class CorsMiddleware
{
    public function handle()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
```

### Logging Middleware
```php
<?php
namespace App\Middleware;

class AuditLog
{
    public static function log($action)
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user']['id'] ?? 'guest',
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'uri' => $_SERVER['REQUEST_URI']
        ];
        
        file_put_contents(
            'logs/audit.log', 
            json_encode($logEntry) . "\n", 
            FILE_APPEND
        );
    }
}
```

## 🚀 Request Dispatch

### Basic Dispatch
```php
// In public/index.php
Router::dispatch();
```

### Method Override
```html
<!-- HTML form with method override -->
<form method="POST" action="/users/123" method="DELETE">
    <button type="submit">Delete User</button>
</form>
```

## 📝 Complete Examples

### User Management Routes
```php
// User routes
Router::get('/users', 'UserController@index');
Router::get('/user/{id}', 'UserController@show');
Router::post('/users', 'UserController@store')
       ->middleware('AuthMiddleware');
Router::put('/user/{id}', 'UserController@update')
       ->middleware('AuthMiddleware');
Router::delete('/user/{id}', 'UserController@destroy')
       ->middleware('AuthMiddleware')
       ->middleware('AdminMiddleware');
```

### API Routes with Throttling
```php
// API routes with rate limiting
Router::get('/api/posts', 'ApiController@getPosts')
       ->middleware('Throttle@check:100&1');

Router::post('/api/posts', 'ApiController@createPost')
       ->middleware('AuthMiddleware')
       ->middleware('Throttle@check:10&1');

Router::any('/api/webhook', 'WebhookController@handle')
       ->middleware('ValidateSignature@verify:secret_key');
```

### Admin Panel Routes
```php
// Protected admin routes
Router::get('/admin', 'AdminController@dashboard')
       ->middleware('AuthMiddleware')
       ->middleware('RoleMiddleware#admin');

Router::get('/admin/users', 'AdminController@users')
       ->middleware('AuthMiddleware')
       ->middleware('RoleMiddleware#admin');

Router::post('/admin/users/{id}/ban', 'AdminController@banUser')
       ->middleware('AuthMiddleware')
       ->middleware('RoleMiddleware#admin')
       ->middleware('AuditLog@log:user_ban');
```

## 🔧 Advanced Usage

### Dynamic Parameter Extraction
```php
// Route: /post/{id}/comment/{commentId}
Router::get('/post/{id}/comment/{commentId}', 'CommentController@show');

// In CommentController:
public function show($postId, $commentId)
{
    // $postId and $commentId are automatically passed
    $post = Post::find($postId);
    $comment = Comment::find($commentId);
}
```

### Middleware with Complex Parameters
```php
// Rate limiting with custom parameters
Router::post('/api/upload', 'UploadController@store')
       ->middleware('RateLimit@check:5&60&ip'); // 5 requests per 60 seconds per IP

// Permission checking with multiple roles
Router::get('/moderate', 'ModerationController@index')
       ->middleware('Permission@check:moderate&admin&super_admin');
```

## ❌ Error Handling

### 404 Not Found
When no route matches:
1. Sets HTTP response code to 404
2. Tries to call `\App\Controllers\ErrorController->index()`
3. Falls back to "404 Not Found" message if ErrorController doesn't exist

### Custom Error Controller
```php
namespace App\Controllers;

class ErrorController
{
    public function index()
    {
        // Custom 404 page logic
        include 'views/errors/404.php';
    }
}
```

## 🏗️ Route Organization

### Grouping Routes by Feature
```php
// Authentication routes
Router::get('/login', 'AuthController@showLogin');
Router::post('/login', 'AuthController@login');
Router::post('/logout', 'AuthController@logout')
       ->middleware('AuthMiddleware');

// User profile routes
Router::get('/profile', 'ProfileController@show')
       ->middleware('AuthMiddleware');
Router::put('/profile', 'ProfileController@update')
       ->middleware('AuthMiddleware');

// Public routes
Router::get('/', 'HomeController@index');
Router::get('/about', 'PageController@about');
Router::get('/contact', 'PageController@contact');
```

## 💡 Best Practices

1. **Use descriptive URIs** - `/users/{id}` instead of `/u/{id}`
2. **Apply middleware consistently** - Always protect sensitive routes
3. **Follow RESTful conventions** - Use appropriate HTTP methods
4. **Handle parameters safely** - Validate dynamic parameters in controllers
5. **Organize routes logically** - Group related routes together
6. **Use method override** - For HTML forms that need PUT/DELETE
7. **Implement proper error handling** - Create custom ErrorController
8. **Chain middleware appropriately** - Order matters for middleware execution