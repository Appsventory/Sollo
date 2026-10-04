<?php

/*
|--------------------------------------------------------------------------
| Sollo Bootstrap
|--------------------------------------------------------------------------
|
| Shared by public/index.php and the `fany` console:
|   1. registers an autoloader (Composer if vendor/ exists, otherwise a
|      built-in PSR-4 loader, so a fresh clone runs without `composer install`),
|   2. loads the global helper functions (env(), view(), config(), ...),
|   3. loads .env and applies the application timezone.
|
*/

if (defined('SOLLO_BASE_PATH')) {
    return SOLLO_BASE_PATH;
}

define('SOLLO_BASE_PATH', dirname(__DIR__));

if (is_file(SOLLO_BASE_PATH . '/vendor/autoload.php')) {
    require SOLLO_BASE_PATH . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        static $prefixes = null;
        $prefixes ??= [
            'Core\\' => SOLLO_BASE_PATH . '/core/',
            'App\\' => SOLLO_BASE_PATH . '/app/',
        ];

        foreach ($prefixes as $prefix => $dir) {
            if (strncmp($class, $prefix, strlen($prefix)) === 0) {
                $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        }
    });
}

require_once SOLLO_BASE_PATH . '/core/Support/Velo.php';

\Core\Support\Env::load(SOLLO_BASE_PATH . '/.env');

$timezone = \Core\Support\Env::env('APP_TIMEZONE');
if (is_string($timezone) && $timezone !== '') {
    if (!@date_default_timezone_set($timezone)) {
        trigger_error("Invalid APP_TIMEZONE '{$timezone}', falling back to UTC.", E_USER_WARNING);
        date_default_timezone_set('UTC');
    }
}

return SOLLO_BASE_PATH;
