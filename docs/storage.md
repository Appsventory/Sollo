# Storage & Assets

Sollo **tidak memakai symlink** ala `php artisan storage:link`.

File upload disimpan langsung di:

```
public/storage/
```

Cocok untuk shared hosting yang tidak mendukung symbolic link.

---

## Layout folder

```
public/
├── index.php
├── assets/          # aset bawaan (logo, dll.)
├── css/             # CSS publik
└── storage/         # upload user (URL: /storage/...)
    └── images/
```

`storage/` di root project = cache/log/session internal — **bukan** untuk file publik:

| Folder | Isi |
|--------|-----|
| `storage/framework/views` | template Nixs terkompilasi |
| `storage/framework/sessions` | file session (tidak di `/tmp` bersama) |
| `storage/framework/down` | file maintenance mode |
| `storage/logs` | log error & request |

---

## Di template Nixs

```nixs
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
<img src="{{ storage('images/logo.png') }}" alt="">
<img src="{{ Storage::url('images/logo.png') }}" alt="">
```

| Helper | File | URL |
|--------|------|-----|
| `asset('css/app.css')` | `public/css/app.css` | `/css/app.css` |
| `storage('images/logo.png')` | `public/storage/images/logo.png` | `/storage/images/logo.png` |
| `Storage::url('images/logo.png')` | sama | `/storage/images/logo.png` |

---

## Di PHP

```php
use Core\Foundation\Storage\Storage;

Storage::put('images/logo.png', $binaryContents);

// Simpan file upload; nama file acak bila $filename tidak diberikan
$relative = Storage::putFile('images', $_FILES['photo']['tmp_name'], 'avatar-1.jpg');

Storage::url('images/logo.png');    // /storage/images/logo.png
Storage::path('images/logo.png');   // .../public/storage/images/logo.png
Storage::exists('images/logo.png');
Storage::get('images/logo.png');
Storage::delete('images/logo.png');
```

Path selalu dinormalisasi: segmen `..`, `.`, dan kosong dibuang, sehingga tidak bisa keluar dari
`public/storage` (nama file seperti `a..b.png` tetap utuh).

### Upload dari controller

```php
public function store()
{
    $path = $this->uploadFile('photo', 'avatars', ['jpg', 'jpeg', 'png']);
    // $path = "avatars/3f9c....png"  → storage($path) untuk URL
}
```

Memvalidasi ekstensi (whitelist), menyimpan dengan nama acak, dan melempar `RuntimeException` bila gagal.
Untuk aturan lebih ketat (ukuran, mime) gunakan validator: `'photo' => 'required|file|image|max:2048'`.

---

## Git

```gitignore
/public/storage/*
!/public/storage/.gitkeep
```

---

## Hosting

1. Document root **harus** mengarah ke **`public/`** — jangan ke root project, karena `.env`, `app/`,
   `core/`, dan `storage/` akan bisa diakses lewat URL.
2. Pastikan `storage/` dan `public/storage` writable.
3. Production: `APP_ENV=production`, `APP_DEBUG=false` (lihat [Konfigurasi](./configuration.md)).

Nginx (contoh):

```nginx
root /var/www/sollo/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php-fpm.sock;
                    fastcgi_param SCRIPT_FILENAME $document_root/index.php; }
location ~ /\. { deny all; }
```
