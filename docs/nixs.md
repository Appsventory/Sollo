# Nixs Template Engine

Nixs adalah template engine Sollo (mirip Blade). File view berakhiran **`.nixs.php`** di `resources/Views/`.

---

## Render view

```php
// Di controller / route
view('home', ['title' => 'Hello']);

// Atau
use Core\Framework\Velo\Nixs\NixsCompiler as Nixs;
Nixs::render('posts.index', ['posts' => $posts]);
```

Path dot-notation: `posts.index` → `resources/Views/posts/index.nixs.php`.

---

## Output

| Syntax | Perilaku |
|--------|----------|
| `{{ $var }}` | Escaped (aman XSS) |
| `{!! $html !!}` | Raw HTML (hati-hati) |

```nixs
<h1>{{ $title }}</h1>
<div>{!! $trustedHtml !!}</div>
```

---

## Layout & section

```nixs
{{-- resources/Views/layouts/app.nixs.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Sollo')</title>
</head>
<body>
    @yield('content')
</body>
</html>
```

```nixs
{{-- resources/Views/posts/index.nixs.php --}}
@extends('layouts.app')

@section('title')
Posts
@endsection

@section('content')
  <h1>All posts</h1>
@endsection
```

| Directive | Fungsi |
|-----------|--------|
| `@extends('layouts.app')` | Pakai layout |
| `@section('name') ... @endsection` | Isi section |
| `@yield('name')` | Tampilkan section |
| `@yield('name', 'default')` | Section + default |

Layout, section, dan include diselesaikan **saat render** (bukan saat kompilasi), jadi satu layout
aman dipakai banyak halaman dan cache template tidak pernah memuat konten halaman lain.
Section bersarang didukung; section yang sama di child menimpa default dari layout.
Konten di luar `@section` pada halaman yang memakai `@extends` tidak dicetak.

---

## Kontrol alur

```nixs
@if($user)
  Halo, {{ $user }}
@elseif($guest)
  Tamu
@else
  ...
@endif

@unless($ready)
  Belum siap
@endunless

@foreach($items as $item)
  <li>{{ $item }}</li>
@empty
  <p>Kosong</p>
@endforeach

@for($i = 0; $i < 3; $i++)
  {{ $i }}
@endfor

@while($cond)
  ...
@endwhile

@switch($status)
  @case('open')
    Open
    @break
  @default
    Other
@endswitch
```

`@if($x) ... @endif` boleh dalam **satu baris**.

---

## Include & component

```nixs
@include('partials.header')
@include('partials.row', ['x' => 1])
@include('cards.' . $type)   {{-- path dinamis --}}
```

`@include` selalu dimuat saat render dan ikut melihat variabel parent (mis. `$item` di dalam `@foreach`).
Mengedit partial langsung terlihat tanpa `cache:clear`.

```nixs
@nixscomponent('Alert', ['type' => 'info'])
  <p>Pesan</p>
@endnixscomponent
```

Di dalam komponen, isi blok tersedia sebagai `{!! $body !!}` (atau `$slot`).
Komponen boleh dipakai di halaman ber-`@extends`.

PHP:

```php
echo Nixs::component('Alert', ['type' => 'warn', 'body' => 'Hi']);
```

Include/komponen/layout yang tidak ditemukan melempar exception (halaman error), bukan komentar HTML.

---

## Form

```nixs
<form method="POST" action="/posts">
  @csrf
  @method('PUT')
  <input name="title" value="{{ $title }}">
  <button type="submit">Save</button>
</form>
```

| Directive | Fungsi |
|-----------|--------|
| `@csrf` | Token CSRF |
| `@method('PUT')` | Spoof method (PUT/PATCH/DELETE) |
| `{{ old('title') }}` | Input sebelumnya setelah validasi gagal (password tidak pernah di-flash) |
| `session('errors')` | Pesan error validasi (array `field => [pesan]`), tersedia setelah redirect |

---

## Helper global

| Di template | Sumber |
|-------------|--------|
| `@env('APP_NAME')` / `env('APP_NAME')` | `.env` |
| `@session('key')` / `session('key')` | Session |
| `@auth()` / `auth()` | User di session |
| `{{ asset('css/app.css') }}` / `@asset(...)` | File di `public/` |
| `{{ storage('images/a.png') }}` / `@storage(...)` | File di `public/storage/` |
| `{{ Storage::url('images/a.png') }}` | Sama, class Storage |
| `@url('/about')` | URL aplikasi |
| `@route('name', [...])` | Named route (jika ada) |
| `{{ config('app.name') }}` | Nilai dari `config/app.php` |

---

## Komentar (syntax khusus Nixs)

```nixs
{{-- komentar Blade-style --}}
@** komentar Nixs **@
[[ + komentar blok + ]]
```

Blok `<pre><code>` dan fenced ` ``` ` di-mask agar contoh kode tidak ikut di-compile.

---

## PHP block

```nixs
@php
  $x = 1 + 1;
@endphp
```

---

## Cache

Template dikompilasi ke `storage/framework/views/nixs_<hash>_<mtime>.php`. File terkompilasi hanya bergantung
pada source-nya sendiri; saat source berubah, versi lama otomatis dihapus.

```bash
php fany cache:clear
php fany nixs:clear
```

---

## Contoh lengkap

```nixs
@extends('layouts.app')

@section('content')
  <h1>{{ $title }}</h1>

  <img src="{{ storage('images/logo.png') }}" alt="Logo">

  @foreach($posts as $post)
    <article>
      <h2>{{ $post->title }}</h2>
      <a href="/posts/{{ $post->id }}">Read</a>
    </article>
  @empty
    <p>Belum ada post.</p>
  @endforeach

  <form method="POST" action="/posts">
    @csrf
    <input name="title" required>
    <button type="submit">Create</button>
  </form>
@endsection
```
