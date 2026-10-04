<?php

namespace Core\Foundation\Routing;

use Core\Foundation\Http\Cors;
use Core\Foundation\Http\HttpException;
use Core\Foundation\Http\Request;
use Core\Framework\Exceptions\ErrorHandler as Handler;

class Router
{
    protected static $routes = [];
    protected static $globalMiddleware = [];
    protected static $currentGroup = null;
    protected static array $currentParameters = [];

    /** name => uri template (written by RouteEntry::name()) */
    public static $namedRoutes = [];

    public static function get($uri, $action)
    {
        return self::addRoute('GET', $uri, $action);
    }

    public static function post($uri, $action)
    {
        return self::addRoute('POST', $uri, $action);
    }

    public static function put($uri, $action)
    {
        return self::addRoute('PUT', $uri, $action);
    }

    public static function delete($uri, $action)
    {
        return self::addRoute('DELETE', $uri, $action);
    }

    public static function patch($uri, $action)
    {
        return self::addRoute('PATCH', $uri, $action);
    }

    public static function options($uri, $action)
    {
        return self::addRoute('OPTIONS', $uri, $action);
    }

    public static function any($uri, $action)
    {
        $route = null;
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $route = self::addRoute($method, $uri, $action);
        }
        return $route; // last route, for chaining
    }

    public static function match($methods, $uri, $action)
    {
        $route = null;
        foreach ((array) $methods as $method) {
            $route = self::addRoute(strtoupper($method), $uri, $action);
        }
        return $route;
    }

    protected static function addRoute($method, $uri, $action)
    {
        $uri = '/' . trim((string) $uri, '/');

        if (self::$currentGroup) {
            $prefix = trim((string) (self::$currentGroup['prefix'] ?? ''), '/');
            if ($prefix !== '') {
                $uri = '/' . $prefix . ($uri === '/' ? '' : $uri);
            }
        }

        $route = new RouteEntry($method, $uri, $action);

        if (self::$currentGroup && isset(self::$currentGroup['middleware'])) {
            foreach ((array) self::$currentGroup['middleware'] as $middleware) {
                $route->middleware($middleware);
            }
        }

        self::$routes[$method][$uri] = $route;
        return $route;
    }

    public static function group($attributes, $callback)
    {
        $previousGroup = self::$currentGroup;

        if ($previousGroup) {
            $parentPrefix = $previousGroup['prefix'] ?? '';
            $currentPrefix = $attributes['prefix'] ?? '';

            if ($parentPrefix || $currentPrefix) {
                $attributes['prefix'] = preg_replace('#/+#', '/', rtrim($parentPrefix, '/') . '/' . ltrim($currentPrefix, '/'));
            }

            $attributes['middleware'] = array_merge(
                (array) ($previousGroup['middleware'] ?? []),
                (array) ($attributes['middleware'] ?? [])
            );
        }

        self::$currentGroup = $attributes;

        try {
            call_user_func($callback);
        } finally {
            self::$currentGroup = $previousGroup;
        }
    }

    public static function resource($uri, $controller, $options = [])
    {
        $only = $options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $actions = array_diff($only, $except);

        // "/blog/posts/{id}" -> "blog.posts"
        $base = str_replace('/', '.', trim(preg_replace('/\{[^}]+\}/', '', (string) $uri), '/'));
        $routes = [];

        if (in_array('index', $actions)) {
            $routes[] = self::get($uri, "{$controller}@index")->name("{$base}.index");
        }
        if (in_array('create', $actions)) {
            $routes[] = self::get("{$uri}/create", "{$controller}@create")->name("{$base}.create");
        }
        if (in_array('store', $actions)) {
            $routes[] = self::post($uri, "{$controller}@store")->name("{$base}.store");
        }
        if (in_array('show', $actions)) {
            $routes[] = self::get("{$uri}/{id}", "{$controller}@show")->name("{$base}.show");
        }
        if (in_array('edit', $actions)) {
            $routes[] = self::get("{$uri}/{id}/edit", "{$controller}@edit")->name("{$base}.edit");
        }
        if (in_array('update', $actions)) {
            $routes[] = self::match(['PUT', 'PATCH'], "{$uri}/{id}", "{$controller}@update")->name("{$base}.update");
        }
        if (in_array('destroy', $actions)) {
            $routes[] = self::delete("{$uri}/{id}", "{$controller}@destroy")->name("{$base}.destroy");
        }

        return $routes;
    }

    public static function redirect($uri, $destination, $status = 302)
    {
        return self::get($uri, function () use ($destination, $status) {
            $destination = str_replace(["\r", "\n"], '', (string) $destination);
            http_response_code($status);
            header("Location: {$destination}");
        });
    }

    public static function view($uri, $view, $data = [])
    {
        return self::get($uri, function () use ($view, $data) {
            \Core\Framework\Velo\Nixs\NixsCompiler::render($view, $data);
        });
    }

    // ------------------------------------------------------------------
    // Dispatching
    // ------------------------------------------------------------------

    public static function dispatch()
    {
        $headObLevel = null;

        try {
            $uri = self::getCurrentUri();
            $realMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

            // CORS preflight
            if ($realMethod === 'OPTIONS') {
                Cors::preflight();
                return;
            }

            $method = Request::method();

            self::executeGlobalMiddleware();

            $matched = self::findRoute($method, $uri);

            // HEAD is answered by the GET route; the body is discarded.
            if (!$matched && $method === 'HEAD') {
                $matched = self::findRoute('GET', $uri);
                if ($matched) {
                    ob_start(static fn() => '');
                    $headObLevel = ob_get_level();
                }
            }

            if ($matched) {
                self::executeRoute($matched);
            } else {
                self::handleNotFound($method, $uri);
            }

            if ($headObLevel !== null) {
                while (ob_get_level() >= $headObLevel) {
                    ob_end_clean();
                }
            }
        } catch (\Throwable $e) {
            if ($headObLevel !== null) {
                while (ob_get_level() >= $headObLevel) {
                    ob_end_clean();
                }
            }
            Handler::handleException($e);
        }
    }

    /**
     * Resolve a request without executing it (tests / tooling).
     * Returns ['route' => RouteEntry, 'parameters' => array] or null.
     */
    public static function resolve(string $method, string $uri): ?array
    {
        return self::findRoute(strtoupper($method), '/' . trim($uri, '/'));
    }

    public static function getRoutes()
    {
        return self::$routes;
    }

    /**
     * Route parameters of the route being executed ({id}, {slug}, ...).
     */
    public static function currentParameters(): array
    {
        return self::$currentParameters;
    }

    protected static function getCurrentUri()
    {
        return '/' . trim(preg_replace('#/+#', '/', Request::uri()), '/');
    }

    protected static function findRoute($method, $uri)
    {
        foreach (self::$routes[$method] ?? [] as $routeUri => $routeEntry) {
            if (preg_match(self::buildRoutePattern($routeUri), $uri, $matches)) {
                $parameters = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $parameters[$key] = rawurldecode($value);
                    }
                }

                return ['route' => $routeEntry, 'parameters' => $parameters];
            }
        }

        return null;
    }

    /**
     * Methods that have a route for this URI (used for 405 / Allow).
     */
    protected static function allowedMethods(string $uri): array
    {
        $allowed = [];

        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $routeUri => $entry) {
                if (preg_match(self::buildRoutePattern($routeUri), $uri)) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        if (in_array('GET', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        return array_values(array_unique($allowed));
    }

    protected static function buildRoutePattern($routeUri)
    {
        // 1. Constrained parameters {id:\d+}
        $pattern = preg_replace_callback('/\{([^:\/\}]+):([^\/\}]+)\}/', function ($m) {
            return "(?P<{$m[1]}>{$m[2]})";
        }, $routeUri);

        // 2. Optional parameters {param?}
        $pattern = preg_replace('/\/\{([^\/\}]+)\?\}/', '(?:/(?P<$1>[^\/]+))?', $pattern);

        // 3. Plain parameters {param}
        $pattern = preg_replace('/\{([^\/\}]+)\}/', '(?P<$1>[^\/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }

    protected static function executeGlobalMiddleware()
    {
        foreach (self::$globalMiddleware as $middleware) {
            self::callMiddleware($middleware);
        }
    }

    protected static function executeRoute($matchedRoute)
    {
        $route = $matchedRoute['route'];
        $parameters = $matchedRoute['parameters'];

        self::$currentParameters = $parameters;

        foreach ($route->middleware as $middleware) {
            self::callMiddleware($middleware, $parameters);
        }

        self::executeAction($route->action, $parameters);
    }

    // ------------------------------------------------------------------
    // Middleware
    // ------------------------------------------------------------------

    /**
     * Resolve a middleware name to a class. Accepts "auth", "Auth",
     * "AuthMiddleware" or a fully qualified class name.
     * An unknown middleware is an error: a typo must never silently
     * leave a route unprotected.
     */
    protected static function resolveMiddlewareClass(string $name): string
    {
        if (str_contains($name, '\\')) {
            if (class_exists($name)) {
                return $name;
            }
        } else {
            $base = ucfirst($name);
            foreach (["App\\Middleware\\{$base}", "App\\Middleware\\{$base}Middleware"] as $candidate) {
                if (class_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        throw new \RuntimeException(
            "Middleware '{$name}' not found. Create it with `php fany make:middleware " . ucfirst($name) . "` "
            . "(expected App\\Middleware\\" . ucfirst($name) . " or App\\Middleware\\" . ucfirst($name) . "Middleware)."
        );
    }

    /**
     * Run one middleware entry. Supported formats:
     *   Name                       -> handle()
     *   Name@method:param1&param2  -> method(param1, param2, ...routeParams)
     *   Name#param1&param2         -> handle(param1, param2, ...routeParams)
     */
    public static function callMiddleware($entry, array $routeParameters = []): void
    {
        $entry = (string) $entry;
        $method = 'handle';
        $params = null;

        if (str_contains($entry, '@')) {
            [$name, $rest] = explode('@', $entry, 2);
            if (str_contains($rest, ':')) {
                [$method, $paramString] = explode(':', $rest, 2);
                $params = explode('&', $paramString);
            } else {
                $method = $rest;
            }
        } elseif (str_contains($entry, '#')) {
            [$name, $paramString] = explode('#', $entry, 2);
            $params = explode('&', $paramString);
        } else {
            $name = $entry;
        }

        $class = self::resolveMiddlewareClass($name);

        if (!method_exists($class, $method)) {
            throw new \RuntimeException("Middleware {$class} has no {$method}() method.");
        }

        $args = $params === null ? [] : array_merge($params, array_values($routeParameters));
        $reflection = new \ReflectionMethod($class, $method);
        $target = $reflection->isStatic() ? null : new $class();

        $reflection->invokeArgs($target, $args);
    }

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    protected static function executeAction($action, $parameters = [])
    {
        if ($action instanceof \Closure || (is_string($action) && !str_contains($action, '@') && is_callable($action))) {
            $reflection = new \ReflectionFunction(\Closure::fromCallable($action));
            self::handleResult($reflection->invokeArgs(self::buildArguments($reflection, $parameters)));
            return;
        }

        if (is_array($action)) {
            [$controller, $methodName] = $action;
        } else {
            if (!str_contains((string) $action, '@')) {
                throw new \InvalidArgumentException('Invalid route action. Use a closure, "Controller@method" or [Controller::class, "method"].');
            }
            [$controller, $methodName] = explode('@', $action, 2);
            $controller = "App\\Controllers\\{$controller}";
        }

        if (!class_exists($controller)) {
            throw new \RuntimeException("Controller {$controller} not found.");
        }

        if (!method_exists($controller, $methodName)) {
            throw new \RuntimeException("Method {$methodName} not found in {$controller}.");
        }

        $reflection = new \ReflectionMethod($controller, $methodName);

        // Protected/private/magic methods can never be routed to.
        if (!$reflection->isPublic() || $reflection->isStatic() || str_starts_with($methodName, '__')) {
            throw new HttpException(404);
        }

        $instance = new $controller();

        if (method_exists($instance, 'runMiddleware')) {
            $instance->runMiddleware($methodName);
        }

        self::handleResult($reflection->invokeArgs($instance, self::buildArguments($reflection, $parameters)));
    }

    /**
     * Build named arguments for a closure/controller method from the route
     * parameters. Parameters are matched BY NAME (closures and controllers
     * behave the same), scalar types are cast, the Request type is injected,
     * and parameters with a default value may be omitted.
     */
    protected static function buildArguments(\ReflectionFunctionAbstract $reflection, array $parameters): array
    {
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $args[$name] = new Request();
                continue;
            }

            if (array_key_exists($name, $parameters) && $parameters[$name] !== '') {
                $args[$name] = self::castParameter($parameters[$name], $typeName);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                continue; // let PHP use the default
            }

            if ($type && $type->allowsNull()) {
                $args[$name] = null;
                continue;
            }

            if ($param->isVariadic()) {
                continue;
            }

            throw new \LogicException(
                "Route parameter \${$name} could not be resolved. Available parameters: "
                . ($parameters ? implode(', ', array_keys($parameters)) : '(none)')
                . '. Closure/controller argument names must match the {placeholders} in the URI.'
            );
        }

        return $args;
    }

    protected static function castParameter(string $value, ?string $type)
    {
        switch ($type) {
            case 'int':
                if (!preg_match('/^-?\d+$/', $value)) {
                    throw new HttpException(404);
                }
                return (int) $value;
            case 'float':
                if (!is_numeric($value)) {
                    throw new HttpException(404);
                }
                return (float) $value;
            case 'bool':
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    throw new HttpException(404);
                }
                return $bool;
            default:
                return $value;
        }
    }

    /**
     * Turn an action's return value into output: arrays/objects become JSON,
     * strings are echoed, null means the action already produced output.
     */
    protected static function handleResult($result): void
    {
        if ($result === null) {
            return;
        }

        if (is_array($result) || $result instanceof \JsonSerializable || $result instanceof \stdClass) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            return;
        }

        if (is_scalar($result) || (is_object($result) && method_exists($result, '__toString'))) {
            echo $result;
        }
    }

    protected static function handleNotFound(string $method, string $uri): void
    {
        $allowed = self::allowedMethods($uri);

        if ($allowed) {
            throw new HttpException(405, '', ['Allow' => implode(', ', $allowed)]);
        }

        if (class_exists($ctrl = 'App\Controllers\ErrorController')) {
            http_response_code(404);
            $instance = new $ctrl;
            method_exists($ctrl, 'notFound') ? $instance->notFound() : $instance->index();
            return;
        }

        throw new HttpException(404);
    }

    // ------------------------------------------------------------------
    // Named routes
    // ------------------------------------------------------------------

    /**
     * Build a path for a named route:  Router::name('posts.show', ['id' => 5]) => /posts/5
     */
    public static function name($name, $parameters = [])
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new \InvalidArgumentException("Named route '{$name}' not found.");
        }

        return preg_replace_callback(
            '#(/?)\{(\w+)(?::[^}]*)?(\?)?\}#',
            function ($m) use ($parameters, $name) {
                $key = $m[2];

                if (array_key_exists($key, $parameters) && $parameters[$key] !== null) {
                    return $m[1] . rawurlencode((string) $parameters[$key]);
                }

                if (($m[3] ?? '') === '?') {
                    return ''; // optional segment omitted
                }

                throw new \InvalidArgumentException("Missing parameter '{$key}' for route '{$name}'.");
            },
            self::$namedRoutes[$name]
        );
    }

    public static function url($name, $parameters = [])
    {
        $baseUrl = rtrim((string) \Core\Support\Env::raw('APP_URL', ''), '/');
        return $baseUrl . self::name($name, $parameters);
    }

    public static function hasNamedRoute($name)
    {
        return isset(self::$namedRoutes[$name]);
    }

    // ------------------------------------------------------------------
    // Global middleware / testing helpers
    // ------------------------------------------------------------------

    public static function middleware($middleware)
    {
        self::$globalMiddleware = array_merge(self::$globalMiddleware, (array) $middleware);
    }

    /** Clear all routes (for testing) */
    public static function clearRoutes()
    {
        self::$routes = [];
        self::$namedRoutes = [];
        self::$globalMiddleware = [];
        self::$currentParameters = [];
        self::$currentGroup = null;
    }
}
