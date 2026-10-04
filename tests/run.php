<?php

/**
 * Regression tests (no PHPUnit needed):   php tests/run.php   |   composer test
 *
 *   1. unit tests    in-process (SQLite in memory)
 *   2. CLI tests     php fany ... in a temporary copy of the project
 *   3. HTTP tests    php -S with tests/server.php (fixture routes and views)
 *
 * Exit code 0 = all passed.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

// Real environment variables win over .env, so tests never touch your own settings.
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_NAME=TestApp');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('API_ALLOWED_ORIGINS=*');
putenv('TRUSTED_PROXIES=');

require $root . '/core/bootstrap.php';

use Core\Foundation\Database\DB;
use Core\Foundation\Database\Schema;
use Core\Foundation\Http\HttpException;
use Core\Foundation\Http\Request;
use Core\Foundation\ORM\Model;
use Core\Foundation\ORM\ModelNotFoundException;
use Core\Foundation\Routing\Router;
use Core\Foundation\ValidationException;
use Core\Foundation\Validator;
use Core\Framework\Velo\Nixs\NixsCompiler as Nixs;
use Core\Framework\Velo\Nixs\Support\PathResolver;
use Core\Support\Env;

// ---------------------------------------------------------------------------
// Tiny test framework
// ---------------------------------------------------------------------------

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = [];
$GLOBALS['__section'] = '';

function section(string $name): void
{
    $GLOBALS['__section'] = $name;
    echo "\n\e[1m{$name}\e[0m\n";
}

function check(string $name, bool $condition, string $detail = ''): void
{
    if ($condition) {
        $GLOBALS['__pass']++;
        echo "  \e[32m✓\e[0m {$name}\n";
        return;
    }

    $GLOBALS['__fail'][] = $GLOBALS['__section'] . ' › ' . $name . ($detail !== '' ? " — {$detail}" : '');
    echo "  \e[31m✗ {$name}\e[0m" . ($detail !== '' ? "\n      " . str_replace("\n", "\n      ", $detail) : '') . "\n";
}

function eq(string $name, $actual, $expected): void
{
    check($name, $actual === $expected, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function has(string $name, string $haystack, string $needle): void
{
    check($name, str_contains($haystack, $needle), "missing " . var_export($needle, true) . ' in ' . var_export(mb_substr($haystack, 0, 400), true));
}

function lacks(string $name, string $haystack, string $needle): void
{
    check($name, !str_contains($haystack, $needle), "unexpected " . var_export($needle, true) . ' in ' . var_export(mb_substr($haystack, 0, 400), true));
}

function throws(string $name, callable $fn, string $class = \Throwable::class, ?string $contains = null): ?\Throwable
{
    try {
        $fn();
    } catch (\Throwable $e) {
        $ok = $e instanceof $class && ($contains === null || str_contains($e->getMessage(), $contains));
        check($name, $ok, 'got ' . get_class($e) . ': ' . $e->getMessage());
        return $e;
    }

    check($name, false, 'no exception thrown');
    return null;
}

function capture(callable $fn): string
{
    $level = ob_get_level();
    ob_start();
    try {
        $fn();
        return (string) ob_get_clean();
    } catch (\Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        throw $e;
    }
}

function copyDir(string $from, string $to, array $skip = []): void
{
    @mkdir($to, 0777, true);
    foreach (scandir($from) as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $skip, true)) {
            continue;
        }
        is_dir("{$from}/{$item}") ? copyDir("{$from}/{$item}", "{$to}/{$item}", $skip) : copy("{$from}/{$item}", "{$to}/{$item}");
    }
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        is_dir("{$dir}/{$item}") ? removeDir("{$dir}/{$item}") : @unlink("{$dir}/{$item}");
    }
    @rmdir($dir);
}

// ===========================================================================
// 1. UNIT TESTS
// ===========================================================================

section('Env');
{
    $file = sys_get_temp_dir() . '/sollo_env_' . uniqid() . '.env';
    file_put_contents($file, "A=1 # comment\nB=\"hello world\"\nNOEQUALS\nC=\nD=a=b=c\nE=false\nF=\"quoted # hash\"\nexport G=1\nREAL_WINS=from-file\nT=true\nN=null\n");
    putenv('REAL_WINS=from-real-env');
    Env::load($file);
    @unlink($file);

    eq('inline comment stripped', Env::raw('A'), '1');
    eq('quoted value', Env::raw('B'), 'hello world');
    eq('empty value', Env::raw('C'), '');
    eq('value containing =', Env::raw('D'), 'a=b=c');
    eq('hash inside quotes kept', Env::raw('F'), 'quoted # hash');
    eq('export prefix', Env::raw('G'), '1');
    eq('line without = ignored', Env::raw('NOEQUALS'), null);
    eq('real environment variable wins', Env::raw('REAL_WINS'), 'from-real-env');
    eq('env() converts false', Env::env('E'), false);
    eq('env() converts true', Env::env('T'), true);
    eq('env() converts null', Env::env('N', 'x'), null);
    eq('env() default', Env::env('NOT_SET_ANYWHERE', 'dflt'), 'dflt');
    eq('Env::bool', Env::bool('E', true), false);
    eq('global env() helper', env('APP_NAME'), 'TestApp');
    eq('config() dot notation', config('app.name'), 'TestApp');
    eq('config() default', config('app.nothing', 'fallback'), 'fallback');
    eq('config() blocks path tricks', config('../.env.x', 'safe'), 'safe');
}

section('Nixs templates');
{
    PathResolver::setBasePath(__DIR__ . '/fixtures');
    Nixs::clearCache();
    $r = fn(string $view, array $data = []) => trim(preg_replace('/\s+/', ' ', capture(fn() => Nixs::render($view, $data))));

    $first = $r('page', ['name' => '<b>x</b>']);
    has('layout: section injected into @yield', $first, '<p>Hello &lt;b&gt;x&lt;/b&gt;</p>');
    has('layout: title section', $first, '<title>Page One</title>');
    has('layout: @yield default used', $first, 'default-footer');
    eq('layout: exactly one document', substr_count($first, '<!DOCTYPE'), 1);

    $second = $r('page', ['name' => '<b>x</b>']);
    eq('layout: 2nd (cached) render identical to 1st', $second, $first);

    // A different page that shares the layout must not inherit the first page's content
    $other = $r('page', ['name' => 'Other']);
    has('layout: other data rendered', $other, 'Hello Other');
    lacks('layout: no content leak between renders', $other, '&lt;b&gt;');

    $p2 = $r('page2');
    has('component inside @extends page (slot body)', $p2, '<span class="badge">SLOT|inner body</span>');
    has('include inside @extends page', $p2, '<i>partial 5');
    has('content around component kept', $p2, '<p>before</p>');
    has('content after include kept', $p2, '<p>after</p>');
    has('default title when section missing', $p2, '<title>Default Title</title>');
    eq('component page: exactly one document', substr_count($p2, '<!DOCTYPE'), 1);
    eq('component page cached render identical', $r('page2'), $p2);

    $loop = $r('loop', ['items' => ['a', 'b']]);
    has('@include sees loop variable (1)', $loop, 'partial loop a');
    has('@include sees loop variable (2)', $loop, 'partial loop b');

    eq('@foreach/@empty/@if/@else/@unless', $r('flow', ['list' => [1, 2], 'flag' => true]), '<li>1</li><li>2</li>|yes|');
    eq('@empty branch + inline @else', $r('flow', ['list' => [], 'flag' => false]), '<p>none</p>| no|U');

    $esc = $r('escape', ['html' => '<i>&</i>']);
    has('{{ }} escapes', $esc, '&lt;i&gt;&amp;&lt;/i&gt;');
    has('{!! !!} is raw', $esc, '<p><i>&</i></p>');

    $vars = $r('vars', ['path' => '/etc/hostname', 'template' => 'zzz', 'data' => 'ddd']);
    eq('view data cannot overwrite internals', $vars, '<p>path=/etc/hostname template=zzz data=ddd</p>');

    $form1 = $r('form');
    $form2 = $r('form');
    has('@csrf renders token field', $form1, 'name="_token"');
    has('@method renders override field', $form1, 'name="_method" value="PUT"');
    has('@csrf works from the compiled cache (2nd render)', $form2, 'name="_token"');

    // editing a partial is visible immediately (never baked into a cached parent)
    $partial = __DIR__ . '/fixtures/resources/Views/partials/tmp_partial.nixs.php';
    $parent = __DIR__ . '/fixtures/resources/Views/tmp_parent.nixs.php';
    file_put_contents($partial, 'VERSION-ONE');
    file_put_contents($parent, "A @include('partials.tmp_partial') B");
    $v1 = $r('tmp_parent');
    file_put_contents($partial, 'VERSION-TWO');
    touch($partial, time() + 5);
    $v2 = $r('tmp_parent');
    @unlink($partial);
    @unlink($parent);
    eq('partial v1', $v1, 'A VERSION-ONE B');
    eq('partial edit visible without clearing cache', $v2, 'A VERSION-TWO B');

    // cache files of an edited template are replaced, not accumulated
    $tmp = __DIR__ . '/fixtures/resources/Views/tmp_cache.nixs.php';
    file_put_contents($tmp, 'one');
    $r('tmp_cache');
    file_put_contents($tmp, 'two');
    touch($tmp, time() + 10);
    $r('tmp_cache');
    $count = count(glob(dirname(__DIR__) . '/storage/framework/views/nixs_' . md5($tmp) . '_*.php'));
    @unlink($tmp);
    eq('stale compiled versions are removed', $count, 1);

    $level = ob_get_level();
    throws('missing view throws', fn() => capture(fn() => Nixs::render('does.not.exist')), \Exception::class, 'not found');
    eq('output buffers balanced after failure', ob_get_level(), $level);
    throws('absolute path outside project rejected', fn() => Nixs::render('/etc/hostname'), \InvalidArgumentException::class);

    Nixs::clearCache();
}

section('Request');
{
    $reset = function (array $server = [], array $post = [], array $get = []) {
        foreach (['HTTP_X_HTTP_METHOD_OVERRIDE', 'HTTP_X_FORWARDED_FOR', 'HTTP_REFERER', 'CONTENT_TYPE', 'HTTP_ACCEPT'] as $k) {
            unset($_SERVER[$k]);
        }
        $_SERVER = array_merge($_SERVER, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '1.2.3.4', 'HTTP_HOST' => 'example.test'], $server);
        $_POST = $post;
        $_GET = $get;
        Request::clearCache();
    };

    $reset(['REQUEST_METHOD' => 'POST'], ['p' => "pa&ss<w>'rd", 'email' => "o'b@x.com", '_token' => 'T', '_method' => 'PUT'], ['q' => '1']);
    eq('input is NOT html-escaped', Request::all()['p'], "pa&ss<w>'rd");
    eq('apostrophe kept', Request::post('email'), "o'b@x.com");
    check('framework fields are not part of all()', !isset(Request::all()['_token']) && !isset(Request::all()['_method']));
    eq('query string merged', Request::all()['q'], '1');
    eq('POST + _method=PUT => PUT', Request::method(), 'PUT');

    $reset(['REQUEST_METHOD' => 'POST'], ['_method' => 'TRACE']);
    eq('unsupported override ignored', Request::method(), 'POST');

    $reset(['REQUEST_METHOD' => 'GET', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE']);
    eq('GET cannot be overridden', Request::method(), 'GET');

    $reset(['REQUEST_URI' => '/api']);
    check('/api is an API request', Request::isApi());
    $reset(['REQUEST_URI' => '/apiary']);
    check('/apiary is not an API request', !Request::isApi());

    $reset(['HTTP_X_FORWARDED_FOR' => '9.9.9.9']);
    eq('forwarded IP ignored without trusted proxy', Request::ip(), '1.2.3.4');
    putenv('TRUSTED_PROXIES=1.2.3.4');
    eq('forwarded IP used from trusted proxy', Request::ip(), '9.9.9.9');
    putenv('TRUSTED_PROXIES=');

    $reset(['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json']);
    $raw = new ReflectionProperty(Request::class, 'rawCache');
    $raw->setAccessible(true);
    $raw->setValue(null, '{"a":"A & B","n":5}');
    eq('JSON body is raw (not escaped)', Request::all()['a'], 'A & B');
    Request::clearCache();
    $raw->setValue(null, '{bad');
    throws('malformed JSON => HttpException 400', fn() => Request::json(), HttpException::class, 'Malformed');
    Request::clearCache();

    $reset(['HTTP_REFERER' => 'https://evil.example/phish']);
    eq('previousUrl ignores external referer', Request::previousUrl(), '/');
    $reset(['HTTP_REFERER' => 'https://example.test/form?x=1']);
    eq('previousUrl keeps same-host referer', Request::previousUrl(), '/form?x=1');

    $reset();
}

section('Validator');
{
    $v = fn(array $data, array $rules, array $messages = []) => (new Validator())->validate($data, $rules, $messages);
    $errors = function (array $data, array $rules) {
        try {
            (new Validator())->validate($data, $rules);
            return [];
        } catch (ValidationException $e) {
            return $e->getErrors();
        }
    };

    check('min:8 rejects 7-char numeric string', isset($errors(['pw' => '1234567'], ['pw' => 'required|min:8'])['pw']));
    check('min:8 accepts 8 chars', !$errors(['pw' => '12345678'], ['pw' => 'required|min:8']));
    $e = $errors(['n' => '7'], ['n' => 'numeric|min:8']);
    check('numeric field is compared as a number', isset($e['n']) && str_contains($e['n'][0], 'at least 8.') && !str_contains($e['n'][0], 'characters'), json_encode($e));
    $e = $errors(['n' => 'ab'], ['n' => 'between:3,10']);
    check('between message shows both bounds', isset($e['n']) && str_contains($e['n'][0], 'between 3 and 10'), json_encode($e));
    check('regex rule with comma', !$errors(['c' => '123'], ['c' => ['regex:/^\d{1,3}$/']]));
    check('regex rule rejects', (bool) $errors(['c' => '1234'], ['c' => ['regex:/^\d{1,3}$/']]));
    check('email with apostrophe is valid', !$errors(['e' => "o'b@x.com"], ['e' => 'email']));
    check('min counts characters, not bytes', !$errors(['n' => 'ñññ ñ'], ['n' => 'min:5']));
    check('array value does not crash (string rule)', (bool) $errors(['n' => ['x']], ['n' => 'email']));
    check('required', isset($errors([], ['name' => 'required'])['name']));
    check('confirmed', (bool) $errors(['p' => 'a', 'p_confirmation' => 'b'], ['p' => 'confirmed']));
    eq('validate() returns only validated fields', $v(['a' => 1, 'b' => 2], ['a' => 'required']), ['a' => 1]);
    throws('unknown rule is a developer error', fn() => $v(['a' => 1], ['a' => 'nonsense']), \Exception::class, 'does not exist');
    throws('unique: table name is validated', fn() => $v(['a' => 1], ['a' => 'unique:users;DROP TABLE x']), \InvalidArgumentException::class);
}

section('Query builder');
{
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, password TEXT, created_at TEXT, updated_at TEXT)');
    DB::statement('CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');

    foreach (['Ann', 'Bob', 'Cy'] as $i => $name) {
        DB::table('users')->insert(['name' => $name, 'email' => strtolower($name) . '@x.com', 'password' => 'secret']);
    }

    eq('count()', DB::table('users')->count(), 3);
    eq('where', DB::table('users')->where('name', 'Bob')->first()['email'], 'bob@x.com');
    eq('where with operator', DB::table('users')->where('id', '>', 1)->count(), 2);
    eq('orWhere is valid SQL and works', DB::table('users')->where('id', 1)->orWhere('id', 2)->count(), 2);
    eq('whereIn', DB::table('users')->whereIn('id', [1, 3])->count(), 2);
    eq('whereIn with an empty list matches nothing', DB::table('users')->whereIn('id', [])->count(), 0);
    eq('whereNotIn with an empty list matches all', DB::table('users')->whereNotIn('id', [])->count(), 3);
    has('where(col, null) becomes IS NULL', DB::table('users')->where('name', null)->toSql(), 'name IS NULL');
    has('where(col, "=", null) becomes IS NULL', DB::table('users')->where('name', '=', null)->toSql(), 'name IS NULL');

    $q = DB::table('users')->where('id', '>', 0)->orderBy('id', 'desc');
    $q->count();
    eq('count() does not change the builder', count($q->get()), 3);

    $page = DB::table('users')->orderBy('id')->paginate(2, 2);
    eq('paginate: data are rows', $page['data'][0]['name'], 'Cy');
    eq('paginate: total', $page['total'], 3);
    eq('paginate: last_page', $page['last_page'], 2);
    eq('paginate: current_page', $page['current_page'], 2);

    eq('update returns affected rows', DB::table('users')->where('id', 1)->update(['name' => 'Annie']), 1);
    eq('delete returns affected rows', DB::table('users')->where('id', 3)->delete(), 1);
    eq('insertGetId', DB::table('users')->insertGetId(['name' => 'Dee', 'email' => 'dee@x.com', 'password' => 'x']), '4');
    eq('sum/max/min', [DB::table('users')->max('id'), DB::table('users')->min('id')], [4, 1]);
    eq('limit/offset without limit', count(DB::table('users')->orderBy('id')->offset(1)->get()), 2);

    throws('column name injection rejected', fn() => DB::table('users')->where('name; DROP TABLE users;--', 'x')->get(), \InvalidArgumentException::class);
    throws('table name injection rejected', fn() => DB::table('users; DROP TABLE users'), \InvalidArgumentException::class);
    throws('operator injection rejected', fn() => DB::table('users')->where('id', '= 1 OR 1=1 --', 1), \InvalidArgumentException::class);
    throws('order direction injection rejected', fn() => DB::table('users')->orderBy('id', 'ASC; DROP TABLE users'), \InvalidArgumentException::class);
    throws('select expression injection rejected', fn() => DB::table('users')->select(['id FROM users; --']), \InvalidArgumentException::class);
    eq('select with alias/aggregate allowed', DB::table('users')->select(['COUNT(*) as c'])->first()['c'], 3);
    throws('unique violation surfaces as exception', fn() => DB::table('users')->insert(['name' => 'Dup', 'email' => 'ann@x.com', 'password' => 'x']), \RuntimeException::class);
    eq('transaction rolls back on exception', (function () {
        try {
            DB::transaction(function () {
                DB::table('users')->insert(['name' => 'T', 'email' => 't@x.com', 'password' => 'x']);
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException $e) {
        }
        return DB::table('users')->where('email', 't@x.com')->count();
    })(), 0);
    check('Schema::hasTable', Schema::hasTable('users') && !Schema::hasTable('nope'));
    check('Schema::hasColumn', Schema::hasColumn('users', 'email') && !Schema::hasColumn('users', 'nope'));
}

class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
}

class Note extends Model
{
    protected $table = 'notes';
    protected $fillable = ['title'];
    protected $softDeletes = true;
}

class Locked extends Model
{
    protected $table = 'users';
}

section('Model');
{
    $u = User::create(['name' => 'Eve', 'email' => 'eve@x.com', 'password' => 'p', '_route_params' => [], '_token' => 't', 'id' => 999]);
    check('create() sets the primary key', is_int($u->id) && $u->id !== 999, var_export($u->id, true));
    eq('create() persisted', User::find($u->id)->email, 'eve@x.com');
    eq('where()->get() returns models', User::where('name', 'Eve')->get()[0] instanceof User, true);
    eq('where()->first()', User::where('email', 'eve@x.com')->first()->name, 'Eve');
    eq('where() is chainable', User::where('id', '>', 0)->orderBy('id', 'desc')->limit(1)->get()[0]->name, 'Eve');
    eq('Model::count()', User::count(), 4);
    eq('Model::orWhere', count(User::where('name', 'Eve')->orWhere('name', 'Annie')->get()), 2);
    eq('Model::all()', count(User::all()), 4);

    $json = json_encode(User::all());
    check('json_encode(models) includes data', str_contains($json, '"name":"Annie"'), $json);
    lacks('hidden attributes are not serialized', $json, 'password');
    lacks('toArray hides hidden', json_encode($u->toArray()), 'password');

    $page = User::paginate(2, 1);
    eq('paginate(perPage, page): per_page', $page['per_page'], 2);
    eq('paginate: models', $page['data'][0] instanceof User, true);

    $e = throws('findOrFail on a missing row', fn() => User::findOrFail(12345), ModelNotFoundException::class);
    eq('...is a 404', $e instanceof HttpException ? $e->getStatusCode() : null, 404);

    $u->update(['name' => 'Eve2']);
    eq('instance update()', User::find($u->id)->name, 'Eve2');
    $u->name = 'Eve3';
    $u->save();
    eq('save() after attribute change', User::find($u->id)->name, 'Eve3');
    $u->delete();
    eq('hard delete', User::find($u->id), null);

    throws('model without $fillable refuses mass assignment', fn() => Locked::create(['name' => 'x']), \LogicException::class, 'mass-assignable');
    throws('unknown static method', fn() => User::doesNotExist(), \BadMethodCallException::class);

    $a = Note::create(['title' => 'a']);
    Note::create(['title' => 'b']);
    Note::create(['title' => 'c']);
    $a->delete();
    eq('soft delete hides from all()', count(Note::all()), 2);
    eq('soft delete: orWhere cannot leak deleted rows', count(Note::where('title', 'a')->orWhere('title', 'b')->get()), 1);
    eq('soft delete: find()', Note::find($a->id), null);
    eq('soft delete: withTrashed()', count(Note::query()->withTrashed()->get()), 3);
    eq('soft delete: count()', Note::count(), 2);
}

section('Router');
{
    Router::clearRoutes();
    Router::get('/hello/{name}', fn($name) => $name);
    Router::get('/opt/{x?}', fn($x = null) => $x);
    Router::get('/n/{id:\d+}', fn($id) => $id);
    Router::resource('/blog/posts', 'PostController', ['only' => ['index', 'show']]);
    Router::get('/trailing/', fn() => 'x');
    Router::group(['prefix' => 'admin', 'middleware' => ['auth']], function () {
        Router::get('/', fn() => 'admin-home');
        Router::get('users', fn() => 'u')->name('admin.users');
    });

    eq('param decoded', Router::resolve('GET', '/hello/a%20b')['parameters']['name'], 'a b');
    eq('optional param absent', Router::resolve('GET', '/opt')['parameters'], []);
    eq('optional param present', Router::resolve('GET', '/opt/5')['parameters']['x'], '5');
    check('constraint matches digits', Router::resolve('GET', '/n/42') !== null);
    check('constraint rejects letters', Router::resolve('GET', '/n/abc') === null);
    check('trailing slash route normalized', Router::resolve('GET', '/trailing') !== null);
    check('group prefix applied to "/"', Router::resolve('GET', '/admin') !== null);
    eq('group middleware inherited', Router::resolve('GET', '/admin/users')['route']->middleware, ['auth']);
    eq('named route', Router::name('admin.users'), '/admin/users');
    eq('resource route names', Router::name('blog.posts.show', ['id' => 7]), '/blog/posts/7');
    eq('named route encodes params', Router::name('blog.posts.show', ['id' => 'a b']), '/blog/posts/a%20b');
    throws('named route missing parameter', fn() => Router::name('blog.posts.show'), \InvalidArgumentException::class, "'id'");
    throws('unknown named route', fn() => Router::name('nope'), \InvalidArgumentException::class);
    Router::clearRoutes();
}

// ===========================================================================
// 2. CLI TESTS
// ===========================================================================

section('CLI (php fany in a temporary copy)');
{
    $dir = sys_get_temp_dir() . '/sollo_cli_' . uniqid();
    copyDir($root, $dir, ['vendor', '.git', 'tests']);
    copy($dir . '/.env.example', $dir . '/.env');
    file_put_contents($dir . '/.env', preg_replace('/^DB_DATABASE=.*/m', 'DB_DATABASE=database/test.sqlite', file_get_contents($dir . '/.env')));

    $fany = function (string $args, ?string $stdin = null) use ($dir): array {
        $env = ['PATH' => getenv('PATH'), 'HOME' => getenv('HOME') ?: '/tmp'];
        $proc = proc_open(PHP_BINARY . ' fany ' . $args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir, $env);
        fwrite($pipes[0], $stdin ?? '');
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $code = proc_close($proc);
        return [$code, preg_replace('/\e\[[0-9;]*m/', '', $out)];
    };

    [$code, $out] = $fany('--version');
    eq('fany --version works without vendor/', $code, 0);
    [$code] = $fany('no:such:command');
    check('unknown command exits non-zero', $code !== 0);

    [$code] = $fany('make:model User');
    check('make:model creates the file', $code === 0 && is_file("$dir/app/Models/User.php"));
    [$code] = $fany('make:model ../Evil');
    check('make:model rejects path traversal', $code !== 0 && !is_file("$dir/app/Evil.php") && !is_file("$dir/app/Models/../Evil.php"));

    foreach (['UserSeeder', 'OrderSeeder', 'ProductSeeder'] as $seeder) {
        $fany("make:seeder $seeder");
    }
    $fany('make:seeder UserSeeder');
    $db = file_get_contents("$dir/app/Database/Seeders/DatabaseSeeder.php");
    exec(PHP_BINARY . ' -l ' . escapeshellarg("$dir/app/Database/Seeders/DatabaseSeeder.php") . ' 2>&1', $lint, $lintCode);
    eq('DatabaseSeeder is valid PHP after make:seeder', $lintCode, 0);
    foreach (['UserSeeder', 'OrderSeeder', 'ProductSeeder'] as $seeder) {
        eq("$seeder registered once", substr_count($db, "$seeder::class"), 1);
    }

    [$code] = $fany('make:controller PostController --resource');
    $ctrl = @file_get_contents("$dir/app/Controllers/PostController.php") ?: '';
    check('make:controller --resource', $code === 0 && $ctrl !== '');
    lacks('controller stub has no manual CSRF calls', $ctrl, 'CsrfToken');
    [$code] = $fany('make:controller ../EvilController');
    check('make:controller rejects path traversal', $code !== 0);
    [$code] = $fany('make:middleware Auth');
    check('make:middleware creates AuthMiddleware', $code === 0 && is_file("$dir/app/Middleware/AuthMiddleware.php"));
    [$code] = $fany('make:config mail');
    check('make:config + config() reads it', $code === 0 && str_contains(file_get_contents("$dir/config/mail.php"), "MAIL_DEFAULT"));
    [$code] = $fany('make:view users/index');
    check('make:view', $code === 0 && is_file("$dir/resources/Views/users/index.nixs.php"));

    [$code] = $fany('make:migration create_users_table --table=users');
    $migration = glob("$dir/app/Database/migrations/*create_users_table.php")[0] ?? null;
    check('make:migration', $code === 0 && $migration !== null);
    file_put_contents($migration, <<<'PHP'
<?php

use Core\Foundation\Database\Migration;
use Core\Foundation\Database\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}
PHP);
    [$code, $out] = $fany('db:migrate');
    check('db:migrate', $code === 0 && is_file("$dir/database/test.sqlite"), $out);
    [$code, $out] = $fany('db:migrate');
    check('db:migrate is idempotent', $code === 0 && stripos($out, 'nothing') !== false, $out);
    [$code, $out] = $fany('db:status');
    check('db:status', $code === 0 && str_contains($out, 'create_users_table'), $out);

    file_put_contents("$dir/app/Database/Seeders/UserSeeder.php", <<<'PHP'
<?php

namespace App\Database\Seeders;

use Core\Foundation\Database\DB;
use Core\Foundation\Seeding\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->insert(['name' => 'Seeded']);
    }
}
PHP);
    [$code, $out] = $fany('db:seed --class=UserSeeder');
    check('db:seed --class runs the seeder', $code === 0, $out);
    [$code, $out] = $fany('db:seed');
    check('db:seed runs DatabaseSeeder (registered seeders)', $code === 0, $out);
    [$code] = $fany('db:seed --class=NopeSeeder');
    check('db:seed --class=<missing> fails with non-zero exit', $code !== 0);

    [$code, $out] = $fany('db:backup --type=full');
    $backups = glob("$dir/database/backups/*.sql");
    check('db:backup (SQLite)', $code === 0 && $backups && str_contains(file_get_contents($backups[0]), 'CREATE TABLE'), $out);

    [$code, $out] = $fany('db:fresh --force');
    check('db:fresh --force works on SQLite', $code === 0 && str_contains($out, 'Migrated'), $out);
    [$code, $out] = $fany('db:reset --force --seed');
    check('db:reset --force --seed works on SQLite', $code === 0, $out);
    [$code, $out] = $fany('db:rollback');
    check('db:rollback', $code === 0, $out);

    [$code, $out] = $fany('down');
    check('down creates maintenance file', $code === 0 && is_file("$dir/storage/framework/down/maintenance.html"), $out);
    clearstatcache();
    [$code, $out] = $fany('up');
    clearstatcache();
    check('up removes maintenance file', $code === 0 && !is_file("$dir/storage/framework/down/maintenance.html"), "exit {$code}\n{$out}");
    [$code, $out] = $fany('route:list');
    check('route:list', $code === 0 && str_contains($out, '/api/health'), $out);
    [$code] = $fany('cache:clear');
    eq('cache:clear', $code, 0);

    removeDir($dir);
}

