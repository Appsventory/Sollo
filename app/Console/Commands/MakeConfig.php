<?php

namespace App\Console\Commands;

class MakeConfig extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Config name is required.");
            $this->info("Usage: php fany make:config mail");
            return;
        }

        $this->createConfig($name);
    }

    protected function createConfig($name)
    {
        $fileName = strtolower($name);
        $path = "config/{$fileName}.php";

        if (file_exists($path)) {
            $this->warning("Config {$fileName}.php already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStub('config');
        
        $replacements = [
            'ConfigName' => $fileName,
            'ConfigTitle' => ucfirst($fileName),
        ];

        $content = $this->replaceStubVariables($stub, $replacements);
        
        file_put_contents($path, $content);
        $this->success("Config created: {$path}");
        
        $this->info("You can now configure your {$fileName} settings in the created file.");
    }
}