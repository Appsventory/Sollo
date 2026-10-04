# Fany CLI

Fany adalah command-line interface Sollo.

```bash
php fany
php fany help
php fany --version
```

Jalankan dari mana saja (Fany otomatis pindah ke root project). **Exit code** 0 = sukses, ≠ 0 = gagal,
jadi aman dipakai di CI/deploy: `php fany db:migrate && ...`.

Nama pada `make:*` divalidasi (huruf/angka/underscore; view memakai `.` atau `/`); path seperti `../Evil` ditolak.

---

## Server

```bash
php fany server
php fany serve --port=8080 --host=0.0.0.0
php fany server --help
```

---

## Environment

```bash
php fany make:env --name="My App" --database=sqlite
php fany make:env --database=mysql --db-name=sollo --db-user=root
```

Untuk SQLite, file database dibuat otomatis (`mkdir` + `touch`).

---

## Make (generator)

| Perintah | Fungsi |
|----------|--------|
| `make:controller Name [--model] [--resource]` | Controller (+ model/rute opsional) |
| `make:model Name` | Model |
| `make:view posts.index` | View `.nixs.php` |
| `make:migration create_x_table [--table=x]` | Migrasi |
| `make:seeder Name` | Seeder (+ daftar di DatabaseSeeder) |
| `make:middleware Name` | Middleware |
| `make:route name [flags]` | Append rute |
| `make:nixs name [--type=page\|layout\|form\|crud\|...]` | Template Nixs |
| `make:component Name [--props=a,b]` | Komponen Nixs |
| `make:config name` | File `config/name.php` (baca dengan `config('name.key')`) |
| `make:env` | File `.env` |

### Contoh

```bash
php fany make:controller Post --resource --model
php fany make:migration create_posts_table
php fany make:view posts.index
php fany make:nixs posts --type=crud
php fany make:component Alert --props=type,message
```

Resource controller memakai:

- URL singular: `/post`
- View plural: `posts.index`

Rute duplikat **dilewati** (tidak di-append dua kali).

---

## Database

| Perintah | Fungsi |
|----------|--------|
| `db:migrate` | Jalankan migrasi pending |
| `db:rollback [--step=n]` | Rollback |
| `db:status` | Status migrasi |
| `db:seed [--class=Name]` | Seeder (gagal → exit code ≠ 0) |
| `db:reset [--seed] [--force]` | Drop semua tabel + migrate |
| `db:fresh [--seed] [--force]` | Drop semua tabel + migrate |
| `db:backup [--type=full\|structure\|data] [--compress]` | Backup SQL (MySQL & SQLite) |

`db:reset` / `db:fresh` meminta konfirmasi; di `APP_ENV=production` wajib `--force`. Semua bekerja di
MySQL, PostgreSQL, dan SQLite.

```bash
php fany db:migrate
php fany db:seed
php fany db:status
```

---

## Maintenance & cache

```bash
php fany down          # maintenance mode (HTTP 503 + Retry-After)
php fany up            # live lagi
php fany cache:clear   # cache app + Nixs
php fany nixs:clear    # alias view cache
php fany route:list
```

---

## Route flags (make:route / make:controller)

| Flag | Arti |
|------|------|
| `--G` | GET → index |
| `--P` | POST → store |
| `--U` | PUT → update |
| `--D` | DELETE → destroy |
| `--RESOURCE` | Resource CRUD |
| `--M=Name` | Middleware |
| `--api` | Tulis ke `api.php` |

```bash
php fany make:route post --RESOURCE
php fany make:route users --G --P --M=AuthMiddleware
```