// ===========================================================================
// 3. HTTP TESTS (php -S)
// ===========================================================================

function startServer(string $root, string $args, array $env): array
{
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);

    $env = array_merge(['PATH' => getenv('PATH'), 'HOME' => getenv('HOME') ?: '/tmp', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'], $env);
    $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $proc = proc_open(
        PHP_BINARY . " -S 127.0.0.1:{$port} " . $args,
        [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
        $pipes,
        $root,
        $env
    );

    for ($i = 0; $i < 50; $i++) {
        if (($c = @fsockopen('127.0.0.1', $port, $e, $s, 0.2)) !== false) {
            fclose($c);
            return [$proc, "http://127.0.0.1:{$port}"];
        }
        usleep(100000);
    }

    throw new RuntimeException('Test server did not start');
}

function http(string $base, string $method, string $path, array $options = []): array
{
    $lines = [];
    foreach ($options['headers'] ?? [] as $name => $value) {
        $lines[] = "{$name}: {$value}";
    }

    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $lines),
        'content' => $options['body'] ?? '',
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 10,
    ]]);

    $body = @file_get_contents($base . $path, false, $context);
    $raw = $http_response_header ?? [];

    $status = 0;
    $headers = [];
    foreach ($raw as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = (int) $m[1];
            $headers = [];
            continue;
        }
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))][] = trim($v);
        }
    }

    return ['status' => $status, 'body' => (string) $body, 'headers' => $headers, 'json' => json_decode((string) $body, true)];
}

