<?php

namespace App\Console;

use App\Console\Commands\MakeController;
use App\Console\Commands\MakeModel;
use App\Console\Commands\MakeMiddleware;
use App\Console\Commands\MakeView;
use App\Console\Commands\MakeRoute;
use App\Console\Commands\MakeMigration;
use App\Console\Commands\MakeSeeder;
use App\Console\Commands\MakeConfig;
use App\Console\Commands\MakeNixs;
use App\Console\Commands\MakeComponent;
use App\Console\Commands\ServerCommand;
use App\Console\Commands\DatabaseCommand;
use App\Console\Commands\MakeEnv;
use App\Console\NineVerse;


class KernelConsole
{
    protected $commands = [];

    public function __construct()
    {
        $this->registerCommands();
    }

    protected function registerCommands()
    {
        $this->commands = [
            // Make Commands
            'make:controller' => MakeController::class,
            'make:model' => MakeModel::class,
            'make:middleware' => MakeMiddleware::class,
            'make:view' => MakeView::class,
            'make:route' => MakeRoute::class,
            'make:migration' => MakeMigration::class,
            'make:seeder' => MakeSeeder::class,
            'make:config' => MakeConfig::class,
            'make:nixs' => MakeNixs::class,
            'make:component' => MakeComponent::class,
            'make:env' => MakeEnv::class,
            
            // Server Commands
            'server' => ServerCommand::class,
            'serve' => ServerCommand::class,
            
            // Database Commands
            'db:migrate' => DatabaseCommand::class,
            'db:seed' => DatabaseCommand::class,
            'db:reset' => DatabaseCommand::class,
            'db:rollback' => DatabaseCommand::class,
            'db:status' => DatabaseCommand::class,
            'db:fresh' => DatabaseCommand::class,
            'db:backup' => DatabaseCommand::class,
            
            // Maintenance Commands
            'down' => DatabaseCommand::class,
            'up' => DatabaseCommand::class,

        ];
    }

    public function run(array $argv)
    {
        $command = $argv[1] ?? null;

        if (!$command || in_array($command, ['help', '--help', '-h'])) {
            $this->showHelp();
            return;
        }

        // Handle version command
        if (in_array($command, ['--version', '-v', 'version'])) {
            $this->showVersion();
            return;
        }


        // Handle list command
        if ($command === 'list') {
            $this->showList();
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->error("Unknown command: $command");
            $this->showSuggestions($command);
            $this->showHelp();
            return;
        }

        $commandClass = $this->commands[$command];
        
        try {
            $commandInstance = new $commandClass();
            
            if (method_exists($commandInstance, 'handle')) {
                $commandInstance->handle($argv);
            } else {
                $this->error("Command handler not found for: $command");
            }
        } catch (\Exception $e) {
            $this->error("Command failed: " . $e->getMessage());
            if (isset($argv[2]) && ($argv[2] === '--verbose' || $argv[2] === '-v')) {
                echo "\n\e[90mStack trace:\e[0m\n";
                echo $e->getTraceAsString() . "\n";
            }
        }
    }

