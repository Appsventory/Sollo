<?php

namespace Core\Console\Commands;

use Core\Foundation\Routing\Router;
use Core\Providers\RouteServiceProvider;

/**
 * List registered routes
 */
class RouteListCommand extends BaseCommand
{
    public function handle(array $argv)
    {
        // Boot routes the same way the HTTP kernel does
        try {
            $provider = new RouteServiceProvider();
            $provider->boot();
        } catch (\Throwable $e) {
            $this->warning("Could not boot routes: " . $e->getMessage());
        }

        $routes = Router::getRoutes();

        if (empty($routes)) {
            $this->warning("No routes registered.");
            return;
        }

        $rows = [];
        foreach ($routes as $method => $map) {
            if (!is_array($map)) {
                continue;
            }
            foreach ($map as $uri => $entry) {
                $action = $this->formatAction($entry->action ?? null);
                $mw = [];
                if (isset($entry->middleware) && is_array($entry->middleware)) {
                    $mw = $entry->middleware;
                }
                $rows[] = [
                    strtoupper((string) $method),
                    $uri,
                    $action,
                    $mw ? implode(', ', $mw) : '-',
                ];
            }
        }

        usort($rows, fn($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        $this->info("Registered routes (" . count($rows) . "):");
        echo "\n";
        printf("  %-8s %-40s %-40s %s\n", "METHOD", "URI", "ACTION", "MIDDLEWARE");
        echo "  " . str_repeat('-', 120) . "\n";
        foreach ($rows as $row) {
            printf("  %-8s %-40s %-40s %s\n", $row[0], $row[1], $row[2], $row[3]);
        }
        echo "\n";
    }

    protected function formatAction($action): string
    {
        if ($action instanceof \Closure) {
            return 'Closure';
        }
        if (is_string($action)) {
            return $action;
        }
        if (is_array($action)) {
            return implode('@', array_map(fn($x) => is_object($x) ? get_class($x) : (string) $x, $action));
        }
        return '-';
    }
}
