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

        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            $this->error("Config name may only contain letters, digits, underscores and dashes.");
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

        $content = $this->replaceStubVariables($this->getStub('config'), [
            'CONFIG_NAME' => strtoupper(str_replace('-', '_', $name)),
            'ConfigName' => ucfirst($name),
            'config_name' => $name,
        ]);

        file_put_contents($path, $content);
        $this->success("Config created: {$path}");
        $this->info("Read values with: config('{$name}.default')");
    }
}
