<?php

namespace Core\Console\Traits;

/**
 * CommandHelper Trait
 * 
 * Common helper methods for console commands.
 * Provides utilities for file operations, string manipulation, etc.
 */
trait CommandHelper
{
    /**
     * Ensure directory exists, create if not
     */
    protected function ensureDirectoryExists(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \Exception("Cannot create directory: $dir");
            }
        }
    }

    /**
     * Get stub template content
     */
    protected function getStub(string $stubName): string
    {
        $stubPath = __DIR__ . "/../../stubs/{$stubName}.stub";

        if (!file_exists($stubPath)) {
            $this->warning("Stub not found: {$stubName}");
            return '';
        }

        return file_get_contents($stubPath);
    }

    /**
     * Create file with content
     */
    protected function createFile(string $path, string $content): bool
    {
        $this->ensureDirectoryExists($path);

        if (file_exists($path)) {
            $this->error("File already exists: $path");
            return false;
        }

        if (!file_put_contents($path, $content)) {
            $this->error("Failed to create file: $path");
            return false;
        }

        $this->success("Created: $path");
        return true;
    }

    /**
     * Append content to file
     */
    protected function appendToFile(string $path, string $content): bool
    {
        if (!file_exists($path)) {
            $this->error("File does not exist: $path");
            return false;
        }

        if (!file_put_contents($path, $content, FILE_APPEND)) {
            $this->error("Failed to append to file: $path");
            return false;
        }

        $this->success("Appended to: $path");
        return true;
    }

    /**
     * Replace content in file
     */
    protected function replaceInFile(string $path, string $search, string $replace): bool
    {
        if (!file_exists($path)) {
            $this->error("File does not exist: $path");
            return false;
        }

        $content = file_get_contents($path);
        $newContent = str_replace($search, $replace, $content);

        if ($content === $newContent) {
            $this->warning("No changes made to: $path");
            return false;
        }

        if (!file_put_contents($path, $newContent)) {
            $this->error("Failed to update file: $path");
            return false;
        }

        $this->success("Updated: $path");
        return true;
    }

    /**
     * Replace stub variables
     * 
     * Example:
     *     $content = $this->replaceStubVariables($stub, [
     *         'ControllerClass' => 'UserController',
     *         'ModelClass' => 'User'
     *     ]);
     */
    protected function replaceStubVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        return $content;
    }

    /**
     * Get relative path from app root
     */
    protected function getRelativePath(string $path): string
    {
        $root = getcwd();
        return str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * Check if file exists
     */
    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Delete file
     */
    protected function deleteFile(string $path): bool
    {
        if (!file_exists($path)) {
            $this->error("File does not exist: $path");
            return false;
        }

        if (!unlink($path)) {
            $this->error("Failed to delete file: $path");
            return false;
        }

        $this->success("Deleted: $path");
        return true;
    }

    /**
     * Get files in directory
     */
    protected function getFiles(string $directory, string $extension = ''): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        foreach (scandir($directory) as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_file($path)) {
                if (empty($extension) || str_ends_with($file, $extension)) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Convert string to class name
     * 
     * Examples:
     *     'user_controller' -> 'UserController'
     *     'user' -> 'User'
     */
    protected function toClassName(string $name): string
    {
        return str_replace(
            ' ',
            '',
            ucwords(str_replace(['_', '-'], ' ', $name))
        );
    }

    /**
     * Convert class name to file name
     * 
     * Examples:
     *     'UserController' -> 'UserController.php'
     *     'User' -> 'User.php'
     */
    protected function toFileName(string $name, string $extension = '.php'): string
    {
        return $name . $extension;
    }

    /**
     * Convert to namespace
     * 
     * Examples:
     *     'app/Controllers' -> 'App\Controllers'
     *     'app/Models' -> 'App\Models'
     */
    protected function toNamespace(string $path): string
    {
        return str_replace(['/', '\\'], '\\', ucfirst(trim($path, '\/')));
    }

    /**
     * Get namespace from file path
     * 
     * Example:
     *     'app/Controllers/UserController.php' -> 'App\Controllers'
     */
    protected function getNamespaceFromPath(string $path): string
    {
        $dir = dirname($path);
        return $this->toNamespace($dir);
    }
}
