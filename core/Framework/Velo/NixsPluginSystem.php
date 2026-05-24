<?php

namespace Core\Framework\Velo;

class NixsPluginSystem
{
    protected static array $config = [];
    protected static array $plugins = [];
    protected static array $widgets = [];
    protected static array $directives = [];
    protected static bool $initialized = false;

    /**
     * Initialize plugin system
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::loadConfig();
        self::loadPlugins();
        self::registerDirectives();
        self::$initialized = true;
    }

    /**
     * Load nixs.conf configuration file
     */
    protected static function loadConfig(): void
    {
        $configPath = root_path('nixs.conf');

        if (!file_exists($configPath)) {
            // Create default config if not exists
            self::$config = [
                'plugins' => [
                    'nixsWidgets' => true,
                    'nixsComponents' => true,
                ]
            ];
            return;
        }

        // Parse configuration file
        $content = file_get_contents($configPath);
        self::$config = self::parseConfig($content);
    }

    /**
     * Parse nixs.conf file format
     */
    protected static function parseConfig(string $content): array
    {
        $config = [];

        // Simple parser for nixs.conf format
        if (preg_match('/Plugin\s*\[\s*(.*?)\s*\]/is', $content, $matches)) {
            $pluginString = $matches[1];

            $entries = preg_split('/\s*,\s*/', trim($pluginString));
            foreach ($entries as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }

                if (preg_match('/^(\w+)\s*:\s*(true|false)$/i', $entry, $pluginMatches)) {
                    $config['plugins'][$pluginMatches[1]] = strcasecmp($pluginMatches[2], 'true') === 0;
                }
            }
        }

        return $config ?: ['plugins' => []];
    }

    /**
     * Load all enabled plugins
     */
    protected static function loadPlugins(): void
    {
        $plugins = self::$config['plugins'] ?? [];

        foreach ($plugins as $pluginName => $enabled) {
            if (!$enabled) {
                continue;
            }

            $pluginClass = 'App\\Plugins\\Nixs\\' . ucfirst($pluginName);

            if (class_exists($pluginClass)) {
                $plugin = new $pluginClass();

                if (method_exists($plugin, 'register')) {
                    $plugin->register();
                }

                self::$plugins[$pluginName] = $plugin;
            }
        }
    }

    /**
     * Register all plugin directives
     */
    protected static function registerDirectives(): void
    {
        foreach (self::$plugins as $pluginName => $plugin) {
            if (method_exists($plugin, 'directives')) {
                $directives = $plugin->directives();

                foreach ($directives as $name => $callback) {
                    self::$directives[$name] = $callback;
                }
            }

            if (method_exists($plugin, 'widgets')) {
                $widgets = $plugin->widgets();

                foreach ($widgets as $name => $widgetClass) {
                    self::$widgets[$name] = $widgetClass;
                }
            }
        }
    }

    /**
     * Check if plugin is enabled
     */
    public static function isEnabled(string $plugin): bool
    {
        return self::$config['plugins'][$plugin] ?? false;
    }

    /**
     * Get plugin instance
     */
    public static function getPlugin(string $name): ?object
    {
        return self::$plugins[$name] ?? null;
    }

    /**
     * Get all registered directives
     */
    public static function getDirectives(): array
    {
        return self::$directives;
    }

    /**
     * Get all registered widgets
     */
    public static function getWidgets(): array
    {
        return self::$widgets;
    }

    /**
     * Call directive compiler
     */
    public static function compileDirective(string $name, string $content): string
    {
        if (isset(self::$directives[$name])) {
            return call_user_func(self::$directives[$name], $content);
        }

        return $content;
    }

    /**
     * Get config value
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$config;

        foreach ($parts as $part) {
            $value = $value[$part] ?? $default;

            if ($value === $default) {
                break;
            }
        }

        return $value;
    }
}

// Helper function for root path
if (!function_exists('root_path')) {
    function root_path(string $path = ''): string
    {
        $root = dirname(dirname(__DIR__));
        return $root . ($path ? '/' . ltrim($path, '/') : '');
    }
}
