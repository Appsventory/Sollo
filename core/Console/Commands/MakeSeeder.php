<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class MakeSeeder extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Seeder name is required.");
            $this->info("Usage: php fany make:seeder UserSeeder");
            return;
        }

        $this->createSeeder($name);
    }

    protected function createSeeder($name)
    {
        $className = $this->formatClassName($name);
        $path = "app/Database/Seeders/{$className}.php";

        if (file_exists($path)) {
            $this->warning("Seeder {$className} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStub('seeder');

        $replacements = [
            'SeederClass' => $className,
            'TableName' => $this->getTableName($className),
        ];

        $content = $this->replaceStubVariables($stub, $replacements);

        file_put_contents($path, $content);
        $this->success("Seeder created: {$path}");

        // Auto-register seeder in DatabaseSeeder.php
        $this->registerSeederInDatabase($className);
        $this->success("Seeder automatically registered in DatabaseSeeder.php");
    }

    protected function formatClassName($name)
    {
        $name = ucfirst($name);
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }
        return $name;
    }

    protected function getTableName($className)
    {
        $tableName = str_replace('Seeder', '', $className);
        $tableName = strtolower(preg_replace('/([A-Z])/', '_$1', $tableName));
        $tableName = ltrim($tableName, '_');

        // Simple pluralization
        if (str_ends_with($tableName, 'y')) {
            $tableName = substr($tableName, 0, -1) . 'ies';
        } elseif (str_ends_with($tableName, 's') || str_ends_with($tableName, 'sh') || str_ends_with($tableName, 'ch')) {
            $tableName .= 'es';
        } else {
            $tableName .= 's';
        }

        return $tableName;
    }

    /**
     * Automatically register seeder in DatabaseSeeder.php
     */
    protected function registerSeederInDatabase($seederClassName)
    {
        $databaseSeederPath = 'app/Database/Seeders/DatabaseSeeder.php';

        if (!file_exists($databaseSeederPath)) {
            $this->warning("DatabaseSeeder.php not found. Please register manually.");
            return;
        }

        $content = file_get_contents($databaseSeederPath);

        // Check if already registered (not in comment)
        if (preg_match('/^\s*' . preg_quote($seederClassName) . '::class/m', $content)) {
            $this->info("Seeder {$seederClassName} is already registered.");
            return;
        }

        // Check if $this->call([ is already uncommented
        $isCallActive = preg_match('/^\s*\$this->call\(\[\s*\n/m', $content) &&
            !preg_match('/^\s*\/\/\s*\$this->call\(\[\s*\n/m', $content);

        if ($isCallActive) {
            // Call is active, add before first commented seeder  
            $pattern = '/(\s+)([A-Za-z]+Seeder::class,)\n(\s*)\/\//';
            $replacement = "\$1\$2\n\$1{$seederClassName}::class,\n\$3//";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content, 1);
            }
        } else {
            // Call is commented, uncomment it and add first seeder
            $pattern = '/(\s+)\/\/\s*\$this->call\(\[\s*\n(\s+)\/\/\s+(UserSeeder::class,)/';
            $replacement = "\$1\$this->call([\n\$1    {$seederClassName}::class,\n\$2// \$3";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content, 1);
            }
        }

        file_put_contents($databaseSeederPath, $content);
    }
}
