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

        if (!$this->isValidClassName($name)) {
            $this->error("Seeder name may only contain letters, digits and underscores.");
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
        if ($this->registerSeederInDatabase($className)) {
            $this->success("Seeder registered in DatabaseSeeder.php");
        } else {
            $this->warning("Could not update DatabaseSeeder.php automatically. Add {$className}::class to the \$this->call([...]) list yourself.");
        }
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
     * Register the seeder in DatabaseSeeder::run() -> $this->call([ ... ]).
     * Returns true when the seeder is (now) registered, false when the file
     * could not be updated and must be edited by hand.
     */
    protected function registerSeederInDatabase($seederClassName): bool
    {
        $path = 'app/Database/Seeders/DatabaseSeeder.php';

        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        // The active (non-commented) $this->call([ ... ]); list
        if (!preg_match('/^([ \t]*)\$this->call\(\[(.*?)(\n[ \t]*\]\);)/ms', $content, $m, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $indent = $m[1][0];
        $inner = $m[2][0];
        $innerStart = $m[2][1];

        $withoutComments = preg_replace('/^\s*\/\/.*$/m', '', $inner);
        if (preg_match('/\b' . preg_quote($seederClassName, '/') . '::class\b/', $withoutComments)) {
            $this->info("Seeder {$seederClassName} is already registered.");
            return true;
        }

        $entry = "\n{$indent}    {$seederClassName}::class,";
        $content = substr_replace($content, $inner . $entry, $innerStart, strlen($inner));

        return file_put_contents($path, $content) !== false;
    }
}
