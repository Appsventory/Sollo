<?php

namespace App\Console\Commands;

class MakeRoute extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;
        $options = array_slice($argv, 3);

        if (!$name) {
            $this->error("Route name is required.");
            $this->info("Usage: php fany make:route users --G --P --M=AuthMiddleware");
            return;
        }

        $this->createRoute($name, $options);
    }

    protected function createRoute($name, $options)
    {
        $route = strtolower($name);
        $controller = ucfirst($name) . 'Controller';
        $middleware = '';

        // Handle middleware
        foreach ($options as $opt) {
            if (str_starts_with($opt, '--M=')) {
                $middlewareName = trim(substr($opt, 4));
                $middleware = "->middleware('$middlewareName')";
                break;
            }
        }

        $lines = [];

        foreach ($options as $opt) {
            switch ($opt) {
                case '--G':
                    $lines[] = "Router::get('/$route', '$controller@index')$middleware;";
                    break;
                case '--P':
                    $lines[] = "Router::post('/$route', '$controller@store')$middleware;";
                    break;
                case '--U':
                    $lines[] = "Router::put('/$route/{id}', '$controller@update')$middleware;";
                    break;
                case '--D':
                    $lines[] = "Router::delete('/$route/{id}', '$controller@destroy')$middleware;";
                    break;
                case '--RESOURCE':
                    $this->createResourceRoutes($route, $controller, $middleware, $lines);
                    break;
            }
        }

        if (empty($lines)) {
            $this->warning("No method specified (--G, --P, --U, --D, --RESOURCE). Nothing was added.");
            $this->info("Available options:");
            $this->info("  --G         GET route (index)");
            $this->info("  --P         POST route (store)");
            $this->info("  --U         PUT route (update)");
            $this->info("  --D         DELETE route (destroy)");
            $this->info("  --RESOURCE  All CRUD routes");
            $this->info("  --M=Name    Add middleware");
            return;
        }

        $this->appendToRouteFile($lines);
    }

    protected function createResourceRoutes($route, $controller, $middleware, &$lines)
    {
        $lines[] = "// Resource routes for $route";
        $lines[] = "Router::get('/$route', '$controller@index')$middleware;";
        $lines[] = "Router::get('/$route/create', '$controller@create')$middleware;";
        $lines[] = "Router::post('/$route', '$controller@store')$middleware;";
        $lines[] = "Router::get('/$route/{id}', '$controller@show')$middleware;";
        $lines[] = "Router::get('/$route/{id}/edit', '$controller@edit')$middleware;";
        $lines[] = "Router::put('/$route/{id}', '$controller@update')$middleware;";
        $lines[] = "Router::delete('/$route/{id}', '$controller@destroy')$middleware;";
    }

    protected function appendToRouteFile($lines)
    {
        $routeFile = 'app/routes/web.php';
        
        if (!file_exists($routeFile)) {
            $this->ensureDirectoryExists($routeFile);
            file_put_contents($routeFile, "<?php\n\n// Web Routes\n");
        }

        $output = "\n// Generated routes - " . date('Y-m-d H:i:s') . "\n";
        $output .= implode("\n", $lines) . "\n";
        
        file_put_contents($routeFile, $output, FILE_APPEND);
        
        $this->success("Route(s) added to {$routeFile}:");
        foreach ($lines as $line) {
            if (!str_starts_with($line, '//')) {
                echo "  \e[36m$line\e[0m\n";
            }
        }
    }
}