    public function showHelp()
    {
        echo "\n";
        echo "  \e[1;33m🚀 FANY CLI Console v".NineVerse::FANY_CLI." \e[0m\n";
        echo "  \e[36mPowerful command-line interface for FANY Framework\e[0m\n";
        echo "\n";
        echo "\e[1;32mUSAGE:\e[0m\n";
        echo "  \e[36mphp fany <command> [options] [arguments]\e[0m\n";
        echo "\n";

        echo "\e[1;32m📝 MAKE COMMANDS:\e[0m\n";
        echo "  \e[36mmake:controller <n> [--model] [--resource]\e[0m   Create a new controller\n";
        echo "  \e[36mmake:model <n>\e[0m                               Create a new model\n";
        echo "  \e[36mmake:view <n>\e[0m                                Create a new view (.nixs.php)\n";
        echo "  \e[36mmake:component <n>\e[0m                           Create a new component\n";
        echo "  \e[36mmake:middleware <n>\e[0m                          Create a new middleware class\n";
        echo "  \e[36mmake:route <n> [flags]\e[0m                       Append route(s) to routes/web.php\n";
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
        echo "  \e[36mdb:reset\e[0m                                     Reset database and run migrations\n";
        echo "  \e[36mdb:status\e[0m                                    Show migration status\n";
        echo "  \e[36mdb:backup [--type=<t>] [--compress]\e[0m          Backup database to SQL\n";

        echo "\n\e[1;32m🔧 MAINTENANCE COMMANDS:\e[0m\n";
        echo "  \e[36mdown [--message=<msg>]\e[0m                       Put application in maintenance mode\n";
        echo "  \e[36mup\e[0m                                           Bring application out of maintenance\n";

        echo "\n\e[1;32m📋 ROUTE OPTIONS:\e[0m\n";
        echo "       \e[2m--G          Add GET route → index()\e[0m\n";
        echo "       \e[2m--P          Add POST route → store()\e[0m\n";
        echo "       \e[2m--U          Add PUT route → update()\e[0m\n";
        echo "       \e[2m--D          Add DELETE route → destroy()\e[0m\n";
        echo "       \e[2m--RESOURCE   Add all CRUD routes\e[0m\n";
        echo "       \e[2m--M=Name     Attach middleware\e[0m\n";
        
        echo "\n\e[1;32m🎨 NIXS TEMPLATE TYPES:\e[0m\n";
        echo "       \e[2mpage         Basic page template (default)\e[0m\n";
        echo "       \e[2mlayout       Layout template with sections\e[0m\n";
        echo "       \e[2mpartial      Partial template for inclusion\e[0m\n";
        echo "       \e[2mform         Form template with CSRF\e[0m\n";
        echo "       \e[2mcrud         Full CRUD templates\e[0m\n";
        echo "       \e[2mapi          API response templates\e[0m\n";
        
        echo "\n\e[1;32m🆘 HELP COMMANDS:\e[0m\n";
        echo "  \e[36mhelp, --help, -h\e[0m                             Show this help menu\n";
        echo "  \e[36m--version, -v\e[0m                                Show version information\n";
        echo "  \e[36mlist\e[0m                                         List all available commands\n";

        echo "\n\e[1;32m💡 EXAMPLES:\e[0m\n";
        echo "  \e[2mphp fany make:controller UserController --resource --model\e[0m\n";
        echo "  \e[2mphp fany make:nixs users.index --type=crud --bootstrap\e[0m\n";
        echo "  \e[2mphp fany make:component Button --props=text,color --slots\e[0m\n";
        echo "  \e[2mphp fany make:migration create_users_table --table=users\e[0m\n";
        echo "  \e[2mphp fany server --port=3000 --host=0.0.0.0\e[0m\n";
        echo "  \e[2mphp fany db:seed --class=UserSeeder\e[0m\n";

        echo "\n\e[1;32m🔗 MORE INFO:\e[0m\n";
        echo "  \e[2mDocumentation: ".NineVerse::BASE_DOMAIN."/docs\e[0m\n";
        echo "  \e[2mGitHub: ".NineVerse::REPO_URL."\e[0m\n";
        echo "  \e[2mSupport: ".NineVerse::BASE_DOMAIN."/fany\e[0m\n";
        echo "\n";
    }

    protected function showVersion()
    {
        echo "\n";
        echo "  \e[1;33m🚀 FANY CLI Console\e[0m\n";
        echo "  \e[36mVersion 2.0.0\e[0m\n";
        echo "  \e[2mPHP " . PHP_VERSION . "\e[0m\n";
        echo "\n";
        echo "  \e[1;32m📦 Components:\e[0m\n";
        echo "  \e[36m• Core Console System\e[0m       v2.0.0\n";
        echo "  \e[36m• Nixs Template Engine\e[0m     v1.5.0\n";
        echo "  \e[36m• Database Migrations\e[0m      v1.3.0\n";
        echo "  \e[36m• Component System\e[0m         v1.2.0\n";
        echo "\n";
        echo "  \e[2mCopyright (c) 2024 FANY Framework\e[0m\n";
        echo "\n";
    }

