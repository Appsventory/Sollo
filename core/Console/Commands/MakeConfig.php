<?php

namespace Core\Console\Commands;

/**
 * Create a config file under config/
 */
class MakeConfig extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Config name is required.");
            $this->info("Usage: php fany make:config <name>");
            $this->info("Example: php fany make:config database");
            return;
        }

        $this->createConfig($name);
    }

    protected function createConfig(string $name): void
    {
        $name = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $name));
        if ($name === '') {
            $this->error("Invalid config name.");
            return;
        }

        $path = "config/{$name}.php";

        if (file_exists($path)) {
            $this->warning("Config {$name} already exists at {$path}");
            return;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStub('config');
        if ($stub === '' || $stub === false) {
            $stub = $this->getConfigStubContent();
        }

        $content = $this->replaceStubVariables($stub, [
            'CONFIG_NAME' => strtoupper($name),
            'ConfigName' => ucfirst($name),
            'config_name' => $name,
        ]);

        // Fallback if stub uses different placeholders
        $content = str_replace(
            ['{{CONFIG_NAME}}', '{{ConfigName}}', '{{config_name}}'],
            [strtoupper($name), ucfirst($name), $name],
            $content
        );

        file_put_contents($path, $content);
        $this->success("Config created: {$path}");
        $this->info("Load with: require config/{$name}.php  or your config helper if available.");
    }
}
