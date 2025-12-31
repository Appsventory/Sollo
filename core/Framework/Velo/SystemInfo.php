<?php

namespace Core\Framework\Velo;

use ReflectionClass;

class SystemInfo
{
    /*
    |--------------------------------------------------------------------------
    | Semantic Versioning
    |--------------------------------------------------------------------------
    |
    | This framework follows the MAJOR.MINOR.PATCH versioning strategy.
    | Each component uses independent version tracking to ensure proper
    | compatibility and maintenance across releases.
    |
    */
    public const NIXS_TE = '3.0.0';
    public const NIXS_CS = '3.0.0';
    public const DB_FUNCTION = '3.1.0';
    public const ERROR_HANDLER_VERSION = '1.0.0';


    /*
    |--------------------------------------------------------------------------
    | Framework System Information
    |--------------------------------------------------------------------------
    |
    | Core metadata for the framework, including product name, version,
    | codename, release year, and current development status. This
    | information is used internally for diagnostics, reporting, and 
    | reference purposes.
    |
    */

    public const NAME = 'Sollo 3';
    public const SHORT_NAME = 'NV';
    public const VERSION = '3.0.0';
    public const CODENAME = 'Nara';
    public const RELEASE_YEAR = 2025;
    public const RELEASE_STATUS = 'stable';
    public const BASE_DOMAIN = 'https://sollo.xo.je';
    public const REPO_URL = 'https://github.com/Appsventory/Sollo';

    /*
    |--------------------------------------------------------------------------
    | Core Building Structure
    |--------------------------------------------------------------------------
    |
    | Defines the fundamental components that form the framework's core,
    | serving as a reference for bootstrap, runtime, and internal interactions.
    |
    */

    public const NINE_ENGINE = '1.0';
    public const RYU_ROUTER = '2.0';
    public const JIRO_ROUTING = '1.0';
    public const VELO_SYSTEM = '3.0';
    public const KAZE_UI = '1.0';
    public const KAZE_JS = '1.0';


    /*
    |--------------------------------------------------------------------------
    | Internal Vendor Information
    |--------------------------------------------------------------------------
    |
    | Vendor-specific details such as organization name and required PHP
    | version. These constants help maintain compatibility across
    | different environments and distributions.
    |
    */

    public const VENDOR = 'Appsventory';
    public const PHP_REQUIRED = '8.3';


    /*
    |--------------------------------------------------------------------------
    | Command Line Interface (CLI) Metadata
    |--------------------------------------------------------------------------
    |
    | Collection of information describing the identity and version of the
    | internal CLI. This metadata is used to display version information,
    | assist in debugging, and ensure consistent behavior across CLI commands.
    |
    */

    public const CLI_NAME = 'Fany CLI';
    public const CLI_SHORT_NAME = 'Fany';
    public const CLI_VERSION = '3.1.0';
    public const CLI_SUPPORT_URL = 'https://sollo.xo.je/fany/support';
    public const CLI_DOCS_URL = 'https://sollo.xo.je/fany/docs';
    public const CLI_REPOSITORY = 'https://github.com/Appsventory/Fany_CLI';


    public static function all(): array
    {
        $reflection = new ReflectionClass(self::class);

        // Ambil semua konstanta
        $constants = $reflection->getConstants();

        // Ambil semua static property
        $statics = $reflection->getStaticProperties();

        // Gabungkan dan kembalikan
        return array_merge($constants, $statics);
    }
}
