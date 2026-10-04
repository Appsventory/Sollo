<p align="center"><a href="https://github.com/Appsventory/Sollo" target="_blank"><img src="./public/assets/sollo-logo.png" width="300" alt="Sollo Logo"></a></p>

<p align="center">
  <strong>A simple and flexible PHP framework to build websites and web applications</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/php-%3E%3D8.2-blue" alt="PHP">
  <img src="https://img.shields.io/badge/license-MIT-blue" alt="License">
</p>

---

## Requirements

- PHP 8.2+
- `pdo` with `pdo_sqlite` / `pdo_mysql` / `pdo_pgsql`, and `mbstring`
- Composer is optional: without `vendor/` the built-in autoloader is used

## Quick Start

```bash
git clone https://github.com/Appsventory/Sollo.git
cd Sollo

cp .env.example .env     # or: php fany make:env --name="My App" --database=sqlite
php fany server
```

Open [http://localhost:8000](http://localhost:8000).

## Fany CLI

```bash
php fany make:controller Post --resource --model
php fany make:migration create_posts_table
php fany make:view posts.index
php fany db:migrate
php fany route:list
php fany server
```

Run `php fany help` for all commands.

## Structure

```
app/                 Application (controllers, models, routes, middleware)
config/              config('app.name') files
core/                Framework core (core/bootstrap.php boots autoload + .env)
tests/               Regression tests: php tests/run.php
public/              Web root
resources/Views/     Nixs templates
  home.nixs.php      Welcome page
  layouts/app.nixs.php
  errors/
storage/             Cache, logs, compiled views
database/            SQLite file (gitignored)
```

## Assets & storage

Files under `public/` are web-accessible. Uploads go to **`public/storage/`** (no symlink, no `storage:link`).

In Nixs templates:

```nixs
{{-- public/css/app.css --}}
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
@asset('css/app.css')

{{-- public/storage/images/logo.png --}}
<img src="{{ storage('images/logo.png') }}" alt="">
@storage('images/logo.png')
{{ Storage::url('images/logo.png') }}
```

In PHP:

```php
use Core\Foundation\Storage\Storage;

Storage::put('images/logo.png', $binary);
$url = Storage::url('images/logo.png');   // /storage/images/logo.png
$path = Storage::path('images/logo.png'); // .../public/storage/images/logo.png
```

## Production

`.env.example` is a **development** template (`APP_ENV=local`, `APP_DEBUG=true`). On a live server set
`APP_ENV=production` and `APP_DEBUG=false`, and point the web server's document root at `public/`.
See [Configuration](./docs/configuration.md) for the checklist.

## Tests

```bash
php tests/run.php     # unit + CLI + HTTP regression tests (also: composer test)
```

## Documentation

Full docs in the [`docs/`](./docs/) folder:

- [Getting Started](./docs/getting-started.md)
- [Nixs Template Engine](./docs/nixs.md)
- [Fany CLI](./docs/fany-cli.md)
- [Routing](./docs/routing.md)
- [Database](./docs/database.md)
- [Storage & Assets](./docs/storage.md)
- [Middleware](./docs/middleware.md)
- [Request, Validation & Errors](./docs/requests.md)
- [Configuration](./docs/configuration.md)
- [Changelog](./CHANGELOG.md)

## License

MIT
