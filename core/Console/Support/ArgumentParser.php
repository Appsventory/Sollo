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
    public function getOptions(): array
    {
        return $this->options;
    }

    // Get argument by index with default value
    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    // Get all arguments
    public function getArguments(): array
    {
        return $this->arguments;
    }

    // Check if argument exists
    public function hasArgument(int $index): bool
    {
        return isset($this->arguments[$index]);
    }

    // Get parsed command
    public function getCommand(): string
    {
        return $this->command;
    }

    // Check if option equals a specific value
    public function optionEquals(string $key, mixed $value): bool
    {
        return ($this->options[$key] ?? null) === $value;
    }

    //  Get multiple options at once
    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getOption($key);
        }
        return $result;
    }

    /**
     * Get option with validation
     * 
     * @throws \InvalidArgumentException
     */
    public function getRequired(string $key): mixed
    {
        if (!$this->hasOption($key)) {
            throw new \InvalidArgumentException("Required option --$key not provided");
        }
        return $this->getOption($key);
    }

    // Get first available option from list
    public function getOptionFrom(array $alternatives, mixed $default = null): mixed
    {
        foreach ($alternatives as $key) {
            if ($this->hasOption($key)) {
                return $this->getOption($key);
            }
        }
        return $default;
    }

    // Check if any of the options exist
    public function hasAnyOption(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->hasOption($key)) {
                return true;
            }
        }
        return false;
    }

    // Get raw argv array
    public function getRaw(): array
    {
        return $this->argv;
    }

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
