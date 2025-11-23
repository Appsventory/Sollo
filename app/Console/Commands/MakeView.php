<?php

namespace App\Console\Commands;

class MakeView extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("View name is required.");
            $this->info("Usage: php fany make:view users.index");
            return;
        }

        $this->createView($name);
    }

    protected function createView($name)
    {
        $path = "app/Views/" . str_replace('.', '/', $name) . ".nixs.php";

        if (file_exists($path)) {
            $this->warning("View {$name} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStub('view');
        
        $replacements = [
            'ViewName' => $name. ".nixs.php",
            'FilePath' => $path,
            'ViewTitle' => ucfirst(str_replace(['.', '_', '-'], ' ', $name)),
        ];

        $content = $this->replaceStubVariables($stub, $replacements);
        
        file_put_contents($path, $content);
        $this->success("View created: {$path}");
    }
}