<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;
use Core\Console\Commands\MakeModel;
use Core\Console\Commands\MakeRoute;

class MakeController extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Controller name is required.");
            $this->info("Usage: php fany make:controller UserController [--model] [--resource] [--G] [--P] [--U] [--D] [--M=middleware]");
            return;
        }

        $withModel = $this->hasOption($argv, 'model');
        $isResource = $this->hasOption($argv, 'resource');
        $middleware = $this->getOptionValue($argv, 'M');

        // Check for individual route options
        $hasRouteOptions = $this->hasOption($argv, 'G') ||
            $this->hasOption($argv, 'P') ||
            $this->hasOption($argv, 'U') ||
            $this->hasOption($argv, 'D') ||
            $isResource;

        $this->createController($name, $withModel, $isResource);

        if ($withModel) {
            $modelCommand = new MakeModel();
            $modelCommand->handle(['', '', $name]);
        }

        // Auto-create routes if any route options are set
        if ($hasRouteOptions) {
            $this->createControllerRoutes($name, $argv, $middleware);
        }
    }

    /**
     * Create resource routes for the controller
     */
    protected function createControllerRoutes($name, $argv, $middleware = null)
    {
        $route = strtolower(str_replace('Controller', '', $name));
        $controller = $this->formatClassName($name);

        $routeCommand = new MakeRoute();

        // Build argv for MakeRoute command - include all route-related flags
        $routeArgv = ['', '', $route];

        // Add route method flags
        if ($this->hasOption($argv, 'G')) {
            $routeArgv[] = '--G';
        }
        if ($this->hasOption($argv, 'P')) {
            $routeArgv[] = '--P';
        }
        if ($this->hasOption($argv, 'U')) {
            $routeArgv[] = '--U';
        }
        if ($this->hasOption($argv, 'D')) {
            $routeArgv[] = '--D';
        }
        if ($this->hasOption($argv, 'resource')) {
            $routeArgv[] = '--RESOURCE';
        }

        if ($middleware) {
            $routeArgv[] = "--M={$middleware}";
        }

        $routeCommand->handle($routeArgv);
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
