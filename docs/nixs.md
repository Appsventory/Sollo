# 🎨 Nixs Template Engine

A lightweight, fast template engine with Blade-like syntax for rendering views with layouts, sections, and includes.

## ✨ Features

- 🎯 **Template Rendering** - Render `.nixs.php` files with data variables
- 🏗️ **Layout System** - `@extends()` for template inheritance
- 📦 **Section Management** - `@section()` and `@yield()` for content blocks
- 🔗 **Partial Includes** - `@include()` for reusable components
- 🔒 **Auto Escaping** - `{{ }}` syntax with XSS protection
- 🎛️ **Control Structures** - Support for if, foreach, for, while loops
- 📝 **Raw PHP** - `@php` blocks for complex logic
- 🌐 **Form Method Override** - Automatic PUT/DELETE/PATCH form handling
- ⚡ **Temporary Compilation** - Compile to temporary PHP files for execution

## 📖 API Reference

### Static Methods

#### `render(string $template, array $data = []): void`
Renders a template file with optional data variables and outputs the result.

**Parameters:**
- `$template` - Template path using dot notation (e.g., 'home', 'user.profile')
- `$data` - Associated array of variables to pass to template

## 💡 Template Syntax

### Variable Output
```php
<!-- Escaped output (safe) -->
{{ $userName }}
{{ $post->title }}
{{ date('Y-m-d') }}

<!-- Variables are automatically escaped for XSS protection -->
{{ $userInput }} <!-- <script> becomes &lt;script&gt; -->
```

### Layout Inheritance
```php
<!-- In child template -->
@extends('layouts.master')

@section('title', 'Page Title')

@section('content')
    <h1>Welcome to my page</h1>
@endsection
```

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
app/Views/
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