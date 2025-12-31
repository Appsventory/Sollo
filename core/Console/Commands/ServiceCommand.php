<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class ServiceCommand extends BaseCommand
{
    public function handle(array $argv)
    {
        $force = $this->hasOption($argv, 'force');
        $this->runService($argv[1], $force);
    }

    protected function runService($name, $force = false)
    {
        $nameLower = strtolower($name);
        $maintenancePath = dirname(__DIR__, 3) . '/storage/framework/down/maintenance.html';

        if ($nameLower === 'down') {
            if (file_exists($maintenancePath)) {
                if (!$force) {
                    $this->warning("Application is already in maintenance mode.");
                    return;
                }
            }

            $stub = $this->getStub('maintenance');
            $replacements = [
                'ConfigName' => 'maintenance',
                'ConfigTitle' => ucfirst('maintenance'),
            ];
            $content = $this->replaceStubVariables($stub, $replacements);
            file_put_contents($maintenancePath, $content);
            $this->success("Application is now in maintenance mode." . ($force ? " (forced)" : ""));
        } elseif ($nameLower === 'up') {
            if (!file_exists($maintenancePath)) {
                $this->warning("Application is not in maintenance mode.");
                return;
            }

            unlink($maintenancePath);
            $this->success("Application is now live.");
        } else {
            $this->error("Invalid action. Use 'Up' or 'Down'.");
        }
    }
}
