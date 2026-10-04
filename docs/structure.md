# Struktur Proyek

```
Sollo/
├── app/
│   ├── Controllers/      # Controller aplikasi
│   ├── Models/           # Model ORM
│   ├── Middleware/       # Web.php, Api.php, custom
│   ├── Routes/
│   │   ├── web.php       # Rute web
│   │   └── api.php       # Rute API
│   └── Database/
│       ├── migrations/   # File migrasi
│       └── Seeders/      # Seeder
├── config/               # File konfigurasi (config('app.name'))
├── core/                 # Inti framework (jangan diubah sembarangan)
│   ├── bootstrap.php     # Autoloader (tanpa Composer), helper, .env, timezone
│   ├── Console/          # Fany CLI
│   ├── Foundation/       # Router, ORM, Database, Storage, Http
│   └── Framework/Velo/Nixs/  # Template engine
├── public/               # Document root (web)
│   ├── index.php
│   ├── router.php        # Router untuk `php fany server`
│   ├── assets/           # Aset statis bawaan
│   ├── css/
│   └── storage/          # Upload publik (tanpa symlink)
├── resources/
│   └── Views/            # Template Nixs (*.nixs.php)
│       ├── home.nixs.php
│       ├── layouts/
│       └── errors/
├── storage/              # Cache, log, compiled views (bukan publik)
├── database/             # File SQLite (gitignored)
├── tests/                # Tes regresi: php tests/run.php
├── docs/                 # Dokumentasi (folder ini)
├── fany                  # Entry CLI
├── composer.json
├── .env.example
└── README.md
```

---

## Konvensi penting

| Path | Peran |
|------|--------|
| `public/` | Satu-satunya folder yang di-expose web server |
| `public/storage/` | File upload yang bisa diakses URL |
| `storage/` | Cache & log internal — **bukan** untuk URL publik |
| `resources/Views/` | View Nixs; path dot: `posts.index` → `posts/index.nixs.php` |
| `app/` | Kode aplikasi Anda |
| `core/` | Framework |

---

## Alur request

```
public/index.php
  → cek maintenance (503)
  → core/bootstrap.php          (autoload, helper env()/config()/view(), .env)
  → ErrorHandler::register()
  → RouteServiceProvider        (web.php + api.php, group web/api)
  → Router::dispatch()          (middleware → aksi → hasil; error → ErrorHandler)
```

`php fany` memakai `core/bootstrap.php` yang sama.

## Autoload

PSR-4 — satu class per file (Composer atau autoloader bawaan):

| Namespace | Folder |
|-----------|--------|
| `App\` | `app/` |
| `Core\` | `core/` |
