<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class MakeEnv extends BaseCommand
{
    public function handle(array $argv)
    {
        // Show help if requested
        if ($this->hasOption($argv, 'help') || $this->hasOption($argv, 'h')) {
            $this->showHelp();
            return;
        }

        $projectName = $this->getOptionValue($argv, 'name');
        $environment = $this->getOptionValue($argv, 'env') ?? 'local';
        $database = $this->getOptionValue($argv, 'database') ?? 'mysql';
        $force = $this->hasOption($argv, 'force');
        $interactive = $this->hasOption($argv, 'interactive') || $this->hasOption($argv, 'i');

        // Check if .env already exists
        if (file_exists('.env') && !$force) {
            $this->warning(".env file already exists!");
            echo "Use --force to overwrite or --interactive to edit\n";

            if ($this->confirm("Do you want to backup existing .env?", true)) {
                $this->backupEnv();
            }

            if (!$this->confirm("Continue and overwrite .env?", false)) {
                $this->info("Operation cancelled.");
                return;
            }
        }

        // Interactive mode
        if ($interactive) {
            $this->createInteractive();
            return;
        }

        // Generate .env
        $this->createEnv($projectName, $environment, $database, $argv);
    }

    protected function createInteractive()
    {
        $this->info("🎯 Interactive .env Generator");
        echo str_repeat('═', 60) . "\n\n";

        // Collect information
        $config = [];

        // Application
        $config['APP_NAME'] = $this->ask("Application name", "FANY App");
        $config['APP_ENV'] = $this->choice("Environment", [
            'local' => 'Local Development',
            'development' => 'Development',
            'staging' => 'Staging',
            'production' => 'Production',
            'testing' => 'Testing'
        ], 'local');
        $config['APP_DEBUG'] = $this->confirm("Enable debug mode?", $config['APP_ENV'] !== 'production');
        $config['APP_URL'] = $this->ask("Application URL", "http://localhost:8000");

        // Database
        $this->line("\n\e[1;33m📊 Database Configuration:\e[0m");
        $config['DB_CONNECTION'] = $this->choice("Database type", [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'SQL Server'
        ], 'mysql');

        if ($config['DB_CONNECTION'] !== 'sqlite') {
            $config['DB_HOST'] = $this->ask("Database host", "127.0.0.1");
            $config['DB_PORT'] = $this->ask("Database port", $this->getDefaultPort($config['DB_CONNECTION']));
            $config['DB_DATABASE'] = $this->ask("Database name", strtolower(str_replace(' ', '_', $config['APP_NAME'])));
            $config['DB_USERNAME'] = $this->ask("Database username", "root");
            $config['DB_PASSWORD'] = $this->ask("Database password", "");
        } else {
            $config['DB_DATABASE'] = $this->ask("SQLite database path", "database/database.sqlite");
        }

        // Generate .env content
        $content = $this->generateEnvContent($config);

        // Preview
        $this->line("\n\e[1;32m📋 Preview .env file:\e[0m");
        echo str_repeat('─', 60) . "\n";
        echo $content;
        echo str_repeat('─', 60) . "\n";

        if ($this->confirm("\nSave this configuration?", true)) {
            file_put_contents('.env', $content);
            $this->success(".env file created successfully!");
            $this->showNextSteps();
        } else {
            $this->info("Operation cancelled.");
        }
    }

    protected function createEnv($projectName, $environment, $database, $argv)
    {
        $projectName = $projectName ?? $this->getDefaultProjectName();
        $environment = $this->normalizeEnvironment($environment);

        $config = [
            'APP_NAME' => $projectName,
            'APP_ENV' => $environment,
            'APP_DEBUG' => ($environment === 'production') ? 'false' : 'true',
            'ERROR_DISPLAY' => 'inline',
            'APP_URL' => $this->getOptionValue($argv, 'url') ?? 'http://localhost:8000',
            'APP_TIMEZONE' => $this->getOptionValue($argv, 'timezone') ?? 'UTC',

            'DB_CONNECTION' => $database,
            'DB_HOST' => $this->getOptionValue($argv, 'db-host') ?? '127.0.0.1',
            'DB_PORT' => $this->getOptionValue($argv, 'db-port') ?? $this->getDefaultPort($database),
            'DB_DATABASE' => $this->getOptionValue($argv, 'db-name') ?? (
                $database === 'sqlite'
                    ? 'database/sollo.sqlite'
                    : strtolower(str_replace(' ', '_', $projectName))
            ),
            'DB_USERNAME' => $this->getOptionValue($argv, 'db-user') ?? 'root',
            'DB_PASSWORD' => $this->getOptionValue($argv, 'db-pass') ?? '',
        ];

        $content = $this->generateEnvContent($config);

        file_put_contents('.env', $content);

        $this->success(".env file created successfully!");
        $this->info("Environment: {$environment}");
        $this->info("Project: {$projectName}");
        $this->info("Database: {$database}");

        $this->ensureSqliteDatabase($config);

        // Create .env.example
        if ($this->hasOption($argv, 'with-example')) {
            $this->createEnvExample($content);
        }

        $this->showNextSteps();
    }

    /**
     * Only variables the framework actually reads are written.
     */
    protected function generateEnvContent($config)
    {
        $debug = $config['APP_DEBUG'];
        $debug = $debug === true ? 'true' : ($debug === false ? 'false' : $debug);
        $isSqlite = $config['DB_CONNECTION'] === 'sqlite';

        $content = "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $content .= "# Application\n";
        $content .= "APP_NAME=\"{$config['APP_NAME']}\"\n";
        $content .= "APP_ENV={$config['APP_ENV']}\n";
        $content .= "APP_DEBUG={$debug}\n";
        $content .= "ERROR_DISPLAY=" . ($config['ERROR_DISPLAY'] ?? 'inline') . "\n";
        $content .= "APP_URL={$config['APP_URL']}\n";
        $content .= "APP_TIMEZONE=" . ($config['APP_TIMEZONE'] ?? 'UTC') . "\n";

        $content .= "\n# Database (sqlite | mysql | pgsql)\n";
        $content .= "DB_CONNECTION={$config['DB_CONNECTION']}\n";

        if (!$isSqlite) {
            $content .= "DB_HOST={$config['DB_HOST']}\n";
            $content .= "DB_PORT={$config['DB_PORT']}\n";
        }

        $content .= "DB_DATABASE={$config['DB_DATABASE']}\n";

        if (!$isSqlite) {
            $content .= "DB_USERNAME={$config['DB_USERNAME']}\n";
            $content .= "DB_PASSWORD=" . ($config['DB_PASSWORD'] ?? '') . "\n";
        }

        $content .= "\n# Session lifetime in minutes\n";
        $content .= "SESSION_LIFETIME=120\n";

        $content .= "\n# API CORS: * or a comma separated list of origins\n";
        $content .= "API_ALLOWED_ORIGINS=*\n";

        $content .= "\n# Trust X-Forwarded-* headers only from these proxies (comma separated IPs or *)\n";
        $content .= "TRUSTED_PROXIES=\n";

        return $content;
    }


    /**
     * Ensure SQLite directory and database file exist (mkdir -p + touch).
     */
    protected function ensureSqliteDatabase(array $config): void
    {
        if (($config['DB_CONNECTION'] ?? '') !== 'sqlite') {
            return;
        }

        $path = $config['DB_DATABASE'] ?? 'database/sollo.sqlite';
        if ($path === '' || $path === ':memory:') {
            return;
        }

        if (!str_starts_with($path, '/') && !str_starts_with($path, '~')) {
            $path = getcwd() . '/' . ltrim($path, '/');
        } elseif (str_starts_with($path, '~')) {
            $path = ($_SERVER['HOME'] ?? getenv('HOME') ?: '') . substr($path, 1);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                $this->warning("Could not create SQLite directory: {$dir}");
                return;
            }
            $this->info("Created directory: {$dir}");
        }

        if (!file_exists($path)) {
            if (@touch($path) === false) {
                $this->warning("Could not create SQLite file: {$path}");
                return;
            }
            $this->success("SQLite database file created: " . ($config['DB_DATABASE'] ?? $path));
        } else {
            $this->info("SQLite database file already exists: " . ($config['DB_DATABASE'] ?? $path));
        }
    }

    protected function createEnvExample($content)
    {
        // Remove sensitive values
        $example = preg_replace('/^(.*PASSWORD|.*KEY|.*SECRET)=.*/m', '$1=', $content);
        $example = preg_replace('/^(MAIL_USERNAME|MAIL_HOST|DB_USERNAME)=.*/m', '$1=', $example);

        file_put_contents('.env.example', $example);
        $this->success(".env.example created successfully!");
    }

    protected function backupEnv()
    {
        $backupName = '.env.backup.' . date('Y-m-d_His');
        copy('.env', $backupName);
        $this->success("Backup created: {$backupName}");
    }

    protected function getDefaultProjectName()
    {
        $dir = basename(getcwd());
        return ucwords(str_replace(['-', '_'], ' ', $dir));
    }

    protected function normalizeEnvironment($env)
    {
        $env = strtolower($env);

        $aliases = [
            'development' => 'development',
            'develop' => 'development',
            'dev' => 'development',
            'production' => 'production',
            'prod' => 'production',
            'staging' => 'staging',
            'stage' => 'staging',
            'testing' => 'testing',
            'test' => 'testing',
            'local' => 'local',
        ];

        return $aliases[$env] ?? 'local';
    }

    protected function getDefaultPort($database)
    {
        $ports = [
            'mysql' => '3306',
            'pgsql' => '5432',
            'sqlsrv' => '1433',
            'mongodb' => '27017',
        ];

        return $ports[$database] ?? '3306';
    }

    protected function showHelp()
    {
        echo "\n\e[1;33m📝 make:env - Environment Configuration Generator\e[0m\n";
        echo str_repeat('═', 70) . "\n\n";

        echo "\e[1;32mUSAGE:\e[0m\n";
        echo "  \e[36mphp fany make:env [options]\e[0m\n\n";

        echo "\e[1;32mDESCRIPTION:\e[0m\n";
        echo "  Generate .env configuration file with customizable settings.\n";
        echo "  Supports multiple environments, databases, and interactive mode.\n\n";

        echo "\e[1;32mMODES:\e[0m\n";
        echo "  \e[36m--interactive, -i\e[0m     Interactive wizard mode (recommended)\n";
        echo "  \e[36m[options]\e[0m              Quick generation with command-line options\n\n";

        echo "\e[1;32mAPPLICATION OPTIONS:\e[0m\n";
        echo "  \e[36m--name=<n>\e[0m             Application name\n";
        echo "  \e[36m--env=<env>\e[0m            Environment (local|development|staging|production|testing)\n";
        echo "  \e[36m--url=<url>\e[0m            Application URL\n";
        echo "  \e[36m--timezone=<tz>\e[0m        Timezone (default: UTC)\n";

        echo "\e[1;32mDATABASE OPTIONS:\e[0m\n";
        echo "  \e[36m--database=<type>\e[0m      Database type (sqlite|mysql|pgsql)\n";
        echo "  \e[36m--db-host=<host>\e[0m       Database host\n";
        echo "  \e[36m--db-port=<port>\e[0m       Database port\n";
        echo "  \e[36m--db-name=<name>\e[0m       Database name\n";
        echo "  \e[36m--db-user=<user>\e[0m       Database username\n";
        echo "  \e[36m--db-pass=<pass>\e[0m       Database password\n\n";

        echo "\e[1;32mCONTROL OPTIONS:\e[0m\n";
        echo "  \e[36m--force\e[0m                 Overwrite existing .env file\n";
        echo "  \e[36m--with-example\e[0m          Also generate .env.example\n";
        echo "  \e[36m--help, -h\e[0m              Show this help message\n\n";

        echo "\e[1;32mEXAMPLES:\e[0m\n";
        echo "  \e[2m# Interactive mode (recommended)\e[0m\n";
        echo "  \e[36mphp fany make:env -i\e[0m\n\n";

        echo "  \e[2m# Quick local setup\e[0m\n";
        echo "  \e[36mphp fany make:env --name=\"Blog App\"\e[0m\n\n";

        echo "  \e[2m# Development environment\e[0m\n";
        echo "  \e[36mphp fany make:env --name=\"My App\" --env=development\e[0m\n\n";

        echo "  \e[2m# Production with PostgreSQL\e[0m\n";
        echo "  \e[36mphp fany make:env --name=\"My App\" --env=production \\\n";
        echo "    --database=pgsql --db-host=db.server.com --force\e[0m\n\n";

        echo "  \e[2m# Complete setup\e[0m\n";
        echo "  \e[36mphp fany make:env \\\n";
        echo "    --name=\"E-Commerce\" \\\n";
        echo "    --env=staging \\\n";
        echo "    --url=https://staging.shop.com \\\n";
        echo "    --database=mysql \\\n";
        echo "    --with-example\e[0m\n\n";

        echo "\e[1;32mENVIRONMENT TYPES:\e[0m\n";
        echo "  \e[36mlocal\e[0m       Local development (debug enabled)\n";
        echo "  \e[36mdevelopment\e[0m Development server (debug enabled)\n";
        echo "  \e[36mstaging\e[0m     Pre-production testing (debug disabled)\n";
        echo "  \e[36mproduction\e[0m  Production (debug disabled, security enabled)\n";
        echo "  \e[36mtesting\e[0m     Testing environment\n\n";

        echo "\e[1;33m💡 TIPS:\e[0m\n";
        echo "  • Use \e[36m-i\e[0m for first-time setup\n";
        echo "  • Never commit .env to version control\n";
        echo "  • Use \e[36m--with-example\e[0m to create team template\n";
        echo "  • Command will prompt to backup existing .env\n\n";

        echo "\e[1;32mNEXT STEPS:\e[0m\n";
        echo "  1. \e[36mphp fany make:env -i\e[0m         # Generate .env\n";
        echo "  2. \e[36mphp fany db:migrate\e[0m          # Setup database\n";
        echo "  3. \e[36mphp fany server\e[0m              # Start server\n\n";
    }

    protected function showNextSteps()
    {
        echo "\n\e[1;32m✅ Next Steps:\e[0m\n";
        echo str_repeat('─', 60) . "\n";
        echo "  1. Review your .env file configuration\n";
        echo "  2. Update database credentials if needed\n";
        echo "  3. Run: \e[36mphp fany db:migrate\e[0m to setup database\n";
        echo "  4. Run: \e[36mphp fany server\e[0m to start development server\n";
        echo "\n\e[33m💡 Tips:\e[0m\n";
        echo "  • Never commit .env to version control\n";
        echo "  • Use .env.example as template for team\n";
        echo "  • Keep production credentials secure\n";
        echo str_repeat('─', 60) . "\n\n";
    }
}
