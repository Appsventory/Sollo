<?php

namespace App\Console\Commands;

class MakeController extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Controller name is required.");
            $this->info("Usage: php fany make:controller UserController [--model] [--resource]");
            return;
        }

        $withModel = $this->hasOption($argv, 'model');
        $isResource = $this->hasOption($argv, 'resource');

        $this->createController($name, $withModel, $isResource);

        if ($withModel) {
            $modelCommand = new MakeModel();
            $modelCommand->handle(['', '', $name]);
        }
    }

    protected function createController($name, $withModel = false, $isResource = false)
    {
        $className = $this->formatClassName($name);
        $modelName = str_replace('Controller', '', $className);
        $path = "app/Controllers/{$className}.php";

        if (file_exists($path)) {
            $this->warning("Controller {$className} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $stub = $isResource ? $this->getStub('controller.resource') : $this->getStub('controller');
        
        $replacements = [
            'ControllerClass' => $className,
            'ModelClass' => $modelName,
            'UseModel' => $withModel ? "use App\\Models\\{$modelName};" : '',
            'ModelVariable' => strtolower($modelName),
        ];

        $content = $this->replaceStubVariables($stub, $replacements);
        
        file_put_contents($path, $content);
        $this->success("Controller created: {$path}");
    }

    protected function formatClassName($name)
    {
        $name = ucfirst($name);
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }
        return $name;
    }
}