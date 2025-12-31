<?php

namespace Core\Console\Commands;

class ServerCommand extends BaseCommand
{
    public function handle(array $argv)
    {
        $defaultPort = 8000;
        $port = $this->getOptionValue($argv, 'port') ?? $defaultPort;
        $host = $this->getOptionValue($argv, 'host') ?? 'localhost';

        if (!$this->isPortAvailable((int)$port)) {
            $this->error("Port {$port} is already in use. Please choose another port.");
            $this->info("Try: php fany server --port=" . ($port + 1));
            return;
        }

        $this->startServer($host, $port);
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

        // Change to public directory if it exists
        $publicDir = 'public';
        if (is_dir($publicDir)) {
            $command = "php -S {$address} -t {$publicDir}";
        } else {
            $this->warning("Public directory not found. Serving from current directory.");
            $command = "php -S {$address}";
        }

        // Execute the server command
        passthru($command);
    }

    protected function isPortAvailable($port)
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return false;
        }
        return true;
    }
}
