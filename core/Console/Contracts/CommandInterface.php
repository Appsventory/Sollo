<?php

namespace Core\Console\Contracts;

use Core\Console\Support\ArgumentParser;

// CommandInterface Contract
interface CommandInterface
{
    /**
     * Get command signature
     * 
     * Format: command:name {argument} {--option=default}
     * 
     * Examples:
     *     'make:controller {name}'
     *     'make:controller {name} {--resource} {--model}'
     *     'serve {--port=8000} {--host=localhost}'
     */
    public function signature(): string;

    /**
     * Get command description
     */
    public function description(): string;

    /**
     * Handle the command execution
     */
    public function handle(ArgumentParser $args): void;

    /**
     * Get detailed help text (optional)
     */
    public function getHelp(): ?string;
}
