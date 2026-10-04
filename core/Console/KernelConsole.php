<?php

namespace Core\Console;

use Core\Console\Commands\ServerCommand;
use Core\Framework\Velo\SystemInfo as Sollo;
use Core\Console\Commands\MakeController;
use Core\Console\Commands\MakeModel;
use Core\Console\Commands\ServiceCommand;
use Core\Console\Commands\MakeEnv;
use Core\Console\Commands\MakeMiddleware;
use Core\Console\Commands\MakeMigration;
use Core\Console\Commands\MakeNixs;
use Core\Console\Commands\MakeRoute;
use Core\Console\Commands\MakeSeeder;
use Core\Console\Commands\MakeView;
use Core\Console\Commands\MakeComponent;
use Core\Console\Commands\MakeConfig;
use Core\Console\Commands\DatabaseCommand;
use Core\Console\Commands\CacheCommand;
use Core\Console\Commands\RouteListCommand;
use Core\Console\Traits\ConsoleOutputFormatter;

class KernelConsole
{
    use ConsoleOutputFormatter;

    protected $commands = [];

    public function __construct()
    {
        $this->registerCommands();
    }

    protected function registerCommands()
    {
        $this->commands = [
            // Server Commands
            'server' => ServerCommand::class,
            'serve' => ServerCommand::class,

            // Down & Up Commands
            'down' => ServiceCommand::class,
            'up' => ServiceCommand::class,

            // Make Commands
            'make:controller' => MakeController::class,
            'make:component' => MakeComponent::class,
            'make:env' => MakeEnv::class,
            'make:model' => MakeModel::class,
            'make:middleware' => MakeMiddleware::class,
            'make:migration' => MakeMigration::class,
            'make:nixs' => MakeNixs::class,
            'make:route' => MakeRoute::class,
            'make:seeder' => MakeSeeder::class,
            'make:view' => MakeView::class,
            'make:config' => MakeConfig::class,

            // Database Commands
            'db:migrate' => DatabaseCommand::class,
            'db:seed' => DatabaseCommand::class,
            'db:reset' => DatabaseCommand::class,
            'db:rollback' => DatabaseCommand::class,
            'db:status' => DatabaseCommand::class,
            'db:fresh' => DatabaseCommand::class,
            'db:backup' => DatabaseCommand::class,

            // Cache & routes
            'cache:clear' => CacheCommand::class,
            'nixs:clear' => CacheCommand::class,
            'route:list' => RouteListCommand::class,
        ];
    }

