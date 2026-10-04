<?php

namespace Core\Console\Commands;

class ServerCommand extends BaseCommand
{
    public function handle(array $argv)
    {
        if ($this->hasOption($argv, 'help') || in_array('-h', $argv, true) || in_array('--help', $argv, true)) {
            $this->showHelp();
            return;
        }

        $defaultPort = 8000;
        $port = $this->getOptionValue($argv, 'port') ?? $defaultPort;
        $host = $this->getOptionValue($argv, 'host') ?? 'localhost';

        if (!$this->isPortAvailable((int) $port)) {
            $this->error("Port {$port} is already in use. Please choose another port.");
            $this->info("Try: php fany server --port=" . ((int) $port + 1));
            return;
        }

        $this->startServer($host, (string) $port);
    }

    protected function showHelp(): void
    {
        echo "\n\e[1;33m🚀 server / serve - Development Server\e[0m\n";
        echo str_repeat('═', 50) . "\n\n";
        echo "\e[1;32mUSAGE:\e[0m\n";
        echo "  \e[36mphp fany server [--host=<host>] [--port=<port>]\e[0m\n";
        echo "  \e[36mphp fany serve  [--host=<host>] [--port=<port>]\e[0m\n\n";
        echo "\e[1;32mOPTIONS:\e[0m\n";
        echo "  \e[36m--host=<host>\e[0m   Host to bind (default: localhost)\n";
        echo "  \e[36m--port=<port>\e[0m   Port to listen (default: 8000)\n";
        echo "  \e[36m--help, -h\e[0m      Show this help\n\n";
        echo "\e[1;32mEXAMPLES:\e[0m\n";
        echo "  php fany server\n";
        echo "  php fany server --port=8080\n";
        echo "  php fany serve --host=127.0.0.1 --port=3000\n\n";
        echo "Uses public/ as document root. If public/router.php exists, it is used\n";
        echo "so paths with dots (e.g. /docs/3.x/...) are routed to the application.\n\n";
    }

    protected function startServer($host, $port)
    {
        $address = "{$host}:{$port}";

        echo "\n\e[1;32m🚀 FANY Development Server\e[0m\n";
        echo "\e[36m📡 Server running at:\e[0m http://{$address}\n";
        echo "\e[36m📂 Document root:\e[0m " . getcwd() . "/public\n";
        echo "\e[36m⏰ Started at:\e[0m " . date('Y-m-d H:i:s') . "\n";
        echo "\e[33m🔁 Press Ctrl+C to stop the server\e[0m\n";
        echo str_repeat('─', 50) . "\n";

        $publicDir = 'public';
        $router = $publicDir . '/router.php';

        if (is_dir($publicDir)) {
            if (is_file($router)) {
                $command = "php -S {$address} -t {$publicDir} {$router}";
            } else {
                $command = "php -S {$address} -t {$publicDir}";
            }
        } else {
            $this->warning("Public directory not found. Serving from current directory.");
            $command = "php -S {$address}";
        }

        passthru($command);
    }

    protected function isPortAvailable($port)
    {
        $connection = @fsockopen('127.0.0.1', (int) $port, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return false;
        }
        return true;
    }
}
