<?php

namespace Core\Console\Commands;


use Core\Console\Commands\BaseCommand;

class MakeMigration extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Migration name is required.");
            $this->info("Usage: php fany make:migration create_users_table [--table=users]");
            return;
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $this->error("Migration name may only contain letters, digits and underscores.");
            return;
        }

        $tableName = $this->getOptionValue($argv, 'table');
        $this->createMigration($name, $tableName);
    }

    protected function createMigration($name, $tableName = null)
    {
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$name}.php";
        $className = $this->formatClassName($name);
        $path = "app/Database/migrations/{$fileName}";

        if (!$tableName) {
            $tableName = $this->extractTableName($name);
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStub('migration');

        $replacements = [
            'MigrationClass' => $className,
            'TableName' => $tableName,
            'Timestamp' => $timestamp,
        ];

        $content = $this->replaceStubVariables($stub, $replacements);

        file_put_contents($path, $content);
        $this->success("Migration created: {$path}");
    }

    protected function formatClassName($name)
    {
        // Convert snake_case to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    protected function extractTableName($name)
    {
        // Try to extract table name from migration name
        if (preg_match('/create_(.+)_table/', $name, $matches)) {
            return $matches[1];
        }

        if (preg_match('/add_(.+)_to_(.+)/', $name, $matches)) {
            return $matches[2];
        }

        if (preg_match('/drop_(.+)_table/', $name, $matches)) {
            return $matches[1];
        }

        // Default fallback
        return strtolower(str_replace('_', '', $name));
    }
}
