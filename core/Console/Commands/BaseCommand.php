<?php

namespace Core\Console\Commands;

use Core\Console\Traits\ConsoleOutputFormatter;
use Core\Console\Traits\CommandHelper;
use Core\Console\Support\ArgumentParser;

/**
 * BaseCommand Abstract Class
 * 
 * Base class for all console commands.
 * Uses traits for output formatting and file operations.
 * 
 * Provides:
 * - ConsoleOutputFormatter: success(), error(), warning(), info(), etc.
 * - CommandHelper: file operations, string utilities
 * - ArgumentParser integration
 */
abstract class BaseCommand
{
    use ConsoleOutputFormatter;
    use CommandHelper;

    abstract public function handle(array $argv);

    /**
     * Create ArgumentParser from argv
     */
    protected function parseArguments(array $argv): ArgumentParser
    {
        return new ArgumentParser($argv);
    }

    /**
     * Get option value from argv (backward compatibility)
     * 
     * @deprecated Use parseArguments() and ArgumentParser instead
     */
    protected function getOptionValue(array $argv, $key)
    {
        $parser = new ArgumentParser($argv);
        return $parser->getOption($key);
    }

    /**
     * Check if option exists (backward compatibility)
     * 
     * @deprecated Use parseArguments() and ArgumentParser instead
     */
    protected function hasOption(array $argv, $key)
    {
        $parser = new ArgumentParser($argv);
        return $parser->hasOption($key);
    }
}
