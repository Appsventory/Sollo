# Getting Started

Panduan memulai Sollo dari nol.

---

## 1. Clone

```bash
git clone https://github.com/Appsventory/Sollo.git
cd Sollo
```

Composer **tidak wajib**: tanpa folder `vendor/`, `core/bootstrap.php` memakai autoloader bawaan.
Jika Anda memakai Composer (`composer install`), autoloader Composer dipakai otomatis.

Ekstensi PHP yang dibutuhkan: `pdo` + driver (`pdo_sqlite`/`pdo_mysql`/`pdo_pgsql`), `mbstring`.

## 2. Environment

```bash
php fany make:env --name="My App" --database=sqlite
```

Perintah ini membuat file `.env` dan, untuk SQLite, otomatis membuat folder + file database (`database/sollo.sqlite`).

### Opsi umum

| Opsi | Keterangan | Default |
|------|------------|---------|
| `--name=` | Nama aplikasi | nama folder |
| `--database=` | `sqlite` \| `mysql` \| `pgsql` | `mysql` |
| `--db-name=` | Path/nama database | `database/sollo.sqlite` (sqlite) |
| `--url=` | `APP_URL` | `http://localhost:8000` |

Contoh MySQL:

```bash
php fany make:env --name="My App" --database=mysql --db-name=sollo --db-user=root --db-pass=secret
```

Salin dari template tanpa CLI:

```bash
cp .env.example .env
# lalu edit manual
```

## 3. Jalankan server

```bash
php fany server
# atau
php fany serve --port=8080 --host=127.0.0.1
```

| Opsi | Default |
|------|---------|
| `--host=` | `localhost` |
| `--port=` | `8000` |

Document root: `public/`. Router: `public/router.php` (path bertitik seperti `/docs/3.x/...` tetap masuk app).

## 4. Production

```env
APP_ENV=production
APP_DEBUG=false
```

`.env.example` memakai nilai **development** (`local` + debug) agar error tampil lengkap.
Di server publik, ubah keduanya, arahkan document root ke `public/`, dan jangan upload `.env` ke repo.
Lihat [Konfigurasi](./configuration.md).

## 5. Halaman pertama

- Web: [http://localhost:8000](http://localhost:8000) → `resources/Views/home.nixs.php`
- API: [http://localhost:8000/api/health](http://localhost:8000/api/health)

## 6. Langkah berikutnya

```bash
php fany make:controller Post --resource --model
php fany make:migration create_posts_table
php fany db:migrate
php fany route:list
```

Lihat juga:

- [Nixs](./nixs.md) — template
- [Fany CLI](./fany-cli.md) — generator
- [Storage](./storage.md) — upload file tanpa symlink
- [Request & Validasi](./requests.md)

Tes regresi: `php tests/run.php` (atau `composer test`).