    /**
     * Run a command and return the process exit code (0 = success).
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        if (!$command || in_array($command, ['help', '--help', '-h'])) {
            $this->showHelp();
            return 0;
        }

        if (in_array($command, ['--version', '-v', 'version'])) {
            $this->showVersion();
            return 0;
        }

        if (!isset($this->commands[$command])) {
            $this->error("Unknown command: $command");
            $this->showSuggestions($command);
            $this->showHelp();
            return 1;
        }

        $commandClass = $this->commands[$command];

        try {
            $commandInstance = new $commandClass();

            if (!method_exists($commandInstance, 'handle')) {
                $this->error("Command handler not found for: $command");
                return 1;
            }

            $result = $commandInstance->handle($argv);

            if (is_int($result)) {
                return $result;
            }

            return method_exists($commandInstance, 'hasFailed') && $commandInstance->hasFailed() ? 1 : 0;
        } catch (\Throwable $e) {
            $this->error("Command failed: " . $e->getMessage());
            if (in_array('--verbose', $argv, true) || in_array('-v', $argv, true)) {
                echo "\n\e[90mStack trace:\e[0m\n";
                echo $e->getTraceAsString() . "\n";
            }
            return 1;
        }
    }


    protected function showSuggestions($command)
    {
        $suggestions = [];
        $commands = array_keys($this->commands);

        foreach ($commands as $availableCommand) {
            $similarity = 0;
            similar_text($command, $availableCommand, $similarity);
            if ($similarity > 60) {
                $suggestions[] = $availableCommand;
            }
        }

        if (!empty($suggestions)) {
            echo "\n\e[1;33m💡 Did you mean:\e[0m\n";
            foreach ($suggestions as $suggestion) {
                echo "    \e[36mphp fany {$suggestion}\e[0m\n";
            }
        }
    }

    public function showHelp()
    {
        echo "\n";
        echo "  \e[1;33m🚀 " . strtoupper(Sollo::CLI_NAME) . " Console v" . Sollo::CLI_VERSION . " \e[0m\n";
        echo "  \e[36mPowerful command-line interface for " . Sollo::NAME . " Framework\e[0m\n";
        echo "\n";
        echo "\e[1;32mUSAGE:\e[0m\n";
        echo "  \e[36mphp fany <command> [options] [arguments]\e[0m\n";
        echo "\n";

        echo "\e[1;32m📝 MAKE COMMANDS:\e[0m\n";
        echo "  \e[36mmake:controller <n> [--model] [--resource]\e[0m   Create a new controller\n";
        echo "  \e[36mmake:model <n>\e[0m                               Create a new model\n";
        echo "  \e[36mmake:view <n>\e[0m                                Create a new view (.nixs.php)\n";
        echo "  \e[36mmake:component <n> [--props] [--slots] [--class]\e[0m  Nixs component (@nixscomponent)\n";
        echo "  \e[36mmake:middleware <n>\e[0m                          Create a new middleware class\n";
        echo "  \e[36mmake:route <n> [flags] [--api]\e[0m                Append route(s) to app/Routes/web.php or app/Routes/api.php\n";
        echo "  \e[36mmake:migration <n> [--table=<n>]\e[0m             Create a new migration\n";
        echo "  \e[36mmake:seeder <n>\e[0m                              Create a new seeder\n";
        echo "  \e[36mmake:config <n>\e[0m                              Create a new config file\n";
        echo "  \e[36mmake:nixs <n> [--type=<type>] [flags]\e[0m        Create Nixs templates\n";
        echo "  \e[36mmake:env [options]\e[0m                           Create .env configuration file\n";

        echo "\n\e[1;32m🌐 SERVER COMMANDS:\e[0m\n";
        echo "  \e[36mserver [--port=<port>] [--host=<host>]\e[0m       Start PHP dev server (default: 8000)\n";
        echo "  \e[36mserve [--port=<port>] [--host=<host>]\e[0m        Alias for server\n";

        echo "\n\e[1;32m   DATABASE COMMANDS:\e[0m\n";
        echo "  \e[36mdb:migrate\e[0m                                   Run pending migrations\n";
        echo "  \e[36mdb:rollback [--step=<n>]\e[0m                     Rollback migrations\n";
        echo "  \e[36mdb:seed [--class=<n>]\e[0m                        Run database seeders\n";
        echo "  \e[36mdb:seed --list\e[0m                               List all available seeders\n";
        echo "  \e[36mdb:seed --rm=<name>\e[0m                          Remove a seeder file\n";
        echo "  \e[36mdb:reset\e[0m                                     Reset database and run migrations\n";
        echo "  \e[36mdb:fresh\e[0m                                     Drop all tables and re-run migrations\n";
        echo "  \e[36mdb:status\e[0m                                    Show migration status\n";
        echo "  \e[36mdb:backup [--type=<t>] [--compress]\e[0m          Backup database to SQL\n";

        echo "\n\e[1;32m🔧 MAINTENANCE COMMANDS:\e[0m\n";
        echo "  \e[36mdown [--force]\e[0m                               Put application in maintenance mode\n";
        echo "  \e[36mup\e[0m                                           Bring application out of maintenance\n";
        echo "  \e[36mcache:clear\e[0m                                  Clear app & Nixs view cache\n";
        echo "  \e[36mnixs:clear\e[0m                                   Alias for cache:clear (views)\n";
        echo "  \e[36mroute:list\e[0m                                   List all registered routes\n";

        echo "\n\e[1;32m📋 ROUTE OPTIONS:\e[0m\n";
        echo "       \e[2m--G          Add GET route → index()\e[0m\n";
        echo "       \e[2m--P          Add POST route → store()\e[0m\n";
        echo "       \e[2m--U          Add PUT route → update()\e[0m\n";
        echo "       \e[2m--D          Add DELETE route → destroy()\e[0m\n";
        echo "       \e[2m--RESOURCE   Add all CRUD routes\e[0m\n";
        echo "       \e[2m--M=Name     Attach middleware\e[0m\n";
        echo "       \e[2m--api        Use or create app/Routes/api.php\e[0m\n";

        echo "\n\e[1;32m🆘 HELP COMMANDS:\e[0m\n";
        echo "  \e[36mhelp, --help, -h\e[0m                             Show this help menu\n";
        echo "  \e[36m--version, -v\e[0m                                Show version information\n";

        echo "\n\e[1;32m💡 EXAMPLES:\e[0m\n";
        echo "  \e[2mphp fany make:controller UserController --resource --model\e[0m\n";
        echo "  \e[2mphp fany make:migration create_users_table --table=users\e[0m\n";
        echo "  \e[2mphp fany server --port=3000 --host=0.0.0.0\e[0m\n";
        echo "  \e[2mphp fany db:seed --class=UserSeeder\e[0m\n";

        echo "\n\e[1;32m🔗 MORE INFO:\e[0m\n";
        echo "  \e[2mDocumentation: " . Sollo::BASE_DOMAIN . "/docs\e[0m\n";
        echo "  \e[2mGitHub: " . Sollo::REPO_URL . "\e[0m\n";
        echo "  \e[2mSupport: " . Sollo::CLI_SUPPORT_URL . "\e[0m\n";
        echo "\n";
    }

    protected function showVersion()
    {
        echo "\n";
        echo "  \e[1;33m🚀 " . strtoupper(Sollo::CLI_NAME) . " Console\e[0m\n";
        echo "\n";
        echo "  \e[2m" . strtoupper(Sollo::CLI_SHORT_NAME) . " " . Sollo::CLI_VERSION . "\e[0m\n";
        echo "  \e[2m" . strtoupper(Sollo::SHORT_NAME) . " " . Sollo::VERSION . "\e[0m\n";
        echo "  \e[2mPHP " . PHP_VERSION . "\e[0m\n";
        echo "\n";
        echo "  \e[1;32m📦 Components:\e[0m\n";
        echo "  \e[36m• Core Console System\e[0m      v" . Sollo::VERSION . "\n";
        echo "  \e[36m• Nixs Component System\e[0m    v" . Sollo::NIXS_CS . "\n";
        echo "  \e[36m• Nixs Template Engine\e[0m     v" . Sollo::NIXS_TE . "\n";
        echo "  \e[36m• Error System\e[0m             v" . Sollo::ERROR_HANDLER_VERSION . "\n";
        echo "  \e[36m• Database Migrations\e[0m      v" . Sollo::DB_FUNCTION . "\n";
        echo "\n";
        echo "  \e[2mCopyright (c) " . Sollo::RELEASE_YEAR . " " . Sollo::NAME . "(" . Sollo::CODENAME . ")\e[0m\n";
        echo "\n";
    }
}