    protected function showList()
    {
        echo "\n\e[1;33m📋 Available Commands:\e[0m\n";
        
        $categories = [
            'Make Commands' => ['make:controller', 'make:model', 'make:view', 'make:middleware', 'make:route', 'make:migration', 'make:seeder', 'make:config', 'make:nixs', 'make:component'],
            'Server Commands' => ['server', 'serve'],
            'Database Commands' => ['db:migrate', 'db:rollback', 'db:seed', 'db:reset', 'db:status', 'db:fresh', 'db:backup'],
            'Maintenance Commands' => ['down', 'up'],
        ];

        // Calculate max length for dynamic separator
        $maxLen = 0;
        foreach ($categories as $category => $commands) {
            $maxLen = max($maxLen, strlen($category));
            foreach ($commands as $command) {
                $maxLen = max($maxLen, strlen($command));
            }
        }
        // Add padding for formatting
        $lineLength = $maxLen + 20;
        echo str_repeat('─', $lineLength) . "\n";

        foreach ($categories as $category => $commands) {
            echo "\n\e[1;32m{$category}:\e[0m\n";
            foreach ($commands as $command) {
                if (isset($this->commands[$command])) {
                    echo "  \e[36m{$command}\e[0m\n";
                }
            }
        }
        echo "\n";
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

    protected function success($message)
    {
        echo "\e[32m✅ $message\e[0m\n";
    }

    protected function error($message)
    {
        echo "\e[31m❌ $message\e[0m\n";
    }

    protected function warning($message)
    {
        echo "\e[33m⚠️  $message\e[0m\n";
    }

    protected function info($message)
    {
        echo "\e[36mℹ️  $message\e[0m\n";
    }

    protected function line($message = '')
    {
        echo $message . "\n";
    }

    protected function comment($message)
    {
        echo "\e[90m# $message\e[0m\n";
    }

    protected function question($message)
    {
        echo "\e[95m? $message\e[0m";
    }

    protected function table(array $headers, array $rows)
    {
        // Simple table display
        $widths = [];
        
        // Calculate column widths
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen($cell));
            }
        }
        
        // Display header
        echo "\n";
        foreach ($headers as $i => $header) {
            echo str_pad($header, $widths[$i] + 2);
        }
        echo "\n";
        
        // Display separator
        foreach ($widths as $width) {
            echo str_repeat('-', $width + 2);
        }
        echo "\n";
        
        // Display rows
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                echo str_pad($cell, $widths[$i] + 2);
            }
            echo "\n";
        }
        echo "\n";
    }

    protected function progressBar($current, $total, $message = '')
    {
        $percent = round(($current / $total) * 100);
        $bar = str_repeat('█', floor($percent / 5)) . str_repeat('░', 20 - floor($percent / 5));
        echo "\r\e[36m[$bar] $percent% ($current/$total) $message\e[0m";
        if ($current === $total) echo "\n";
    }

    protected function choice($question, array $choices, $default = null)
    {
        echo "\n\e[95m$question\e[0m\n";
        foreach ($choices as $key => $choice) {
            $marker = ($choice === $default) ? '*' : ' ';
            echo "  [$marker] $key) $choice\n";
        }
        echo "Choose an option" . ($default ? " (default: $default)" : "") . ": ";
        
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        if (empty($input) && $default !== null) {
            return $default;
        }
        
        return $choices[$input] ?? $input;
    }

    protected function confirm($question, $default = false)
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        echo "\e[95m$question\e[0m [$defaultText]: ";
        
        $handle = fopen("php://stdin", "r");
        $input = trim(strtolower(fgets($handle)));
        fclose($handle);
        
        if (empty($input)) {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    protected function ask($question, $default = null)
    {
        $defaultText = $default ? " (default: $default)" : "";
        echo "\e[95m$question\e[0m$defaultText: ";
        
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        return empty($input) ? $default : $input;
    }
}