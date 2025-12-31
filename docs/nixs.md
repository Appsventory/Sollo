# 🎨 Nixs Template Engine

A fast, modular template engine with Blade-like syntax for rendering views with layouts, sections, includes, and comprehensive form handling.

## ✨ Features

- 🎯 **Template Rendering** - Render `.nixs.php` files with data variables
- 🏗️ **Layout System** - `@extends()` for template inheritance with cascading layouts
- 📦 **Section Management** - `@section()` and `@yield()` for flexible content blocks
- 🔗 **Partial Includes** - `@include()` for reusable components
- 🔒 **Auto Escaping** - `{{ }}` syntax with automatic XSS protection
- 🎛️ **Control Structures** - @if, @unless, @foreach, @for, @while, @switch/case
- 🌀 **Smart Empty Handling** - `@empty` blocks in `@foreach` for fallback content
- 🌐 **Form Method Override** - Automatic PUT/DELETE/PATCH method handling
- 🔐 **CSRF Protection** - Built-in `@csrf` directive for secure forms
- 🎯 **Global Helpers** - session(), env(), auth() available in all templates
- ⚡ **Modular Architecture** - Organized compiler system with dedicated components
- 💾 **Smart Caching** - Temporary PHP compilation with cache invalidation

## 📚 Table of Contents

