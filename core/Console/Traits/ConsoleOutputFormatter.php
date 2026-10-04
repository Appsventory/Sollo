<?php

namespace Core\Console\Traits;

// ConsoleOutputFormatter Trait
trait ConsoleOutputFormatter
{
    /** Set when error() was printed; becomes a non-zero exit code. */
    protected bool $failed = false;

    public function hasFailed(): bool
    {
        return $this->failed;
    }

    /**
     * Print success message (green with ✅ icon)
     */
    protected function success(string $message): void
    {
        echo "\e[32m✅ $message\e[0m\n";
    }

    /**
     * Print error message (red with ❌ icon)
     */
    protected function error(string $message): void
    {
        $this->failed = true;
        echo "\e[31m❌ $message\e[0m\n";
    }

    /**
     * Print warning message (yellow with ⚠️ icon)
     */
    protected function warning(string $message): void
    {
        echo "\e[33m⚠️  $message\e[0m\n";
    }

    /**
     * Print info message (cyan with ℹ️ icon)
     */
    protected function info(string $message): void
    {
        echo "\e[36mℹ️  $message\e[0m\n";
    }

    /**
     * Print blank line
     */
    protected function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    /**
     * Print comment (gray with # prefix)
     */
    protected function comment(string $message): void
    {
        echo "\e[90m# $message\e[0m\n";
    }

    /**
     * Print question prompt (magenta with ? prefix)
     */
    protected function question(string $message): void
    {
        echo "\e[95m? $message\e[0m";
    }

    /**
     * Print section header (yellow bold)
     */
    protected function section(string $title): void
    {
        echo "\n\e[1;33m=== $title ===\e[0m\n";
    }

    /**
     * Print colored text
     */
    protected function text(string $message, string $color = 'white'): void
    {
        $colors = [
            'white' => '37',
            'black' => '30',
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'gray' => '90',
        ];

        $colorCode = $colors[$color] ?? '37';
        echo "\e[{$colorCode}m$message\e[0m\n";
    }

    /**
     * Display table with headers and rows
     */
    protected function table(array $headers, array $rows): void
    {
        if (empty($headers) || empty($rows)) {
            return;
        }

        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string)$cell));
            }
        }

        // Display header
        echo "\n";
        foreach ($headers as $i => $header) {
            echo str_pad($header, $widths[$i] + 2);
        }
        echo "\n";

        // Display separator
        foreach ($widths as $width) {
            echo str_repeat('-', $width + 2);
        }
        echo "\n";

        // Display rows
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                echo str_pad((string)$cell, $widths[$i] + 2);
            }
            echo "\n";
        }
        echo "\n";
    }

    /**
     * Ask user for input (with optional default)
     */
    protected function ask(string $question, ?string $default = null): string
    {
        $defaultText = $default ? " (default: $default)" : '';
        echo "\e[95m$question\e[0m$defaultText: ";

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        return empty($input) ? ($default ?? '') : $input;
    }

    /**
     * Ask for confirmation (yes/no)
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        echo "\e[95m$question\e[0m [$defaultText]: ";

        $handle = fopen("php://stdin", "r");
        $input = trim(strtolower(fgets($handle)));
        fclose($handle);

        if (empty($input)) {
            return $default;
        }

        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    /**
     * Ask user to choose from options
     */
    protected function choice(string $question, array $choices, ?string $default = null): string
    {
        echo "\n\e[95m$question\e[0m\n";

        foreach ($choices as $key => $choice) {
            $marker = ($choice === $default) ? '*' : ' ';
            echo "  [$marker] $key) $choice\n";
        }

        $defaultText = $default ? " (default: $default)" : "";
        echo "Choose an option$defaultText: ";

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        if (empty($input) && $default !== null) {
            return $default;
        }

        return $choices[$input] ?? $input;
    }

    /**
     * Create a box around text
     */
    protected function box(string $message, string $style = 'info'): void
    {
        $lines = explode("\n", $message);
        $maxLength = max(array_map('strlen', $lines));

        $colors = [
            'info' => '36',
            'success' => '32',
            'error' => '31',
            'warning' => '33',
        ];

        $color = $colors[$style] ?? '36';

        echo "\e[{$color}m";
        echo '┌' . str_repeat('─', $maxLength + 2) . '┐' . "\n";

        foreach ($lines as $line) {
            echo '│ ' . str_pad($line, $maxLength) . ' │' . "\n";
        }

        echo '└' . str_repeat('─', $maxLength + 2) . '┘' . "\e[0m\n";
    }

    /**
     * Clear terminal screen
     */
    protected function clear(): void
    {
        system('clear') ?: system('cls');
    }

    /**
     * Beep/bell sound
     */
    protected function bell(): void
    {
        echo "\007";
    }

}
