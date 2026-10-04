# Request, Validasi & Error

---

## Request

```php
use Core\Foundation\Http\Request;

Request::all();              // query string + body (tanpa _token / _method)
Request::input('name', 'x');
Request::only(['a', 'b']);
Request::post('email');      // body saja
Request::get('page');        // query string saja
Request::json();             // body JSON (JSON rusak => HTTP 400)
Request::file('photo');
Request::method();           // GET, POST, PUT, ... (sudah memperhitungkan _method)
Request::ip();
Request::route('id');        // parameter rute
Request::old('email');       // input sebelumnya (setelah validasi gagal)
```

**Input mentah.** Nilai dikembalikan apa adanya (`O'Brien & Co` tetap `O'Brien & Co`).
Escape dilakukan saat **output**: `{{ $x }}` di Nixs otomatis meng-escape; `{!! $x !!}` tidak.
Body `PUT`/`PATCH`/`DELETE` (urlencoded) dan JSON ikut terbaca.

---

## Validasi

```php
$data = Request::validate([
    'name'     => 'required|min:3|max:50',
    'email'    => 'required|email|unique:users,email',
    'age'      => 'required|integer|between:18,99',
    'password' => 'required|min:8|confirmed',
    'photo'    => 'file|image|max:2048',          // file: ukuran dalam KB
    'code'     => ['required', 'regex:/^[A-Z]{2,3}$/'],   // bentuk array bila parameter memuat "|"
]);
// di controller: $data = $this->validate([...]);
```

Mengembalikan **hanya field yang divalidasi**. Bila gagal, `ValidationException` dilempar dan ditangani otomatis:

| Request | Hasil |
|---------|-------|
| Form web | redirect ke halaman sebelumnya (hanya jika Referer satu host) + `session('errors')` + `old()` |
| API / `Accept: application/json` | **422** `{"error":true,"message":"...","errors":{"field":["..."]}}` |

Password dan field `*_confirmation`, `*token*`, `*secret*` tidak pernah di-flash ke `old()`.

Aturan: `required, email, min, max, between, numeric, integer, string, boolean, url, confirmed, in, not_in,
regex, alpha, alpha_num, alpha_dash, date, date_format, before, after, unique, exists, file, image, mimes, size, json`.

- `min/max/between`: **angka** bila field juga punya `numeric`/`integer`, **jumlah elemen** untuk array,
  **ukuran KB** untuk file, selain itu **jumlah karakter** (UTF-8).
- `unique:table,column,exceptId,idColumn`, `exists:table,column` — nama tabel/kolom divalidasi.
- Aturan yang tidak dikenal → exception (kesalahan developer, bukan diam-diam lolos).
- Pesan kustom: `Request::validate($rules, ['email.required' => '...', 'min' => '...'])`; placeholder `:field :min :max :size`.

---

## Error & HTTP exception

```php
abort(404);
abort(403, 'Tidak boleh');
throw new \Core\Foundation\Http\HttpException(429, 'Terlalu banyak request', ['Retry-After' => '60']);
```

| Exception | Status |
|-----------|--------|
| `HttpException` | status-nya |
| `ModelNotFoundException` (`findOrFail`) | 404 |
| `ValidationException` | 422 / redirect back |
| lainnya (`Throwable`, termasuk `TypeError`) | 500 |

Tampilan: `resources/Views/errors/{status}.nixs.php` (HTML polos) → fallback bawaan; JSON untuk `/api/*`
atau `Accept: application/json`. Error 5xx dicatat di `storage/logs/YYYY-MM-DD.log`; 4xx tidak dicatat.
PHP warning/notice diubah jadi exception; **deprecation hanya dicatat** (tidak menjatuhkan halaman).

Mode debug (stack trace + kode): `APP_DEBUG=true`. Bila `APP_DEBUG` tidak diisi, debug aktif hanya untuk
`APP_ENV=local|development`. `APP_DEBUG=false` selalu menang. Error 4xx tidak pernah menampilkan stack trace.

---

## Otorisasi di controller

```php
$this->requireAuth();          // belum login: redirect /login (JSON: 401)
$this->authorize('edit', $post);   // 403 bila can() mengembalikan false
```

`can()` **menolak secara default** — override di controller/base controller Anda:

```php
protected function can($ability, $resource = null)
{
    return $this->user()['id'] === $resource->user_id;
}
```