1. [Installation & Setup](#installation--setup)
2. [Directory Structure](#directory-structure)
3. [Template Syntax Reference](#template-syntax-reference)
4. [Control Structures](#control-structures)
5. [Layout System](#layout-system)
6. [Form Handling](#form-handling)
7. [Global Helpers](#global-helpers)
8. [Complete Examples](#complete-examples)
9. [Advanced Features](#advanced-features)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)
12. [API Reference](#api-reference)

---

## Installation & Setup

### Basic Usage in Controllers

```php
namespace App\Controllers;

use Core\Foundation\Controller;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::all();
        
        // Render view with data
        view('posts.index', [
            'posts' => $posts,
            'title' => 'All Posts'
        ]);
    }
}
```

### With Global View Helper

```php
// Using the Nixs facade
Nixs::render('posts.index', [
    'posts' => $posts,
    'featured' => Post::featured()->first()
]);
```

---

## Directory Structure

The Nixs template engine is organized into a modular architecture:

```
core/Framework/Velo/Nixs/
├── NixsCompiler.php          # Main orchestrator (8-step pipeline)
├── Compilers/                # Individual compilation units
│   ├── DirectiveCompiler.php    # Control structures (@if, @foreach, etc.)
│   ├── ExpressionCompiler.php   # Variables ({{ $var }}, {!! $raw !!})
│   ├── FormCompiler.php         # Forms (@csrf, @method, form handling)
│   ├── AssetCompiler.php        # Assets (@asset, @url, @route)
│   └── HelperCompiler.php       # Global helpers (session, env, auth)
└── Support/                  # Helper utilities
    ├── PathResolver.php         # Template path resolution
    └── TemplateCache.php        # Compilation caching
```

---

## Template Syntax Reference

### 1. Variable Output

#### Escaped Output (Safe - XSS Protected)
```php
<!-- Double curly braces - automatically escaped -->
{{ $userName }}
{{ $post->title }}
{{ htmlspecialchars($userInput) }}

<!-- Output: 
User: John Doe
Title: My Blog Post
<script> becomes &lt;script&gt;
-->
```

#### Raw Output (No Escaping)
```php
<!-- Triple curly braces or !!...!! - no escaping -->
{!! $htmlContent !!}
{!! $markup !!}

<!-- Use only with trusted content! -->
{!! $post->compiled_html !!}
```

### 2. Comments

```php
<!-- HTML comments are preserved -->
<!-- This will appear in HTML output -->

{{-- Nixs comments are removed during compilation --}}
{{-- These won't appear in HTML output --}}
```

---

## Control Structures

### If / Else / Elseif

```php
@if($user->isAdmin())
    <p>Welcome Admin!</p>
@elseif($user->isPremium())
    <p>Welcome Premium Member!</p>
@else
    <p>Welcome Guest!</p>
@endif
```

**Complex Conditions:**
```php
@if($post->status === 'published' && $post->created_at > now())
    <span class="badge">New Post</span>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
```

### Unless (Inverse If)

```php
@unless($user->isGuest())
    <p>You are logged in as {{ $user->name }}</p>
@endunless

<!-- Equivalent to: @if(!$user->isGuest()) ... @endif -->
```

### Foreach

```php
@foreach($posts as $post)
    <div class="post">
        <h2>{{ $post->title }}</h2>
        <p>{{ $post->content }}</p>
        <small>By {{ $post->author }}</small>
    </div>
@endforeach
```

**With Index:**
```php
@foreach($items as $index => $item)
    <li>{{ $index + 1 }}. {{ $item->name }}</li>
@endforeach
```

**With @empty (No Items Fallback):** ⭐ **NEW FEATURE**

```php
@foreach($posts as $post)
    <article>
        <h2>{{ $post->title }}</h2>
        <p>{{ $post->excerpt }}</p>
    </article>
@empty
    <p class="alert">No posts found. Start by creating your first post!</p>
@endforeach

<!-- This is much cleaner than:
@if(!empty($posts))
    @foreach($posts as $post)
        ...
    @endforeach
@else
    <p>No posts found</p>
@endif
-->
```

### For Loop

```php
@for($i = 0; $i < 10; $i++)
    <p>Iteration {{ $i + 1 }}</p>
@endfor

<!-- Count up to collection size -->
@for($i = 0; $i < count($items); $i++)
    <p>{{ $items[$i]->name }}</p>
@endfor
```

### While Loop

```php
@while($hasMore)
    <p>{{ $current->value }}</p>
    <!-- Update $hasMore somewhere in loop -->
@endwhile
```

### Switch / Case

```php
@switch($user->role)
    @case('admin')
        <span class="badge badge-danger">Administrator</span>
        @break
    
    @case('moderator')
        <span class="badge badge-warning">Moderator</span>
        @break
    
    @case('user')
        <span class="badge badge-info">User</span>
        @break
    
    @default
        <span class="badge badge-secondary">Guest</span>
@endswitch
```

---

## Layout System

### Master Layout File

Create `resources/Views/layouts/master.nixs.php`:

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My Site')</title>
    @asset('css/style.css')
</head>
<body>
    <header>
        <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <p>&copy; 2025 My Site</p>
    </footer>

    @asset('js/app.js')
</body>
</html>
```

### Child Template Extending Layout

Create `resources/Views/posts/index.nixs.php`:

```php
@extends('layouts.master')

@section('title', 'All Posts')

@section('content')
    <div class="posts-container">
        <h1>Blog Posts</h1>
        
        @foreach($posts as $post)
            <article class="post">
                <h2>{{ $post->title }}</h2>
                <p>{{ $post->excerpt }}</p>
                <a href="/posts/{{ $post->id }}">Read More</a>
            </article>
        @empty
            <p class="alert">No posts available yet.</p>
        @endforeach
    </div>
@endsection
```

### Using @include for Partials

Create reusable component `resources/Views/components/post-card.nixs.php`:

```php
<article class="post-card">
    <h3>{{ $post->title }}</h3>
    <p>{{ $post->excerpt }}</p>
    <footer>
        <time>{{ $post->created_at->format('M d, Y') }}</time>
        <span>By {{ $post->author }}</span>
    </footer>
</article>
```

Then use in other templates:

```php
<div class="posts-grid">
    @foreach($posts as $post)
        @include('components.post-card', ['post' => $post])
    @endforeach
</div>

<!-- Or pass entire collection -->
@include('components.post-list', ['items' => $posts])
```

---

## Form Handling

### CSRF Protection

```php
<form method="POST" action="/posts">
    @csrf
    
    <input type="text" name="title" required>
    <textarea name="content"></textarea>
    <button type="submit">Create Post</button>
</form>

<!-- Compiles to: -->
<!-- <input type="hidden" name="csrf_token" value="...secure token..."> -->
```

### Automatic Form Method Handling ⭐ **NEW FEATURE**

Nixs automatically converts HTML5 form methods (DELETE, PUT, PATCH) to POST with a hidden `_method` field:

```php
<!-- In Template -->
<form method="DELETE" action="/posts/{{ $post->id }}">
    @csrf
    <button type="submit" class="btn-danger">Delete Post</button>
</form>

<!-- Compiles To -->
<form method="POST" action="/posts/{{ $post->id }}">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="csrf_token" value="...">
    <button type="submit" class="btn-danger">Delete Post</button>
</form>

<!-- Browser will POST, but your router sees DELETE method -->
```

**Other Form Methods:**

```php
<!-- PUT Method -->
<form method="PUT" action="/posts/{{ $post->id }}">
    @csrf
    <input type="text" name="title" value="{{ $post->title }}">
    <button type="submit">Update</button>
</form>

<!-- PATCH Method -->
<form method="PATCH" action="/posts/{{ $post->id }}/status">
    @csrf
    <select name="status">
        <option>draft</option>
        <option>published</option>
    </select>
    <button type="submit">Update Status</button>
</form>
```

### Manual Method Field

```php
<!-- Explicit method directive -->
<form method="POST" action="/posts/{{ $post->id }}">
    @csrf
    @method('DELETE')
    <button type="submit">Confirm Delete</button>
</form>
```

---

## Global Helpers

All templates have access to global helper functions available as closures.

### session() - Session Helper

```php
<!-- Get session value -->
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<!-- Flash message -->
@if($message = session('error'))
    <div class="alert alert-danger">
        {{ $message }}
    </div>
@endif

<!-- Check if session key exists -->
@if(session('user'))
    Welcome back, {{ session('user')->name }}!
@endif
```

### env() - Environment Helper

```php
<!-- Get environment variables -->
<meta name="app-name" content="{{ env('APP_NAME') }}">
<meta name="app-env" content="{{ env('APP_ENV') }}">

<!-- With default value -->
<p>Debug Mode: {{ env('APP_DEBUG', 'false') }}</p>

<!-- In conditionals -->
@if(env('APP_DEBUG') === 'true')
    <div class="debug-info">
        Environment: {{ env('APP_ENV') }}
    </div>
@endif
```

### auth() - Authentication Helper

```php
<!-- Get current user -->
@if($user = auth())
    <div class="user-profile">
        <p>Welcome, {{ $user->name }}</p>
        <p>Email: {{ $user->email }}</p>
    </div>
@else
    <p><a href="/login">Please log in</a></p>
@endif

<!-- Direct property access -->
<p>Logged in as: {{ auth()->name }}</p>
<p>User ID: {{ auth()->id }}</p>

<!-- Conditional logic -->
@unless(auth())
    <div class="guest-notice">
        You are not logged in
    </div>
@endunless
```

---

## Complete Examples

### Example 1: Blog Post List with Layout

**Master Layout** (`resources/Views/layouts/master.nixs.php`):

```php
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title') - Blog</title>
    <style>
        body { font-family: sans-serif; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .post { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>My Blog</h1>
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        @yield('content')
    </div>
</body>
</html>
```

**Post List View** (`resources/Views/posts/index.nixs.php`):

```php
@extends('layouts.master')

@section('title', 'All Posts')

@section('content')
    <h2>Latest Posts</h2>
    
    @foreach($posts as $post)
        <article class="post">
            <h3>{{ $post->title }}</h3>
            <p>{{ $post->content }}</p>
            <small>By {{ $post->author }} on {{ $post->created_at }}</small>
            
            <form method="DELETE" action="/posts/{{ $post->id }}" style="margin-top: 10px;">
                @csrf
                <button type="submit" style="background: #dc3545; color: white; padding: 5px 10px; border: none; cursor: pointer;">
                    Delete
                </button>
            </form>
        </article>
    @empty
        <p class="alert alert-success">No posts found. Create your first post!</p>
    @endforeach
@endsection
```

### Example 2: User Dashboard

```php
@extends('layouts.master')

@section('title', 'Dashboard')

@section('content')
    @if(auth())
        <div class="user-header">
            <h2>Welcome, {{ auth()->name }}</h2>
            <p>Email: {{ auth()->email }}</p>
        </div>

        <section class="my-posts">
            <h3>My Posts</h3>
            
            @if($userPosts = auth()->posts)
                @foreach($userPosts as $post)
                    <div class="post-summary">
                        <h4>{{ $post->title }}</h4>
                        <p>{{ substr($post->content, 0, 100) }}...</p>
                        <a href="/posts/{{ $post->id }}/edit">Edit</a>
                    </div>
                @endforeach
            @else
                <p>You haven't written any posts yet.</p>
            @endif
        </section>
    @else
        <div class="alert">
            <p>Please <a href="/login">log in</a> to view your dashboard.</p>
        </div>
    @endif
@endsection
```

### Example 3: Form Handling

```php
@extends('layouts.master')

@section('title', 'Edit Post')

@section('content')
    <h2>Edit Post</h2>
    
    @if($errors->any())
        <div class="alert alert-danger">
            <h4>Please fix these errors:</h4>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <form method="PUT" action="/posts/{{ $post->id }}">
        @csrf
        
        <div class="form-group">
            <label for="title">Title</label>
            <input 
                type="text" 
                id="title"
                name="title" 
                value="{{ old('title', $post->title) }}"
                required
            >
        </div>
        
        <div class="form-group">
            <label for="content">Content</label>
            <textarea 
                id="content"
                name="content" 
                required
            >{{ old('content', $post->content) }}</textarea>
        </div>
        
        <div class="form-group">
            <label for="author">Author</label>
            <input 
                type="text" 
                id="author"
                name="author" 
                value="{{ old('author', $post->author) }}"
            >
        </div>
        
        <button type="submit">Update Post</button>
        <a href="/posts/{{ $post->id }}">Cancel</a>
    </form>
@endsection
```

---

## Advanced Features

### 1. Shared Data Across Templates

In your controller:

```php
class BaseController
{
    public function __construct()
    {
        // Share global data with all views
        view()->share([
            'appName' => env('APP_NAME'),
            'currentUser' => auth(),
            'siteSettings' => config('site')
        ]);
    }
}
```

### 2. Template Caching

The engine automatically caches compiled templates:

```php
// Cache location: /tmp/nixs_*.php
// Cache invalidated when template file is modified
// MD5 hash of path + mtime ensures uniqueness
```

### 3. Compilation Pipeline

The NixsCompiler executes an 8-step pipeline:

1. **Load Template** - Read `.nixs.php` file
2. **Handle Layouts** - Process @extends directives
3. **Compile Directives** - Convert control structures
4. **Compile Forms** - Handle @csrf, @method, form methods
5. **Compile Expressions** - Convert {{ }} and {!! !!}
6. **Compile Assets** - Process @asset, @url, @route
7. **Compile Helpers** - Convert session(), env(), auth()
8. **Execute & Render** - Run compiled PHP and output

### 4. Raw PHP Blocks

```php
@php
    $total = 0;
    foreach ($items as $item) {
        $total += $item->price;
    }
@endphp

<p>Total: ${{ $total }}</p>
```

---

## Best Practices

### 1. Escaping User Input

Always escape user-provided content:

```php
<!-- ✅ SAFE: Automatically escaped -->
{{ $post->title }}
{{ $userComment }}

<!-- ❌ UNSAFE: Never trust raw HTML -->
{!! $userComment !!}

<!-- ✅ SAFE: Manually sanitize if needed -->
{!! strip_tags($userComment) !!}
```

### 2. Use @empty for Better UX

```php
<!-- ✅ GOOD: Clear fallback message -->
@foreach($products as $product)
    {{ $product->name }}
@empty
    <p class="message">No products in this category.</p>
@endforeach

<!-- ❌ NOT RECOMMENDED: No feedback to user -->
@if(!empty($products))
    @foreach($products as $product)
        {{ $product->name }}
    @endforeach
@endif
```

### 3. Keep Templates Simple

Move complex logic to controllers:

```php
<!-- ✅ GOOD: Controller handles the logic -->
@foreach($availableProducts as $product)
    {{ $product->name }}
@endforeach

<!-- ❌ BAD: Complex logic in template -->
@foreach($products as $product)
    @if($product->status === 'active' && $product->stock > 0)
        @if(auth() && (auth()->isPremium || auth()->isAdmin))
            {{ $product->name }}
        @endif
    @endif
@endforeach
```

### 4. Form Security

Always include CSRF protection:

```php
<!-- ✅ REQUIRED for state-changing operations -->
<form method="POST" action="/posts">
    @csrf
    <!-- form fields -->
</form>

<form method="DELETE" action="/posts/{{ $id }}">
    @csrf
    <button type="submit">Delete</button>
</form>
```

### 5. Reuse Components with @include

```php
<!-- ✅ DRY: Reusable partial -->
<div class="posts-list">
    @foreach($posts as $post)
        @include('components.post-card', ['post' => $post])
    @endforeach
</div>

<!-- ❌ NOT DRY: Duplicate code -->
<div class="posts-list">
    @foreach($posts as $post)
        <div class="card">
            <h3>{{ $post->title }}</h3>
            <!-- ... lots of HTML ... -->
        </div>
    @endforeach
</div>
```

---

## Troubleshooting

### Common Issues

#### Q: "Template not found" error

```
Error: Template not found: posts.index
```

**Solution:** Check your template path:
- File should be at: `resources/Views/posts/index.nixs.php`
- Use dot notation: `view('posts.index', $data)`
- Ensure `.nixs.php` extension is used

#### Q: Variables not showing in template

```php
// ❌ In controller
view('post.show');  // No data passed!

// ✅ In controller
view('post.show', ['post' => $post]);
```

#### Q: CSRF token missing in forms

```php
// ❌ Missing @csrf
<form method="POST" action="/posts">
    <input type="text" name="title">
</form>

// ✅ Always include @csrf
<form method="POST" action="/posts">
    @csrf
    <input type="text" name="title">
</form>
```

#### Q: Form method not converting to POST

```php
// ✅ This should auto-convert
<form method="DELETE" action="/posts/1">
    @csrf
    <button>Delete</button>
</form>

// If it's not working:
// - Check that method is uppercase: method="DELETE" (not delete)
// - Ensure proper attribute quotes
// - Verify @csrf is present
```

#### Q: Helper functions undefined

```php
// ✅ These work in any template
{{ session('key') }}
{{ env('APP_NAME') }}
{{ auth()->name }}

// If getting errors:
// - Ensure template extends layout
// - Check Session/Env classes are properly imported
// - Verify auth() returns user object or null
```

---

## API Reference

### NixsCompiler Class

**Namespace:** `Core\Framework\Velo\Nixs\NixsCompiler`

#### Static Method: render()

```php
public static function render(string $template, array $data = []): void
```

Renders a template with optional data.

**Parameters:**
- `$template` (string) - Template path using dot notation
- `$data` (array) - Associative array of variables

**Example:**
```php
Nixs::render('posts.index', [
    'posts' => Post::all(),
    'title' => 'All Posts'
]);
```

### Compiler Classes

#### DirectiveCompiler

**Namespace:** `Core\Framework\Velo\Nixs\Compilers\DirectiveCompiler`

Handles all control structure directives:
- `@if`, `@elseif`, `@else`, `@endif`
- `@unless`, `@endunless`
- `@foreach`, `@endforeach` (with `@empty`)
- `@for`, `@endfor`
- `@while`, `@endwhile`
- `@switch`, `@case`, `@default`, `@endswitch`
- `@break`, `@continue`
- Layout directives: `@extends`, `@section`, `@yield`, `@endsection`
- Includes: `@include`

#### ExpressionCompiler

**Namespace:** `Core\Framework\Velo\Nixs\Compilers\ExpressionCompiler`

Handles variable output:
- `{{ $variable }}` - Escaped output (safe)
- `{!! $raw !!}` - Raw output (no escaping)

#### FormCompiler

**Namespace:** `Core\Framework\Velo\Nixs\Compilers\FormCompiler`

Handles form directives:
- `@csrf` - CSRF token field
- `@method('DELETE')` - HTTP method override
- Form method handling - Automatic PUT/DELETE/PATCH conversion

#### AssetCompiler

**Namespace:** `Core\Framework\Velo\Nixs\Compilers\AssetCompiler`

Handles asset directives:
- `@asset('path/to/file')` - Asset path resolution
- `@url('path')` - URL generation
- `@route('name')` - Named route resolution

#### HelperCompiler

**Namespace:** `Core\Framework\Velo\Nixs\Compilers\HelperCompiler`

Converts global helper functions:
- `session()` → `$session()` closure
- `env()` → `$env()` closure
- `auth()` → `$auth()` closure

### Support Classes

#### PathResolver

**Namespace:** `Core\Framework\Velo\Nixs\Support\PathResolver`

```php
public static function resolve(string $template): string
```

Converts dot notation to filesystem path.

#### TemplateCache

**Namespace:** `Core\Framework\Velo\Nixs\Support\TemplateCache`

```php
public static function get(string $sourcePath, callable $compiler): string
public static function store(string $key, string $content): void
```

Manages template compilation caching.

---

## Summary

The Nixs template engine provides a modern, Blade-like syntax for building views with:

✅ **Layout inheritance** for DRY templates
✅ **Smart empty handling** for better UX
✅ **Automatic form method conversion** for REST compliance
✅ **CSRF protection** for security
✅ **Global helpers** for common operations
✅ **Modular architecture** for maintainability
✅ **XSS protection** through auto-escaping
✅ **Fast compilation** with intelligent caching

Start with layouts and partials for code organization, use `@empty` for fallback content, and always include `@csrf` in state-changing forms.


```php
<!-- In layouts/master.nixs.php -->
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title')</title>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
```

### Control Structures
```php
<!-- Conditional statements -->
@if($user->isAdmin())
    <p>Admin Panel</p>
@elseif($user->isModerator())
    <p>Moderator Panel</p>
@else
    <p>User Panel</p>
@endif

<!-- Loops -->
@foreach ($users as $user)
    <div>{{ $user['name'] }}</div>
@empty //foreach suport @empty (opsional)
    <p>No users found.</p>
@endforeach

@for($i = 0; $i < 10; $i++)
    <p>Item {{ $i }}</p>
@endfor

@while($items->hasMore())
    <div>{{ $items->next() }}</div>
@endwhile
```

### Raw PHP Code
```php
@php
    $total = 0;
    foreach($items as $item) {
        $total += $item->price;
    }
@endphp

<p>Total: ${{ number_format($total, 2) }}</p>
```

### Including Partials
```php
<!-- Include partial templates -->
@include('partials.header')
@include('partials.navigation')
@include('components.user-card')
```

## 🚀 Usage Examples

### Basic Template Rendering
```php
use App\Console\Nixs;

// Simple template with data
Nixs::render('home', [
    'title' => 'Welcome',
    'user' => $currentUser,
    'posts' => $recentPosts
]);

// Template with nested path
Nixs::render('admin.dashboard', [
    'stats' => $dashboardStats
]);
```

### Complete Page Example

**Controller:**
```php
class HomeController
{
    public function index()
    {
        $posts = Post::latest()->limit(5)->get();
        $user = Auth::user();
        
        Nixs::render('pages.home', [
            'pageTitle' => 'Home',
            'posts' => $posts,
            'user' => $user,
            'showWelcome' => true
        ]);
    }
}
```

**Template (pages/home.nixs.php):**
```php
@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="homepage">
    @if($showWelcome && $user)
        <div class="welcome-banner">
            <h1>Welcome back, {{ $user->name }}!</h1>
        </div>
    @endif
    
    <section class="recent-posts">
        <h2>Recent Posts</h2>
        
        @if(count($posts) > 0)
            @foreach($posts as $post)
                @include('partials.post-card', ['post' => $post])
            @endforeach
        @else
            <p>No posts available.</p>
        @endif
    </section>
</div>
@endsection
```

**Layout (layouts/app.nixs.php):**
```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My App')</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    @include('partials.header')
    
    <main class="container">
        @yield('content')
    </main>
    
    @include('partials.footer')
    <script src="/js/app.js"></script>
</body>
</html>
```

**Partial (partials/post-card.nixs.php):**
```php
<article class="post-card">
    <h3>{{ $post->title }}</h3>
    <p class="meta">
        By {{ $post->author->name }} on {{ $post->created_at->format('M d, Y') }}
    </p>
    <div class="excerpt">
        {{ substr($post->content, 0, 150) }}...
    </div>
    <a href="/posts/{{ $post->id }}" class="read-more">Read More</a>
</article>
```

## 🌐 Form Method Override

### Automatic Form Method Handling
The engine automatically converts HTML forms with PUT, DELETE, or PATCH methods:

**Input:**
```html
<form method="DELETE" action="/users/123">
    <button type="submit">Delete User</button>
</form>
```

**Compiled Output:**
```html
<form method="POST" action="/users/123">
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit">Delete User</button>
</form>
```

### RESTful Form Examples
```php
<!-- Update form -->
<form method="PUT" action="/users/{{ $user->id }}">
    <input type="text" name="name" value="{{ $user->name }}">
    <button type="submit">Update</button>
</form>

<!-- Delete form -->
<form method="DELETE" action="/posts/{{ $post->id }}">
    <button type="submit">Delete Post</button>
</form>

<!-- Patch form -->
<form method="PATCH" action="/settings">
    <input type="checkbox" name="notifications" {{ $user->notifications ? 'checked' : '' }}>
    <button type="submit">Save Settings</button>
</form>
```

## 📁 File Structure

```
resources/Views/
├── layouts/
│   ├── app.nixs.php
│   ├── admin.nixs.php
│   └── auth.nixs.php
├── pages/
│   ├── home.nixs.php
│   ├── about.nixs.php
│   └── contact.nixs.php
├── partials/
│   ├── header.nixs.php
│   ├── footer.nixs.php
│   └── navigation.nixs.php
├── admin/
│   ├── dashboard.nixs.php
│   └── users.nixs.php
└── errors/
    ├── 404.nixs.php
    └── 500.nixs.php
```

## 🔧 Advanced Examples

### Dynamic Navigation
```php
<!-- partials/navigation.nixs.php -->
<nav class="main-nav">
    <ul>
        @foreach($menuItems as $item)
            <li class="{{ $item['active'] ? 'active' : '' }}">
                <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                
                @if(isset($item['children']) && count($item['children']) > 0)
                    <ul class="submenu">
                        @foreach($item['children'] as $child)
                            <li><a href="{{ $child['url'] }}">{{ $child['title'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
```

### Data Tables with Pagination
```php
<!-- admin/users.nixs.php -->
@extends('layouts.admin')

@section('content')
<div class="users-table">
    <h1>User Management</h1>
    
    @php
        $totalUsers = count($users);
        $perPage = 10;
        $totalPages = ceil($totalUsers / $perPage);
    @endphp
    
    <div class="table-info">
        <p>Showing {{ $totalUsers }} users</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ ucfirst($user->role) }}</td>
                    <td>
                        <a href="/admin/users/{{ $user->id }}">Edit</a>
                        
                        @if($user->role !== 'admin')
                            <form method="DELETE" action="/admin/users/{{ $user->id }}" style="display:inline;">
                                <button type="submit" onclick="return confirm('Delete user?')">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
```

### Conditional Content Loading
```php
<!-- dashboard.nixs.php -->
@extends('layouts.app')

@section('content')
<div class="dashboard">
    @if($user->hasRole('admin'))
        @include('admin.widgets.stats')
        @include('admin.widgets.recent-activity')
    @elseif($user->hasRole('moderator'))
        @include('moderator.widgets.reports')
        @include('moderator.widgets.pending-posts')
    @else
        @include('user.widgets.profile-summary')
        @include('user.widgets.recent-posts')
    @endif
    
    <!-- Common widgets for all users -->
    @include('widgets.notifications')
</div>
@endsection
```

## 🛡️ Security Features

### Automatic XSS Protection
All `{{ }}` output is automatically escaped:

```php
<!-- User input: <script>alert('xss')</script> -->
{{ $userInput }}
<!-- Output: &lt;script&gt;alert('xss')&lt;/script&gt; -->
```

### Safe Variable Handling
```php
<!-- Safe handling of potentially undefined variables -->
{{ $user->name ?? 'Guest' }}
{{ isset($post->title) ? $post->title : 'Untitled' }}

<!-- Array access safety -->
{{ $settings['theme'] ?? 'default' }}
```

## ⚡ Performance Features

### Temporary File Compilation
- Templates are compiled to temporary PHP files
- Unique temporary files prevent conflicts
- Automatic cleanup by system temp directory management

### Efficient Processing
- Single-pass compilation for directives
- Minimal memory footprint
- Fast template resolution with dot notation

## 💡 Best Practices

1. **Use descriptive template names** - `user.profile` instead of `userprofile`
2. **Organize templates logically** - Group related templates in folders
3. **Keep templates simple** - Move complex logic to controllers
4. **Always escape user input** - Use `{{ }}` for user-generated content
5. **Use partials for reusable components** - Header, footer, navigation
6. **Leverage layouts** - Avoid duplicating HTML structure
7. **Handle missing data gracefully** - Use null coalescing operators
8. **Use meaningful section names** - `@section('main-content')` vs `@section('content')`