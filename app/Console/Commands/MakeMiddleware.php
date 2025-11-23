<?php

namespace App\Console\Commands;

class MakeMiddleware extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Middleware name is required.");
            $this->info("Usage: php fany make:middleware AuthMiddleware");
            return;
        }

        $this->createMiddleware($name);
    }

    protected function createMiddleware($name)
    {
        $className = $this->formatClassName($name);
        $path = "app/Middleware/{$className}.php";

        if (file_exists($path)) {
            $this->warning("Middleware {$className} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStub('middleware');
        
        $replacements = [
            'MiddlewareClass' => $className,
        ];

        $content = $this->replaceStubVariables($stub, $replacements);
        
        file_put_contents($path, $content);
        $this->success("Middleware created: {$path}");
        
        $this->info("Don't forget to register your middleware in the router or middleware config!");
    }

    protected function formatClassName($name)
    {
        $name = ucfirst($name);
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }
        return $name;
    }
}