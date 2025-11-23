<?php

namespace App\Console\Commands;

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
        $path = "app/database/seeders/{$className}.php";

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
        
        $this->info("Don't forget to register your seeder in DatabaseSeeder.php!");
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
}