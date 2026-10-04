<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class ServiceCommand extends BaseCommand
{
    public function handle(array $argv)
    {
        $force = $this->hasOption($argv, 'force');
        $this->runService($argv[1] ?? '', $force);
    }

    protected function runService($name, $force = false)
    {
        $nameLower = strtolower($name);
        $downDir = dirname(__DIR__, 3) . '/storage/framework/down';
        $maintenancePath = $downDir . '/maintenance.html';

        if ($nameLower === 'down') {
            if (file_exists($maintenancePath) && !$force) {
                $this->warning("Application is already in maintenance mode.");
                $this->info("Use --force to overwrite the maintenance page.");
                return;
            }

            if (!is_dir($downDir)) {
                if (!@mkdir($downDir, 0755, true) && !is_dir($downDir)) {
                    $this->error("Failed to create directory: {$downDir}");
                    return;
                }
            }

            $stub = $this->getStub('maintenance');
            $content = $this->replaceStubVariables($stub, [
                'ConfigName' => 'maintenance',
                'ConfigTitle' => 'Maintenance',
            ]);

            if (file_put_contents($maintenancePath, $content) === false) {
                $this->error("Failed to write maintenance file: {$maintenancePath}");
                return;
            }

            $this->success("Application is now in maintenance mode.");
            $this->info("File: {$maintenancePath}");
            return;
        }

        if ($nameLower === 'up') {
            if (!file_exists($maintenancePath)) {
                $this->warning("Application is not in maintenance mode.");
                return;
            }

            if (@unlink($maintenancePath)) {
                $this->success("Application is now live.");
            } else {
                $this->error("Failed to remove maintenance file.");
            }
            return;
        }

        $this->error("Unknown service command: {$name}");
        $this->info("Usage: php fany down [--force]  |  php fany up");
    }
}
