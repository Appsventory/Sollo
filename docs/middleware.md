# Middleware

Middleware berjalan sebelum aksi route.

---

## Bawaan

| Class | File | Dipakai oleh |
|-------|------|----------------|
| `Web` | `app/Middleware/Web.php` | Group web: session, **verifikasi CSRF**, security header |
| `Api` | `app/Middleware/Api.php` | Group API: header JSON, CORS, validasi `Content-Type`, log |

Rute di `web.php` memakai group **web**; rute di `api.php` memakai group **api**.

---

## Membuat middleware

```bash
php fany make:middleware Auth
# → app/Middleware/AuthMiddleware.php
```

```php
namespace App\Middleware;

use Core\Foundation\Http\Session;

class AuthMiddleware
{
    public function handle()
    {
        if (!Session::has('user')) {
            abort(401);            // atau redirect: header('Location: /login'); exit;
        }
    }
}
```

Middleware menghentikan request dengan `abort(...)`, melempar `HttpException`, atau `exit` setelah mengirim respons.
Jika `handle()` selesai tanpa itu, request dilanjutkan.

---

## Memasang & menyelesaikan nama

```php
Router::get('/dashboard', 'DashboardController@index')->middleware('auth');
```

Router mencari `App\Middleware\{Name}` lalu `App\Middleware\{Name}Middleware`.
`'auth'`, `'Auth'`, `'AuthMiddleware'`, dan nama class lengkap semuanya valid.

> **Fail-closed:** middleware yang tidak ditemukan melempar error (HTTP 500), sehingga salah ketik
> tidak pernah membuat rute terbuka diam-diam.

### Format lanjutan

| Format | Arti |
|--------|------|
| `Name` | Panggil `handle()` |
| `Name@method` | Panggil method tertentu |
| `Name#param1&param2` | `handle(param1, param2, ...parameter rute)` |
| `Name@method:param1&param2` | `method(param1, param2, ...parameter rute)` |

---

## Middleware di controller

```php
class PostController extends Controller
{
    protected function initializeController()
    {
        $this->middleware('auth', ['only' => ['create', 'store']]);
        $this->middleware('auth', ['except' => ['index', 'show']]);
    }
}
```

Dijalankan Router tepat sebelum aksi, dengan nama yang sama seperti middleware rute.

---

## CSRF (group web)

Middleware `Web` memverifikasi token pada setiap `POST`, `PUT`, `PATCH`, `DELETE`.
Gagal → **419**. Group API tidak memakai CSRF/session.

```nixs
<form method="POST" action="/posts">
  @csrf
  @method('PUT')
</form>
```

AJAX: kirim header `X-CSRF-Token` (nilai dari `@csrf` / `CsrfToken::get()`).

Mengecualikan URL tertentu (mis. webhook pihak ketiga) — isi `$except` di `app/Middleware/Web.php`:

```php
protected array $except = ['/webhooks/*'];
```

Class: `Core\Foundation\Http\CsrfToken` (`get()`, `verify($token)`, `validate()`, `input()`, `generate()`).
Controller **tidak perlu** memanggil `CsrfToken::validate()` lagi.

---

## CORS (group api)

Satu implementasi (`Core\Foundation\Http\Cors`) dipakai middleware `Api` dan preflight `OPTIONS /api/*`.

```env
API_ALLOWED_ORIGINS=*
# atau daftar:
API_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```

Dengan daftar, header `Access-Control-Allow-Origin` hanya dikirim bila `Origin` ada di daftar.

---

## Di belakang reverse proxy

`X-Forwarded-For` / `X-Forwarded-Proto` **diabaikan** kecuali peer langsung ada di `TRUSTED_PROXIES`:

```env
TRUSTED_PROXIES=10.0.0.5,10.0.0.6    # atau * bila semua trafik pasti lewat proxy Anda
```

Memengaruhi `Request::ip()`, `Request::isSecure()` (flag `Secure` cookie session).
