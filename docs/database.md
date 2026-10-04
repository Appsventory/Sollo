# Database & Model

---

## Konfigurasi (`.env`)

```env
DB_CONNECTION=sqlite            # sqlite | mysql | pgsql
DB_DATABASE=database/sollo.sqlite
```

MySQL / PostgreSQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sollo
DB_USERNAME=root
DB_PASSWORD=
```

SQLite: path relatif di-resolve ke root project; folder + file dibuat otomatis.
Satu fungsi (`Database::createConnection()`) membuat koneksi untuk web, ORM, dan Fany CLI.

---

## Migration

```bash
php fany make:migration create_posts_table --table=posts
php fany db:migrate
php fany db:status
php fany db:rollback [--step=n]
```

```php
use Core\Foundation\Database\Migration;
use Core\Foundation\Database\Schema;

class CreatePostsTable extends Migration
{
    public function up()
    {
        Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
```

`Schema::create()` menghasilkan DDL untuk **MySQL dan SQLite**. Untuk PostgreSQL gunakan SQL langsung di migrasi
(`DB::statement('CREATE TABLE ...')`); `Schema::hasTable/hasColumn/drop/rename` bekerja di ketiganya.
Di SQLite dan PostgreSQL setiap migrasi berjalan dalam transaksi (gagal di tengah → tidak ada tabel setengah jadi).
Pengecekan: `Schema::hasTable('posts')`, `Schema::hasColumn('posts', 'title')`.

Perintah destruktif:

| Perintah | Fungsi |
|----------|--------|
| `db:fresh [--seed] [--force]` | Drop semua tabel + migrate |
| `db:reset [--seed] [--force]` | Sama (drop + migrate), keduanya bekerja di MySQL/PostgreSQL/SQLite |
| `db:backup [--type=full\|structure\|data] [--compress]` | Dump SQL ke `database/backups/` (MySQL & SQLite; PostgreSQL: `--type=data`) |

Tanpa `--force` perintah meminta konfirmasi; bila `APP_ENV=production`, `--force` **wajib**.
Semua perintah mengembalikan exit code ≠ 0 saat gagal.

---

## Query Builder

```php
use Core\Foundation\Database\DB;

DB::table('users')->where('active', 1)->orderBy('name')->limit(10)->get();
DB::table('users')->where('id', '>', 5)->count();
DB::table('users')->where('id', 1)->orWhere('id', 2)->get();
DB::table('users')->whereIn('id', [1, 2, 3])->get();
DB::table('users')->where('deleted_at', null)->first();      // IS NULL

DB::table('users')->insert(['name' => 'Ann', 'email' => 'ann@x.com']);   // atau daftar record
$id = DB::table('users')->insertGetId([...]);
DB::table('users')->where('id', 1)->update(['name' => 'Annie']);          // jumlah baris
DB::table('users')->where('id', 1)->delete();                             // jumlah baris

$page = DB::table('posts')->orderBy('id', 'desc')->paginate(15);          // 15 per halaman, ?page=2
// ['data' => [...], 'total' => 120, 'per_page' => 15, 'current_page' => 2, 'last_page' => 8]

DB::transaction(function () { ... });   // rollback otomatis bila exception
DB::select('SELECT * FROM users WHERE id = ?', [1]);
```

Aturan keamanan:

- **Nilai** selalu di-bind sebagai parameter.
- **Nama tabel/kolom, operator, arah order** divalidasi (hanya identifier, operator `= < > <= >= <> != like`, `ASC|DESC`). Input tak valid → `InvalidArgumentException`.
- `select()` hanya menerima kolom, `*`, `alias`, dan agregat (`COUNT(*) as c`). Jangan isi dari input user.
- `count()`, `paginate()`, `first()` tidak mengubah builder asal.
- `update()`/`delete()` tanpa `where()` memengaruhi **semua** baris.

---

## Model

```bash
php fany make:model Post
```

```php
namespace App\Models;

use Core\Foundation\ORM\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $fillable = ['title', 'body'];   // WAJIB untuk mass assignment
    protected $hidden = [];                    // tidak ikut toArray()/JSON
    protected $casts = ['published' => 'bool', 'meta' => 'array'];
    protected $softDeletes = false;
    protected $perPage = 15;
}
```

### Mass assignment

Hanya kolom di `$fillable` yang diisi oleh `create()`, `fill()`, `update([...])`, dan constructor.
Model **tanpa** `$fillable` menolak mass assignment (`LogicException`); atau set `protected $guarded = [];`
(izinkan semua) / `['is_admin']` (blokir kolom tertentu). Jangan melakukan `Model::create(Request::all())`
dengan `$guarded = []` pada data yang tidak tepercaya.

### Operasi

```php
Post::all();
Post::find($id);                    // null bila tidak ada
Post::findOrFail($id);              // ModelNotFoundException => halaman 404 / JSON 404
Post::create(['title' => 'Hello']);

Post::where('published', 1)->orderBy('id', 'desc')->limit(5)->get();   // array<Post>, bisa di-chain
Post::where('slug', $slug)->first();
Post::where('a', 1)->orWhere('b', 2)->get();
Post::count();
Post::whereIn('id', [1, 2])->get();
Post::paginate(10, 2);              // (perPage, halaman) — sama dengan QueryBuilder

$post->title = 'New';
$post->save();
$post->update(['title' => 'Updated']);
$post->delete();

json_encode(Post::all());           // Model mengimplementasikan JsonSerializable ($hidden dihormati)
```

Method statis yang tidak didefinisikan Model diteruskan ke query (`Post::where`, `Post::orderBy`, ...).
`Post::query()` mengembalikan builder yang menghasilkan model.

### Soft delete

`protected $softDeletes = true;` (kolom `deleted_at`). `delete()` mengisi `deleted_at`;
query otomatis menyembunyikan baris terhapus — termasuk saat memakai `orWhere`.
Sertakan yang terhapus: `Post::query()->withTrashed()->get()`.

---

## Seeder

```bash
php fany make:seeder PostSeeder
php fany db:seed
php fany db:seed --class=PostSeeder
php fany db:seed --rm=PostSeeder        # hapus dari daftar DatabaseSeeder
```

```php
class PostSeeder extends Seeder
{
    public function run()
    {
        DB::table('posts')->insert(['title' => 'Hello', 'body' => 'World']);
    }
}
```

`make:seeder` mendaftarkan class ke daftar `$this->call([...])` di `DatabaseSeeder` (apa pun namanya).
Jika file tersebut sudah Anda ubah sehingga tidak bisa diperbarui otomatis, Fany memberi peringatan
agar Anda menambahkannya manual. `db:seed --class=X` gagal (exit code ≠ 0) bila seeder tidak ditemukan.
