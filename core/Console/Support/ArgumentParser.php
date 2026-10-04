<?php

namespace Core\Console\Support;

// ArgumentParser Class
class ArgumentParser
{
    protected array $argv;
    protected array $options = [];
    protected array $arguments = [];
    protected string $command = '';

    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->parse();
    }

    // Parse argv into command, options, and arguments
    protected function parse(): void
    {
        $items = array_slice($this->argv, 1);

        if (empty($items)) {
            return;
        }

        if (!str_starts_with($items[0], '-')) {
            $this->command = array_shift($items);
        }

        // Parse remaining items
        foreach ($items as $item) {
            if (str_starts_with($item, '--')) {
                $this->parseLongOption($item);
            } elseif (str_starts_with($item, '-')) {
                $this->parseShortOption($item);
            } else {
                $this->arguments[] = $item;
            }
        }
    }

    // Parse long option (--option or --option=value)
    protected function parseLongOption(string $item): void
    {
        $item = substr($item, 2); // Remove --

        if (str_contains($item, '=')) {
            [$key, $value] = explode('=', $item, 2);
            $this->options[trim($key)] = trim($value, '\'" ');
        } else {
            $this->options[$item] = true;
        }
    }

    // Parse short option (-o or -abc or -o=value)
    protected function parseShortOption(string $item): void
    {
        $chars = substr($item, 1);

        if (str_contains($chars, '=')) {
            [$key, $value] = explode('=', $chars, 2);
            $this->options[$key] = trim($value, '\'" ');
        } else {
            foreach (str_split($chars) as $char) {
                $this->options[$char] = true;
            }
        }
    }

    // Get option by key with default value
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    // Check if option exists
    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]) && $this->options[$key] !== false;
    }

    // Get all options
    // Get argument by index with default value
    // Get all arguments
    public function getArguments(): array
    {
        return $this->arguments;
    }

    // Check if argument exists
    // Get parsed command
    // Check if option equals a specific value
    //  Get multiple options at once
    // Get first available option from list
    // Check if any of the options exist
    // Get raw argv array
    // Get all parsed data
    public function all(): array
    {
        return [
            'command' => $this->command,
            'options' => $this->options,
            'arguments' => $this->arguments,
        ];
    }

    // Debug representation
    public function debug(): string
    {
        return json_encode($this->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
