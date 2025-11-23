# 🛣️ Router (web.php)

Simple yet powerful routing system inspired by NineVerse for handling URLs and HTTP requests.

## ✨ Features

- 🌐 **HTTP Methods Support** - GET, POST, PUT, DELETE, and ANY
- 🔗 **Dynamic Parameters** - URL parameters like `/user/{id}`
- 🛡️ **Middleware Support** - Filter requests before reaching controllers
- 🎯 **Controller Actions** - Support for both string and array formats:
  - `'Controller@method'` (string)
  - `[Controller, method]` (array)
- 🔄 **HTTP Method Override** - Override via form field `_method`
- 🚫 **Automatic 404 Fallback** - Built-in error handling

## 📝 Route Definition

```php
use App\Core\Router;

// GET route without middleware
Router::get('/', 'HomeController@index');

// POST route with middleware
Router::post('/user', 'UserController@store')->middleware('AuthMiddleware');

// Route with parameters and middleware
Router::get('/post/{id}', 'PostController@show')->middleware('AuthMiddleware');
```

##### Closure Routing Examples

```php
//Example 1: Simple Response
Router::get('/', function() {
    return "Welcome to NineVerse!";
});

//Example 2: JSON Response
Router::get('/api/status', function() {
    return [
        'status' => 'online',
        'version' => '2.0.0'
    ];
});

//Example 3: With Parameters
Router::get('/user/{id}', function($id) {
    return [
        'id' => $id,
        'name' => 'User ' . $id
    ];
});

//Example 4: Multiple Parameters
Router::get('/post/{id}/comment/{commentId}', function($id, $commentId) {
    return [
        'post_id' => $id,
        'comment_id' => $commentId
    ];
});

//Example 5: Return JSON explicitly
Router::get('/api/posts', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            ['id' => 1, 'title' => 'Post 1'],
            ['id' => 2, 'title' => 'Post 2']
        ]
    ]);
});
```

## 🛡️ Middleware

Middleware can be defined as:

- Class with `handle()` method
- Class and specific method with parameters, example: `'Throttle@check:60&1'`


## 💡 Simple Middleware Example

```php
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

## 🔧 Supported Middleware Formats

Router supports various middleware formats with high flexibility:

| Format | Description | Example | Called As |
|--------|-------------|---------|-----------|
| `AuthMiddleware` | Call `handle()` without parameters | `'AuthMiddleware'` | `new AuthMiddleware()->handle()` |
| `RoleMiddleware#admin` | Call `handle()` with parameters | `'RoleMiddleware#admin'` | `new RoleMiddleware()->handle('admin')` |
| `Throttle@check` | Call static method without parameters | `'Throttle@check'` | `Throttle::check()` |
| `Throttle@check:60&1` | Call static method with multiple parameters | `'Throttle@check:60&1'` | `Throttle::check('60', '1')` |
| `Verify@handle:token` | Call static method `handle()` with parameters | `'Verify@handle:token'` | `Verify::handle('token')` |

> **Notes:**
> - Use `@` to specify method and add `:` for parameters if needed
> - Use `#` as shorthand for calling `handle()` method with parameters
> - `#` format is suitable for middleware instances, while `@` is for static methods

### 🎯 Middleware with Parameters Example

```php
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

**Usage:**

```php
Router::get('/admin', 'AdminController@index')->middleware('RoleMiddleware#admin');
```

Or using static:

```php
Router::get('/admin', 'AdminController@index')->middleware('RoleMiddleware@handle:admin');
```

## ❌ Error Handling

If route is not found, automatically calls:

```php
\App\Controllers\ErrorController@index();
```

Or displays `404 Not Found` message if error controller doesn't exist.