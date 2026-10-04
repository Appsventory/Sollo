# Routing

Rute didaftarkan di:

- `app/Routes/web.php` — middleware group **web** (session, CSRF, security header)
- `app/Routes/api.php` — middleware group **api** (prefix `/api`, JSON, CORS, tanpa session/CSRF)

---

## Dasar

```php
use Core\Foundation\Routing\Router;

// Closure
Router::get('/hello', function () {
    return 'Hello';
});

// View langsung
Router::view('/', 'home', ['title' => 'Home']);

// Controller (App\Controllers\PostController)
Router::get('/posts', 'PostController@index');
Router::post('/posts', 'PostController@store');
Router::put('/posts/{id}', 'PostController@update');
Router::delete('/posts/{id}', 'PostController@destroy');

// Beberapa method sekaligus
Router::match(['GET', 'POST'], '/contact', 'ContactController@handle');
Router::any('/ping', fn() => 'pong');

// Redirect
Router::redirect('/old', '/new', 301);
```

### Nilai balik (closure **dan** controller)

| Return | Hasil |
|--------|-------|
| `array` / objek `JsonSerializable` | JSON (`Content-Type: application/json`) |
| `string` / angka | di-echo apa adanya |
| `null` | tidak ada (aksi sudah mencetak sendiri, mis. `view(...)`) |

---

## Parameter

```php
Router::get('/posts/{id}', 'PostController@show');
Router::get('/archive/{year}/{month?}', 'ArchiveController@index');   // opsional
Router::get('/users/{id:\d+}', 'UserController@show');                // dengan regex
```

Parameter dicocokkan **berdasarkan nama** (closure dan controller sama), bukan urutan:

```php
Router::get('/files/{folder}/{name}', function ($name, $folder) { ... });

public function show($id) { ... }
public function index($year, $month = null) { ... }
```

- Nilai otomatis di-URL-decode (`a%20b` → `a b`).
- Tipe skalar di-cast: `show(int $id)`. Nilai yang bukan angka → **404**.
- Parameter dengan nilai default boleh tidak ada di URL.
- Parameter bertipe `Request` diisi otomatis.
- Nama argumen yang tidak ditemukan di URI → error jelas (bukan diam-diam `null`).
- Parameter rute saat ini: `Request::route('id')` atau `Router::currentParameters()`.

---

## Group

```php
Router::group(['prefix' => 'admin', 'middleware' => ['AuthMiddleware']], function () {
    Router::get('/', 'AdminController@index');      // /admin
    Router::get('users', 'UserController@index');   // /admin/users
});
```

Group boleh bersarang; prefix dan middleware digabung.

---

## Resource

```php
Router::resource('/posts', 'PostController');
Router::resource('/posts', 'PostController', ['only' => ['index', 'show']]);
Router::resource('/posts', 'PostController', ['except' => ['destroy']]);
```

Menghasilkan `index, create, store, show, edit, update, destroy` dengan nama `posts.index`, `posts.show`, dst.

---

## Named route

```php
Router::get('/posts/{id}', 'PostController@show')->name('posts.show');

Router::name('posts.show', ['id' => 5]);   // /posts/5
Router::url('posts.show', ['id' => 5]);    // APP_URL + /posts/5
```

Di template: `@route('posts.show', ['id' => 5])`.
Parameter yang hilang → `InvalidArgumentException`.

---

## Perilaku HTTP

| Situasi | Respons |
|---------|---------|
| URL tidak ada | **404** (JSON untuk `/api/*` atau `Accept: application/json`) |
| URL ada tapi method salah | **405** + header `Allow` |
| `HEAD` | dijawab oleh rute `GET` tanpa body |
| `OPTIONS` ke `/api/*` | **204** preflight CORS (lihat [Middleware](./middleware.md)) |
| Method override | hanya pada `POST` asli: field `_method` atau header `X-HTTP-Method-Override` → `PUT`/`PATCH`/`DELETE` |
| Trailing slash | `/posts/` sama dengan `/posts` |

Halaman error dapat diganti lewat `resources/Views/errors/{status}.nixs.php` (mis. `404`, `403`, `419`, `500`).
Hentikan request dari kode mana pun dengan `abort(404)` atau `abort(403, 'Pesan')`.

---

## Middleware per rute

```php
Router::get('/admin', 'AdminController@index')->middleware('auth');
```

Nama middleware yang **tidak ditemukan menghasilkan error 500** (rute tidak dilayani), bukan dilewati. Detail di [Middleware](./middleware.md).

---

## Daftar rute

```bash
php fany route:list
```

---

## Generator rute (Fany)

```bash
php fany make:route post --RESOURCE
php fany make:route users --G --P --M=Auth
php fany make:route health --G --api
```

| Flag | Method | Aksi tipikal |
|------|--------|----------------|
| `--G` | GET | `index` |
| `--P` | POST | `store` |
| `--U` | PUT | `update` |
| `--D` | DELETE | `destroy` |
| `--RESOURCE` | CRUD lengkap | resource controller |
| `--api` | tulis ke `api.php` | |
| `--M=Name` | middleware | |

Rute yang sudah ada **tidak diduplikasi** (deteksi method + URI).
