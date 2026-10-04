<?php

namespace Core\Console\Commands;

use Core\Framework\Velo\Nixs\NixsCompiler;

/**
 * Clear application / view caches
 */
class CacheCommand extends BaseCommand
{
    public function handle(array $argv)
    {
        $command = $argv[1] ?? '';

        switch ($command) {
            case 'cache:clear':
            case 'nixs:clear':
                $this->clearCaches();
                break;
            default:
                $this->info("Usage: php fany cache:clear");
                $this->info("       php fany nixs:clear");
                break;
        }
    }

    protected function clearCaches(): void
    {
        $this->info("Clearing caches...");

        // Nixs compiled views
        try {
            NixsCompiler::clearCache();
            $this->success("Nixs template cache cleared.");
        } catch (\Throwable $e) {
            $this->warning("Nixs cache: " . $e->getMessage());
        }

        // storage/framework/views
        $viewsDir = dirname(__DIR__, 3) . '/storage/framework/views';
        $removed = 0;
        if (is_dir($viewsDir)) {
            foreach (glob($viewsDir . '/nixs_*.php') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $removed++;
                }
            }
        }
        $this->success("Removed {$removed} compiled view file(s).");

        // storage/cache
        $cacheDir = dirname(__DIR__, 3) . '/storage/cache';
        if (is_dir($cacheDir)) {
            $n = 0;
            foreach (glob($cacheDir . '/*') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $n++;
                }
            }
            $this->success("Cleared storage/cache ({$n} file(s)).");
        }

        $this->success("Done.");
    }
}
