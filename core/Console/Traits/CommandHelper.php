<?php

namespace Core\Console\Traits;

/**
 * CommandHelper Trait
 *
 * Helpers shared by the make:* commands: stub loading and placeholder replacement.
 */
trait CommandHelper
{
    /**
     * Ensure the directory of $path exists, create if not
     */
    protected function ensureDirectoryExists(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \Exception("Cannot create directory: $dir");
        }
    }

    /**
     * Load a stub template from core/Console/Commands/stubs/{name}.stub
     */
    protected function getStub(string $stubName): string
    {
        $stubPath = dirname(__DIR__) . "/Commands/stubs/{$stubName}.stub";

        if (!is_file($stubPath)) {
            throw new \Exception("Stub not found: {$stubName} ({$stubPath})");
        }

        return (string) file_get_contents($stubPath);
    }

    /**
     * Replace {{Placeholder}} values in stub content
     *
     *     $content = $this->replaceStubVariables($stub, ['ControllerClass' => 'UserController']);
     */
    protected function replaceStubVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }
        return $content;
    }

    /**
     * Validate a PHP class-like name (letters, digits, underscore; no path parts).
     */
    protected function isValidClassName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name);
    }

    /**
     * Validate a view/component/route name or relative path ("users.index", "admin/users").
     * Rejects empty segments, "..", absolute paths and unusual characters.
     */
    protected function isSafeTemplatePath(string $name): bool
    {
        if (!preg_match('#^[A-Za-z0-9_\-./]+$#', $name)) {
            return false;
        }
        if ($name[0] === '/' || $name[0] === '.' || str_contains($name, '..') || str_contains($name, '//')) {
            return false;
        }
        return true;
    }
}
