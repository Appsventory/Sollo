# Sollo Documentation

Dokumentasi resmi **Sollo Framework** — skeleton PHP ringan dengan Nixs Template Engine dan Fany CLI.

---

## Daftar isi

| # | Topik | File |
|---|--------|------|
| 1 | [Getting Started](./getting-started.md) | Instalasi, env, server |
| 2 | [Struktur Proyek](./structure.md) | Folder & konvensi |
| 3 | [Routing](./routing.md) | Web & API routes |
| 4 | [Nixs Template Engine](./nixs.md) | Syntax, layout, helper |
| 5 | [Fany CLI](./fany-cli.md) | Semua perintah generator |
| 6 | [Database & Model](./database.md) | Migration, ORM, seeder |
| 7 | [Storage & Assets](./storage.md) | `public/storage`, `asset()` |
| 8 | [Middleware](./middleware.md) | Web, API, custom, CSRF, CORS |
| 9 | [Request, Validasi & Error](./requests.md) | Input, validator, halaman error |
| 10 | [Konfigurasi](./configuration.md) | `.env`, `config()`, checklist production |

---

## Ringkas 60 detik

```bash
git clone https://github.com/Appsventory/Sollo.git
cd Sollo
cp .env.example .env        # atau: php fany make:env --name="My App" --database=sqlite
php fany server
```

Buka [http://localhost:8000](http://localhost:8000).

---

## Persyaratan

- PHP **8.2+**
- Ekstensi `pdo` (+ `pdo_sqlite` / `pdo_mysql` / `pdo_pgsql`) dan `mbstring`
- Composer (opsional — tanpa `vendor/` autoloader bawaan dipakai)

---

## Tautan

- GitHub: [Appsventory/Sollo](https://github.com/Appsventory/Sollo)
- Lisensi: MIT
