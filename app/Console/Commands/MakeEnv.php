<?php

namespace App\Console\Commands;

class MakeEnv extends BaseCommand
{
    protected $environments = [
        'development' => 'dev',
        'staging' => 'staging',
        'production' => 'prod',
        'testing' => 'test',
        'local' => 'local',
    ];

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
            'dev' => 'Development',
            'staging' => 'Staging',
            'prod' => 'Production',
            'test' => 'Testing'
        ], 'local');
        $config['APP_DEBUG'] = $this->confirm("Enable debug mode?", $config['APP_ENV'] !== 'prod');
        $config['APP_URL'] = $this->ask("Application URL", "http://localhost:8000");
        $config['APP_KEY'] = $this->generateKey();
        
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
        
        // Cache
        $this->line("\n\e[1;33m⚡ Cache Configuration:\e[0m");
        $config['CACHE_DRIVER'] = $this->choice("Cache driver", [
            'file' => 'File',
            'redis' => 'Redis',
            'memcached' => 'Memcached',
            'array' => 'Array (Testing)'
        ], 'file');
        
        // Session
        $config['SESSION_DRIVER'] = $this->choice("Session driver", [
            'file' => 'File',
            'cookie' => 'Cookie',
            'database' => 'Database',
            'redis' => 'Redis'
        ], 'file');
        
        // Mail (optional)
        if ($this->confirm("\nConfigure mail settings?", false)) {
            $this->line("\n\e[1;33m📧 Mail Configuration:\e[0m");
            $config['MAIL_MAILER'] = $this->choice("Mail driver", [
                'smtp' => 'SMTP',
                'sendmail' => 'Sendmail',
                'mailgun' => 'Mailgun',
                'ses' => 'Amazon SES'
            ], 'smtp');
            
            if ($config['MAIL_MAILER'] === 'smtp') {
                $config['MAIL_HOST'] = $this->ask("SMTP host", "smtp.mailtrap.io");
                $config['MAIL_PORT'] = $this->ask("SMTP port", "2525");
                $config['MAIL_USERNAME'] = $this->ask("SMTP username", "");
                $config['MAIL_PASSWORD'] = $this->ask("SMTP password", "");
                $config['MAIL_ENCRYPTION'] = $this->ask("Encryption (tls/ssl)", "tls");
                $config['MAIL_FROM_ADDRESS'] = $this->ask("From address", "noreply@" . parse_url($config['APP_URL'], PHP_URL_HOST));
                $config['MAIL_FROM_NAME'] = $this->ask("From name", $config['APP_NAME']);
            }
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
            'APP_KEY' => $this->generateKey(),
            'APP_DEBUG' => ($environment === 'prod') ? 'false' : 'true',
            'ERROR_DISPLAY' => 'inline',
            'APP_URL' => $this->getOptionValue($argv, 'url') ?? 'http://localhost:8000',
            'APP_TIMEZONE' => $this->getOptionValue($argv, 'timezone') ?? 'UTC',
            'APP_LOCALE' => $this->getOptionValue($argv, 'locale') ?? 'en',
            
            'DB_CONNECTION' => $database,
            'DB_HOST' => $this->getOptionValue($argv, 'db-host') ?? '127.0.0.1',
            'DB_PORT' => $this->getOptionValue($argv, 'db-port') ?? $this->getDefaultPort($database),
            'DB_DATABASE' => $this->getOptionValue($argv, 'db-name') ?? strtolower(str_replace(' ', '_', $projectName)),
            'DB_USERNAME' => $this->getOptionValue($argv, 'db-user') ?? 'root',
            'DB_PASSWORD' => $this->getOptionValue($argv, 'db-pass') ?? '',
            
            'CACHE_DRIVER' => $this->getOptionValue($argv, 'cache') ?? 'file',
            'SESSION_DRIVER' => $this->getOptionValue($argv, 'session') ?? 'file',
            'QUEUE_CONNECTION' => $this->getOptionValue($argv, 'queue') ?? 'sync',
            
            'MAIL_MAILER' => $this->getOptionValue($argv, 'mail-driver') ?? 'smtp',
            'MAIL_HOST' => $this->getOptionValue($argv, 'mail-host') ?? 'smtp.mailtrap.io',
            'MAIL_PORT' => $this->getOptionValue($argv, 'mail-port') ?? '2525',
            'MAIL_USERNAME' => $this->getOptionValue($argv, 'mail-user') ?? null,
            'MAIL_PASSWORD' => $this->getOptionValue($argv, 'mail-pass') ?? null,
            'MAIL_ENCRYPTION' => $this->getOptionValue($argv, 'mail-encryption') ?? 'tls',
            'MAIL_FROM_ADDRESS' => $this->getOptionValue($argv, 'mail-from') ?? 'noreply@example.com',
            'MAIL_FROM_NAME' => $projectName,
        ];
        
        // Add environment-specific configs
        if ($environment === 'prod') {
            $config = array_merge($config, $this->getProductionConfig());
        } elseif ($environment === 'dev' || $environment === 'local') {
            $config = array_merge($config, $this->getDevelopmentConfig());
        }
        
        $content = $this->generateEnvContent($config);
        
        file_put_contents('.env', $content);
        
        $this->success(".env file created successfully!");
        $this->info("Environment: {$environment}");
        $this->info("Project: {$projectName}");
        $this->info("Database: {$database}");
        
        // Create .env.example
        if ($this->hasOption($argv, 'with-example')) {
            $this->createEnvExample($content);
        }
        
        $this->showNextSteps();
    }

    protected function generateEnvContent($config)
    {
        $content = "# Application Configuration\n";
        $content .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "# Environment: " . ($config['APP_ENV'] ?? 'local') . "\n\n";
        
        // Application
        $content .= "# Application\n";
        $content .= "APP_NAME=\"{$config['APP_NAME']}\"\n";
        $content .= "APP_ENV={$config['APP_ENV']}\n";
        $content .= "APP_KEY={$config['APP_KEY']}\n";
        $content .= "APP_DEBUG=" . ($config['APP_DEBUG'] === true ? 'true' : ($config['APP_DEBUG'] === false ? 'false' : $config['APP_DEBUG'])) . "\n";
        $content .= "ERROR_DISPLAY={$config['ERROR_DISPLAY']}\n";
        $content .= "APP_URL={$config['APP_URL']}\n";
        
        if (isset($config['APP_TIMEZONE'])) {
            $content .= "APP_TIMEZONE={$config['APP_TIMEZONE']}\n";
        }
        if (isset($config['APP_LOCALE'])) {
            $content .= "APP_LOCALE={$config['APP_LOCALE']}\n";
        }
        
        // Database
        $content .= "\n# Database\n";
        $content .= "DB_CONNECTION={$config['DB_CONNECTION']}\n";
        
        if ($config['DB_CONNECTION'] !== 'sqlite') {
            $content .= "DB_HOST={$config['DB_HOST']}\n";
            $content .= "DB_PORT={$config['DB_PORT']}\n";
        }
        
        $content .= "DB_DATABASE={$config['DB_DATABASE']}\n";
        
        if ($config['DB_CONNECTION'] !== 'sqlite') {
            $content .= "DB_USERNAME={$config['DB_USERNAME']}\n";
            $content .= "DB_PASSWORD=" . ($config['DB_PASSWORD'] ?? '') . "\n";
        }
        
        // Cache & Session
        $content .= "\n# Cache & Session\n";
        $content .= "CACHE_DRIVER={$config['CACHE_DRIVER']}\n";
        $content .= "SESSION_DRIVER={$config['SESSION_DRIVER']}\n";
        $content .= "SESSION_LIFETIME=120\n";
        
        if (isset($config['QUEUE_CONNECTION'])) {
            $content .= "QUEUE_CONNECTION={$config['QUEUE_CONNECTION']}\n";
        }
        
        // Redis (if using redis)
        if ($config['CACHE_DRIVER'] === 'redis' || $config['SESSION_DRIVER'] === 'redis') {
            $content .= "\n# Redis\n";
            $content .= "REDIS_HOST=" . ($config['REDIS_HOST'] ?? '127.0.0.1') . "\n";
            $content .= "REDIS_PASSWORD=" . ($config['REDIS_PASSWORD'] ?? 'null') . "\n";
            $content .= "REDIS_PORT=" . ($config['REDIS_PORT'] ?? '6379') . "\n";
        }
        
        // Mail
        if (isset($config['MAIL_MAILER'])) {
            $content .= "\n# Mail\n";
            $content .= "MAIL_MAILER={$config['MAIL_MAILER']}\n";
            $content .= "MAIL_HOST={$config['MAIL_HOST']}\n";
            $content .= "MAIL_PORT={$config['MAIL_PORT']}\n";
            $content .= "MAIL_USERNAME=" . ($config['MAIL_USERNAME'] ?? '') . "\n";
            $content .= "MAIL_PASSWORD=" . ($config['MAIL_PASSWORD'] ?? '') . "\n";
            $content .= "MAIL_ENCRYPTION={$config['MAIL_ENCRYPTION']}\n";
            $content .= "MAIL_FROM_ADDRESS={$config['MAIL_FROM_ADDRESS']}\n";
            $content .= "MAIL_FROM_NAME=\"{$config['MAIL_FROM_NAME']}\"\n";
        }
        
        // Logging
        $content .= "\n# Logging\n";
        $content .= "LOG_CHANNEL=" . ($config['LOG_CHANNEL'] ?? 'stack') . "\n";
        $content .= "LOG_LEVEL=" . ($config['LOG_LEVEL'] ?? 'debug') . "\n";
        
        // Security
        $content .= "\n# Security\n";
        $content .= "CSRF_ENABLED=" . ($config['CSRF_ENABLED'] ?? 'true') . "\n";
        $content .= "CORS_ENABLED=" . ($config['CORS_ENABLED'] ?? 'false') . "\n";
        
        // Asset URLs
        $content .= "\n# Assets\n";
        $content .= "ASSET_URL=" . ($config['ASSET_URL'] ?? $config['APP_URL']) . "\n";
        
        // Additional configs
        if (isset($config['AWS_ACCESS_KEY_ID'])) {
            $content .= "\n# AWS\n";
            $content .= "AWS_ACCESS_KEY_ID={$config['AWS_ACCESS_KEY_ID']}\n";
            $content .= "AWS_SECRET_ACCESS_KEY={$config['AWS_SECRET_ACCESS_KEY']}\n";
            $content .= "AWS_DEFAULT_REGION={$config['AWS_DEFAULT_REGION']}\n";
            $content .= "AWS_BUCKET={$config['AWS_BUCKET']}\n";
        }
        
        return $content;
    }

    protected function getProductionConfig()
    {
        return [
            'LOG_LEVEL' => 'error',
            'CSRF_ENABLED' => 'true',
            'CORS_ENABLED' => 'true',
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_HTTP_ONLY' => 'true',
        ];
    }

    protected function getDevelopmentConfig()
    {
        return [
            'LOG_LEVEL' => 'debug',
            'QUERY_LOG' => 'true',
            'PROFILER_ENABLED' => 'true',
        ];
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

    protected function generateKey()
    {
        return 'base64:' . base64_encode(random_bytes(32));
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
            'development' => 'dev',
            'develop' => 'dev',
            'production' => 'prod',
            'prod' => 'prod',
            'staging' => 'staging',
            'stage' => 'staging',
            'testing' => 'test',
            'test' => 'test',
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
        echo "  \e[36m--env=<env>\e[0m            Environment (local|dev|staging|prod|test)\n";
        echo "  \e[36m--url=<url>\e[0m            Application URL\n";
        echo "  \e[36m--timezone=<tz>\e[0m        Timezone (default: UTC)\n";
        echo "  \e[36m--locale=<locale>\e[0m      Locale (default: en)\n\n";
        
        echo "\e[1;32mDATABASE OPTIONS:\e[0m\n";
        echo "  \e[36m--database=<type>\e[0m      Database type (mysql|pgsql|sqlite|sqlsrv)\n";
        echo "  \e[36m--db-host=<host>\e[0m       Database host\n";
        echo "  \e[36m--db-port=<port>\e[0m       Database port\n";
        echo "  \e[36m--db-name=<name>\e[0m       Database name\n";
        echo "  \e[36m--db-user=<user>\e[0m       Database username\n";
        echo "  \e[36m--db-pass=<pass>\e[0m       Database password\n\n";
        
        echo "\e[1;32mCACHE & SESSION OPTIONS:\e[0m\n";
        echo "  \e[36m--cache=<driver>\e[0m       Cache driver (file|redis|memcached|array)\n";
        echo "  \e[36m--session=<driver>\e[0m     Session driver (file|cookie|database|redis)\n";
        echo "  \e[36m--queue=<driver>\e[0m       Queue driver (sync|database|redis)\n\n";
        
        echo "\e[1;32mMAIL OPTIONS:\e[0m\n";
        echo "  \e[36m--mail-driver=<driver>\e[0m Mail driver (smtp|sendmail|mailgun|ses)\n";
        echo "  \e[36m--mail-host=<host>\e[0m     SMTP host\n";
        echo "  \e[36m--mail-port=<port>\e[0m     SMTP port\n";
        echo "  \e[36m--mail-user=<user>\e[0m     SMTP username\n";
        echo "  \e[36m--mail-pass=<pass>\e[0m     SMTP password\n";
        echo "  \e[36m--mail-encryption=<enc>\e[0m Encryption (tls|ssl)\n";
        echo "  \e[36m--mail-from=<email>\e[0m    From email address\n\n";
        
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
        echo "  \e[36mphp fany make:env --name=\"My App\" --env=dev\e[0m\n\n";
        
        echo "  \e[2m# Production with PostgreSQL\e[0m\n";
        echo "  \e[36mphp fany make:env --name=\"My App\" --env=prod \\\n";
        echo "    --database=pgsql --db-host=db.server.com --force\e[0m\n\n";
        
        echo "  \e[2m# Complete setup\e[0m\n";
        echo "  \e[36mphp fany make:env \\\n";
        echo "    --name=\"E-Commerce\" \\\n";
        echo "    --env=staging \\\n";
        echo "    --url=https://staging.shop.com \\\n";
        echo "    --database=mysql \\\n";
        echo "    --cache=redis \\\n";
        echo "    --session=redis \\\n";
        echo "    --with-example\e[0m\n\n";
        
        echo "\e[1;32mENVIRONMENT TYPES:\e[0m\n";
        echo "  \e[36mlocal\e[0m       Local development (debug enabled)\n";
        echo "  \e[36mdev\e[0m         Development server (debug enabled)\n";
        echo "  \e[36mstaging\e[0m     Pre-production testing (debug disabled)\n";
        echo "  \e[36mprod\e[0m        Production (debug disabled, security enabled)\n";
        echo "  \e[36mtest\e[0m        Testing environment (SQLite, array cache)\n\n";
        
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