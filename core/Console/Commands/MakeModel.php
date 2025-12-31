<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class MakeModel extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Model name is required.");
            $this->info("Usage: php fany make:model User");
            return;
        }

        $this->createModel($name);
    }

    protected function createModel($name)
    {
        $className = $this->formatClassName($name);
        $path = "app/Models/{$className}.php";

        if (file_exists($path)) {
            $this->warning("Model {$className} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStub('model');

        $replacements = [
            'ModelClass' => $className,
            'TableName' => $this->getTableName($className),
        ];

        $content = $this->replaceStubVariables($stub, $replacements);

        file_put_contents($path, $content);
        $this->success("Model created: {$path}");
    }

    protected function formatClassName($name)
    {
        return ucfirst(str_replace('Controller', '', $name));
    }

    protected function getTableName($className)
    {
        // Convert PascalCase to snake_case and pluralize
        $tableName = strtolower(preg_replace('/([A-Z])/', '_$1', $className));
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
