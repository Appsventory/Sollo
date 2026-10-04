# Konfigurasi

---

## `.env`

Hanya variabel yang benar-benar dibaca framework yang ada di `.env.example`:

| Variabel | Fungsi | Default |
|----------|--------|---------|
| `APP_NAME` | Nama aplikasi (judul halaman, error page) | `Sollo` |
| `APP_ENV` | `local`, `development`, `staging`, `production`, `testing` | `production` |
| `APP_DEBUG` | `true` menampilkan stack trace. Menang atas `APP_ENV` | (ikut `APP_ENV`) |
| `ERROR_DISPLAY` | Tampilan debug: `inline`, `pro`, `custom` | `inline` |
| `APP_URL` | Basis `Router::url()` | — |
| `APP_TIMEZONE` | `date_default_timezone_set()` | PHP default |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database | `mysql` |
| `SESSION_LIFETIME` | Umur session (menit) | `120` |
| `API_ALLOWED_ORIGINS` | CORS API | `*` |
| `TRUSTED_PROXIES` | IP proxy tepercaya (atau `*`) | kosong |

Aturan:

- **Variabel environment asli menang** atas `.env` (Docker, Apache `SetEnv`, systemd, CI).
- Komentar inline didukung: `APP_DEBUG=false # production`. Nilai ber-spasi/`#` diberi tanda kutip.
- `env('KEY')` mengubah `true`/`false`/`null`/`empty` menjadi nilai PHP; `Env::raw('KEY')` mengembalikan string asli.
- `env()` tersedia sejak file route dimuat (dimuat oleh `core/bootstrap.php`).

---

## `config/`

```bash
php fany make:config mail
```

```php
// config/mail.php
return [
    'default' => env('MAIL_DEFAULT', 'smtp'),
    'options' => ['timeout' => env('MAIL_TIMEOUT', 60)],
];

config('mail.default');            // 'smtp'
config('mail.options.timeout', 30);
```

File `config/app.php` bawaan memuat nama, env, debug, url, dan timezone.

---

## Checklist production

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Document root = `public/`
- [ ] `.env` tidak ada di repo / tidak bisa diakses dari web
- [ ] `storage/` dan `public/storage` writable; `storage/logs` dipantau
- [ ] `TRUSTED_PROXIES` diisi bila di belakang proxy/CDN; `API_ALLOWED_ORIGINS` dibatasi
- [ ] `php fany db:migrate --force` saat deploy (exit code ≠ 0 bila gagal)