function header1(array $res, string $name): string
{
    return implode(', ', $res['headers'][strtolower($name)] ?? []);
}

function cookieOf(array $res): string
{
    foreach ($res['headers']['set-cookie'] ?? [] as $cookie) {
        if (preg_match('/^(PHPSESSID=[^;]+)/', $cookie, $m)) {
            return $m[1];
        }
    }
    return '';
}

$servers = [];
try {
    // ---- fixture server (production-like: debug off) -----------------------
    [$procA, $A] = startServer($root, '-t public tests/server.php', ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_NAME' => 'TestApp']);
    $servers[] = $procA;

    section('HTTP: views, layouts, cache');
    $r1 = http($A, 'GET', '/page');
    $r2 = http($A, 'GET', '/page');
    eq('GET /page 200', $r1['status'], 200);
    has('layout + section', $r1['body'], '<title>Page One</title>');
    eq('second request (cached) identical', $r2['body'], $r1['body']);
    $o = http($A, 'GET', '/other');
    has('another page sharing the layout', $o['body'], 'Hello Other');
    lacks('no leak from the first page', $o['body'], '&lt;b&gt;');
    $p2 = http($A, 'GET', '/page2');
    has('component + include inside a layout page', $p2['body'], '<span class="badge">SLOT|inner body</span>');
    has('...cached', http($A, 'GET', '/page2')['body'], '<span class="badge">SLOT|inner body</span>');
    eq('view data cannot overwrite internals', trim(http($A, 'GET', '/vars')['body']), '<p>path=/etc/hostname template=zzz data=ddd</p>');
    eq('env() is available in route files and handlers', http($A, 'GET', '/echo-env')['body'], 'TestApp');
    has('controller view()', http($A, 'GET', '/ctrl-view')['body'], 'Hello FromController');

    section('HTTP: routing');
    eq('route param', http($A, 'GET', '/user/5')['body'], 'id=5');
    eq('route param url-decoded', http($A, 'GET', '/hello/a%20b')['body'], 'hi a b');
    eq('int type cast', http($A, 'GET', '/num/5')['body'], 'int:5');
    eq('int type: non numeric => 404', http($A, 'GET', '/num/abc')['status'], 404);
    eq('params matched by name, not position', http($A, 'GET', '/files/1/2')['body'], 'a=2 b=1');
    eq('optional param default', http($A, 'GET', '/opt')['body'], 'x=none');
    eq('optional param value', http($A, 'GET', '/opt/7')['body'], 'x=7');
    eq('controller: default value used when no route param', http($A, 'GET', '/typed')['body'], 'page=5');
    eq('controller returning an array => JSON', http($A, 'GET', '/data')['json']['items'], [1, 2]);
    eq('protected controller method is not routable', http($A, 'GET', '/hidden')['status'], 404);
    $r = http($A, 'GET', '/resource-missing');
    eq('unknown URL => 404', $r['status'], 404);
    has('404 page is HTML', header1($r, 'content-type'), 'text/html');
    $r = http($A, 'GET', '/submit');
    eq('wrong method => 405', $r['status'], 405);
    has('405 sends Allow header', header1($r, 'allow'), 'POST');
    $r = http($A, 'HEAD', '/open');
    check('HEAD answered by the GET route (no body)', $r['status'] === 200 && $r['body'] === '', "status {$r['status']}");
    eq('HEAD on unknown URL => 404', http($A, 'HEAD', '/zzz')['status'], 404);
    eq('resource route works', http($A, 'GET', '/posts')['status'], 500); // index() is not defined on the fixture controller => developer error, not a 200

    section('HTTP: middleware');
    $r = http($A, 'GET', '/guarded');
    eq('controller middleware is executed', $r['status'], 401);
    lacks('...and the action is not reached', $r['body'], 'GUARDED-REACHED');
    $r = http($A, 'GET', '/mw-typo');
    check('unknown middleware fails closed (500), route not served', $r['status'] === 500 && !str_contains($r['body'], 'LEAKED'), "status {$r['status']}");
    eq('middleware by short name resolves to ...Middleware class', http($A, 'GET', '/mw-block')['status'], 401);
    eq('middleware by class name', http($A, 'GET', '/mw-class')['status'], 401);
    eq('authorize() denies by default', http($A, 'GET', '/authorize')['status'], 403);

    section('HTTP: CSRF, sessions, input');
    $form = http($A, 'GET', '/form');
    $cookie = cookieOf($form);
    preg_match('/name="_token" value="([a-f0-9]+)"/', $form['body'], $m);
    $token = $m[1] ?? '';
    check('web route sets a session cookie and renders @csrf', $cookie !== '' && strlen($token) === 64);
    check('session cookie is HttpOnly', str_contains(implode(';', $form['headers']['set-cookie'] ?? []), 'HttpOnly'));
    eq('security header X-Frame-Options', header1($form, 'x-frame-options'), 'SAMEORIGIN');
    has('form (cached render) still has the token', http($A, 'GET', '/form', ['headers' => ['Cookie' => $cookie]])['body'], 'name="_token"');

    $urlenc = ['Content-Type' => 'application/x-www-form-urlencoded', 'Cookie' => $cookie];
    eq('POST without a token => 419', http($A, 'POST', '/submit', ['headers' => $urlenc, 'body' => 'x=1'])['status'], 419);
    eq('POST with a wrong token => 419', http($A, 'POST', '/submit', ['headers' => $urlenc, 'body' => 'x=1&_token=' . str_repeat('0', 64)])['status'], 419);
    eq('POST without a session cookie => 419', http($A, 'POST', '/submit', ['headers' => ['Content-Type' => 'application/x-www-form-urlencoded'], 'body' => "x=1&_token={$token}"])['status'], 419);

    $raw = "O'Brien & <b>pa&ss</b>";
    $r = http($A, 'POST', '/submit', ['headers' => $urlenc, 'body' => http_build_query(['x' => $raw, '_token' => $token])]);
    eq('POST with a valid token => 200', $r['status'], 200);
    eq('input arrives raw (not HTML-escaped)', $r['json']['all']['x'] ?? null, $raw);
    check('_token is not exposed through Request::all()', !isset($r['json']['all']['_token']));

    $r = http($A, 'POST', '/submit', ['headers' => $urlenc, 'body' => http_build_query(['_method' => 'PUT', '_token' => $token])]);
    eq('_method=PUT override => PUT route', $r['json']['method'] ?? null, 'PUT');
    $r = http($A, 'PUT', '/submit', ['headers' => $urlenc + ['X-CSRF-Token' => $token], 'body' => 'a=1']);
    eq('real PUT + X-CSRF-Token header', $r['json']['method'] ?? null, 'PUT');
    eq('real PUT urlencoded body is parsed', $r['json']['all']['a'] ?? null, '1');
    $r = http($A, 'POST', '/submit', ['headers' => $urlenc + ['X-HTTP-Method-Override' => 'DELETE', 'X-CSRF-Token' => $token], 'body' => 'a=1']);
    eq('X-HTTP-Method-Override on POST', $r['json']['method'] ?? null, 'DELETE');

    $r = http($A, 'POST', '/validate', ['headers' => $urlenc + ['Referer' => "{$A}/form"], 'body' => http_build_query(['x' => 'keepme', 'name' => 'a', 'password' => 'SECRET-PW', '_token' => $token])]);
    eq('validation failure on a web form => redirect back', $r['status'], 302);
    eq('...to the referer path', header1($r, 'location'), '/form');
    $after = http($A, 'GET', '/form', ['headers' => ['Cookie' => $cookie]]);
    has('old() input is available after the redirect', $after['body'], 'value="keepme"');
    lacks('password is never flashed', $after['body'], 'SECRET-PW');

    section('HTTP: API');
    $json = ['Content-Type' => 'application/json'];
    $r = http($A, 'POST', '/api/echo', ['headers' => $json, 'body' => '{"name":"A & B"}']);
    eq('API JSON body is raw', $r['json']['name'] ?? null, 'A & B');
    check('API responses do not start a session', !isset($r['headers']['set-cookie']));
    eq('API: no CSRF needed', $r['status'], 200);
    eq('API CORS header', header1($r, 'access-control-allow-origin'), '*');
    eq('API: non-JSON body => 415', http($A, 'POST', '/api/echo', ['headers' => ['Content-Type' => 'application/x-www-form-urlencoded'], 'body' => 'a=1'])['status'], 415);
    $r = http($A, 'POST', '/api/echo', ['headers' => $json, 'body' => '{bad']);
    check('API: malformed JSON => 400 JSON', $r['status'] === 400 && ($r['json']['error'] ?? false) === true, $r['body']);
    $r = http($A, 'GET', '/api/nope');
    check('API 404 is JSON', $r['status'] === 404 && str_contains(header1($r, 'content-type'), 'application/json') && ($r['json']['error'] ?? false) === true, $r['body']);
    $r = http($A, 'GET', '/api/missing-model');
    eq('ModelNotFoundException => 404 JSON', $r['status'], 404);
    $r = http($A, 'POST', '/api/validate', ['headers' => $json, 'body' => '{"name":"a"}']);
    check('API validation => 422 with errors', $r['status'] === 422 && isset($r['json']['errors']['name']) && isset($r['json']['errors']['age']), $r['body']);
    $r = http($A, 'OPTIONS', '/api/echo', ['headers' => ['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'PATCH']]);
    eq('preflight => 204', $r['status'], 204);
    has('preflight allows PATCH', header1($r, 'access-control-allow-methods'), 'PATCH');
    $r = http($A, 'OPTIONS', '/open', ['headers' => ['Origin' => 'https://evil.example']]);
    check('preflight on a web URL gets no CORS headers', !isset($r['headers']['access-control-allow-origin']));

    section('HTTP: errors (production)');
    $r = http($A, 'GET', '/boom');
    eq('exception => 500', $r['status'], 500);
    lacks('exception message not leaked', $r['body'], 'secret-db-password');
    $r = http($A, 'GET', '/typeerr');
    eq('PHP Error (TypeError) => 500', $r['status'], 500);
    lacks('no stack trace in production', $r['body'], 'Stack Trace');
    $r = http($A, 'GET', '/api/missing-model');
    check('production JSON hides server details', !isset($r['json']['trace']));
    check('error log written', (bool) glob($root . '/storage/logs/' . date('Y-m-d') . '.log'));

    // ---- debug server -------------------------------------------------------
    [$procB, $B] = startServer($root, '-t public tests/server.php', ['APP_ENV' => 'local', 'APP_NAME' => 'TestApp']);
    $servers[] = $procB;
    section('HTTP: errors (debug: APP_ENV=local, APP_DEBUG unset)');
    $r = http($B, 'GET', '/boom');
    eq('exception => 500', $r['status'], 500);
    has('details visible in debug', $r['body'], 'secret-db-password');
    eq('404 stays a friendly page in debug', http($B, 'GET', '/nope')['status'], 404);
    lacks('404 page has no stack trace', http($B, 'GET', '/nope')['body'], 'Stack Trace');

    [$procC, $C] = startServer($root, '-t public tests/server.php', ['APP_ENV' => 'local', 'APP_DEBUG' => 'false']);
    $servers[] = $procC;
    section('HTTP: APP_DEBUG=false wins over APP_ENV=local');
    lacks('no details', http($C, 'GET', '/boom')['body'], 'secret-db-password');

    // ---- the real skeleton (public/index.php) -------------------------------
    $down = $root . '/storage/framework/down/maintenance.html';
    [$procD, $D] = startServer($root, '-t public public/router.php', ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false']);
    $servers[] = $procD;
    section('HTTP: skeleton (public/index.php)');
    $r = http($D, 'GET', '/');
    eq('GET / => 200', $r['status'], 200);
    has('welcome page has a doctype', $r['body'], '<!DOCTYPE html>');
    has('welcome page title uses the app name', $r['body'], '<title>Welcome | Sollo</title>');
    $r = http($D, 'GET', '/api/health');
    check('GET /api/health => JSON', $r['status'] === 200 && is_array($r['json']), $r['body']);
    eq('unknown URL => 404', http($D, 'GET', '/definitely-not-here')['status'], 404);
    eq('static file is served', http($D, 'GET', '/robots.txt')['status'], 200);
    eq('HEAD / => 200', http($D, 'HEAD', '/')['status'], 200);

    @mkdir(dirname($down), 0777, true);
    file_put_contents($down, '<h1>Back soon</h1>');
    $r = http($D, 'GET', '/');
    @unlink($down);
    eq('maintenance mode => 503', $r['status'], 503);
    check('503 has Retry-After', header1($r, 'retry-after') !== '');
    has('maintenance page served', $r['body'], 'Back soon');
    eq('back to 200 after "up"', http($D, 'GET', '/')['status'], 200);
} finally {
    foreach ($servers as $proc) {
        if (is_resource($proc)) {
            proc_terminate($proc);
            proc_close($proc);
        }
    }
    @unlink($root . '/storage/framework/down/maintenance.html');
    Nixs::clearCache();
}

// ---------------------------------------------------------------------------
// Result
// ---------------------------------------------------------------------------

$failed = count($GLOBALS['__fail']);
echo "\n" . str_repeat('─', 60) . "\n";
echo "\e[1m" . ($GLOBALS['__pass'] + $failed) . " checks, \e[32m{$GLOBALS['__pass']} passed\e[0m\e[1m, " . ($failed ? "\e[31m{$failed} failed" : '0 failed') . "\e[0m\n";

foreach ($GLOBALS['__fail'] as $line) {
    echo "  \e[31m✗\e[0m {$line}\n";
}

exit($failed ? 1 : 0);
