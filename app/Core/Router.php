<?php

namespace App\Core;
use App\Exceptions\Handler;
use App\Exceptions\FunctionErrorRenderer;
use App\Core\Session;

class Router
{
    protected static $routes = [];
    protected static $globalMiddleware = [];
    protected static $routeGroups = [];
    protected static $currentGroup = null;
    // Make namedRoutes accessible
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
        return $route; // return last route for chaining
    }

    public static function match($methods, $uri, $action)
    {
        $route = null;
        foreach ((array)$methods as $method) {
            $route = self::addRoute(strtoupper($method), $uri, $action);
        }
        return $route;
    }

    protected static function addRoute($method, $uri, $action)
    {
        // Apply current group prefix and middleware
        if (self::$currentGroup) {
            $uri = rtrim(self::$currentGroup['prefix'], '/') . '/' . ltrim($uri, '/');
            
            // Normalize multiple slashes
            $uri = preg_replace('#/+#', '/', $uri);
        }

        $route = new RouteEntry($method, $uri, $action);
        
        // Apply group middleware
        if (self::$currentGroup && isset(self::$currentGroup['middleware'])) {
            foreach ((array)self::$currentGroup['middleware'] as $middleware) {
                $route->middleware($middleware);
            }
        }

        self::$routes[$method][$uri] = $route;
        return $route;
    }

    public static function group($attributes, $callback)
    {
        $previousGroup = self::$currentGroup;
        
        // Merge with parent group if nested
        if ($previousGroup) {
            $attributes['prefix'] = rtrim($previousGroup['prefix'], '/') . '/' . ltrim($attributes['prefix'] ?? '', '/');
            $attributes['middleware'] = array_merge(
                (array)($previousGroup['middleware'] ?? []),
                (array)($attributes['middleware'] ?? [])
            );
        }
        
        self::$currentGroup = $attributes;
        
        call_user_func($callback);
        
        self::$currentGroup = $previousGroup;
    }

    public static function resource($uri, $controller, $options = [])
    {
        $only = $options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        
        $actions = array_diff($only, $except);
        
        $routes = [];
        
        if (in_array('index', $actions)) {
            $routes[] = self::get($uri, "{$controller}@index")->name("{$uri}.index");
        }
        
        if (in_array('create', $actions)) {
            $routes[] = self::get("{$uri}/create", "{$controller}@create")->name("{$uri}.create");
        }
        
        if (in_array('store', $actions)) {
            $routes[] = self::post($uri, "{$controller}@store")->name("{$uri}.store");
        }
        
        if (in_array('show', $actions)) {
            $routes[] = self::get("{$uri}/{id}", "{$controller}@show")->name("{$uri}.show");
        }
        
        if (in_array('edit', $actions)) {
            $routes[] = self::get("{$uri}/{id}/edit", "{$controller}@edit")->name("{$uri}.edit");
        }
        
        if (in_array('update', $actions)) {
            $routes[] = self::match(['PUT', 'PATCH'], "{$uri}/{id}", "{$controller}@update")->name("{$uri}.update");
        }
        
        if (in_array('destroy', $actions)) {
            $routes[] = self::delete("{$uri}/{id}", "{$controller}@destroy")->name("{$uri}.destroy");
        }
        
        return $routes;
    }

    public static function redirect($uri, $destination, $status = 302)
    {
        return self::get($uri, function() use ($destination, $status) {
            http_response_code($status);
            header("Location: {$destination}");
            exit;
        });
    }

    public static function view($uri, $view, $data = [])
    {
        return self::get($uri, function() use ($view, $data) {
            if (class_exists('\App\Console\Nixs')) {
                \App\Console\Nixs::render($view, $data);
            } else {
                echo "View system not found";
            }
        });
    }

    public static function dispatch()
    {
        Session::start();
        try {
            $uri = self::getCurrentUri();
            $method = self::getCurrentMethod();

            // Handle CORS preflight
            if ($method === 'OPTIONS') {
                self::handleCors();
                return;
            }

            // Execute global middleware
            self::executeGlobalMiddleware();

            // Find matching route
            $matchedRoute = self::findRoute($method, $uri);
            
            if ($matchedRoute) {
                self::executeRoute($matchedRoute);
                return;
            }

            // Route not found - redirect to error controller
            self::handleNotFound();
            
        } catch (\Exception $e) {
            // self::handleException($e);
            Handler::handleException($e);
        }
    }

    protected static function getCurrentUri()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return '/' . trim($uri, '/');
    }

    protected static function getCurrentMethod()
    {
        // Handle method override for HTML forms
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method'])) {
            return strtoupper($_POST['_method']);
        }
        
        // Handle method override via header
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
        
        return $_SERVER['REQUEST_METHOD'];
    }

    protected static function findRoute($method, $uri)
    {
        $routes = self::$routes[$method] ?? [];
        
        foreach ($routes as $routeUri => $routeEntry) {
            $pattern = self::buildRoutePattern($routeUri);
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                return [
                    'route' => $routeEntry,
                    'parameters' => $matches
                ];
            }
        }
        
        return null;
    }

    protected static function buildRoutePattern($routeUri)
    {
        // Convert {parameter} to named capture groups
        $pattern = preg_replace('/\{([^\/\}]+)\}/', '(?P<$1>[^\/]+)', $routeUri);
        
        // Handle optional parameters {parameter?}
        $pattern = preg_replace('/\{([^\/\}]+)\?\}/', '(?P<$1>[^\/]*)', $pattern);
        
        // Handle parameter constraints {id:regex}
        $pattern = preg_replace_callback('/\{([^:\/\}]+):([^\/\}]+)\}/', function($matches) {
            return "(?P<{$matches[1]}>{$matches[2]})";
        }, $pattern);
        
        return "#^" . $pattern . "$#";
    }

    protected static function executeGlobalMiddleware()
    {
        foreach (self::$globalMiddleware as $middleware) {
            self::executeMiddleware($middleware);
        }
    }

    protected static function executeRoute($matchedRoute)
    {
        $route = $matchedRoute['route'];
        $parameters = $matchedRoute['parameters'];

        $_GET['_route_params'] = $parameters;

        // Execute route middleware
        foreach ($route->middleware as $middleware) {
            self::executeMiddleware($middleware, $parameters);
        }

        // Execute controller action
        self::executeAction($route->action, $parameters);
    }

    protected static function executeMiddleware($middlewareEntry, $parameters = [])
    {
        $middlewareClass = "App\\Middleware\\";
        $methodName = 'handle';
        $params = [];

        if (str_contains($middlewareEntry, '@')) {
            // Format: Middleware@method:param1&param2
            [$className, $rest] = explode('@', $middlewareEntry, 2);
            $middlewareClass .= $className;

            if (str_contains($rest, ':')) {
                [$methodName, $paramString] = explode(':', $rest, 2);
                $params = explode('&', $paramString);
            } else {
                $methodName = $rest;
            }

            // Call static method
            if (!class_exists($middlewareClass)) {
                throw new \Exception("Middleware class {$middlewareClass} not found.");
            }
            if (!method_exists($middlewareClass, $methodName)) {
                throw new \Exception("Method {$methodName} not found in {$middlewareClass}.");
            }
            
            call_user_func_array([$middlewareClass, $methodName], array_merge($params, $parameters));
            
        } elseif (str_contains($middlewareEntry, '#')) {
            // Format: Middleware#param1&param2
            [$className, $paramString] = explode('#', $middlewareEntry, 2);
            $middlewareClass .= $className;
            $params = explode('&', $paramString);

            if (!class_exists($middlewareClass)) {
                throw new \Exception("Middleware class {$middlewareClass} not found.");
            }
            
            $instance = new $middlewareClass();
            if (!method_exists($instance, 'handle')) {
                throw new \Exception("handle() method not found in {$middlewareClass}.");
            }
            
            call_user_func_array([$instance, 'handle'], array_merge($params, $parameters));
            
        } else {
            // Default: Middleware → call handle()
            $middlewareClass .= $middlewareEntry;
            
            if (!class_exists($middlewareClass)) {
                throw new \Exception("Middleware class {$middlewareClass} not found.");
            }
            
            $instance = new $middlewareClass();
            if (!method_exists($instance, 'handle')) {
                throw new \Exception("handle() method not found in {$middlewareClass}.");
            }
            
            $instance->handle();
        }
    }

    protected static function executeAction($action, $parameters = [])
    {
        if (is_callable($action)) {
            $params = array_values($parameters);
        
            $result = call_user_func_array($action, $params); 
            
            // Handle return value
            if ($result !== null) {
                // If already output buffered, don't do anything
                if (headers_sent()) {
                    return;
                }
                
                // If array or object, convert to JSON
                if (is_array($result) || is_object($result)) {
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } 
                // If string or other scalar, echo directly
                else {
                    echo $result;
                }
            }
            return;
        }

        // Controller@method action
        if (is_array($action)) {
            [$controller, $methodName] = $action;
        } else {
            [$controller, $methodName] = explode('@', $action);
            $controller = "App\\Controllers\\{$controller}";
        }

        if (!class_exists($controller)) {
            throw new \Exception("Controller {$controller} not found.");
        }

        $controllerInstance = new $controller();
        
        if (!method_exists($controllerInstance, $methodName)) {
            throw new \Exception("Method {$methodName} not found in {$controller}.");
        }

        $params = array_values($parameters);
        call_user_func_array([$controllerInstance, $methodName], $params);
    }

    protected static function handleNotFound(): void
    {
        http_response_code(404);

        if (class_exists($ctrl = 'App\Controllers\ErrorController')) {
            $instance = new $ctrl;
            method_exists($ctrl, 'notFound') ? $instance->notFound() : $instance->index();
            return;
        }

        $view = dirname(__DIR__) . '/Views/errors/404.nixs.php';
        file_exists($view) ? require $view : FunctionErrorRenderer::error404();
    }

    protected static function handleCors()
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        
        http_response_code(200);
        exit;
    }

    // Helper methods for named routes
    public static function name($name, $parameters = [])
    {
        if (isset(self::$namedRoutes[$name])) {
            $uri = self::$namedRoutes[$name];
            
            // Replace parameters
            foreach ($parameters as $key => $value) {
                $uri = str_replace("{{$key}}", $value, $uri);
            }
            
            return $uri;
        }
        
        throw new \Exception("Named route '{$name}' not found.");
    }

    public static function url($name, $parameters = [])
    {
        $path = self::name($name, $parameters);
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $baseUrl . $path;
    }

    // Global middleware
    public static function middleware($middleware)
    {
        self::$globalMiddleware = array_merge(self::$globalMiddleware, (array)$middleware);
    }

    // Get all routes (for debugging)
    public static function getRoutes()
    {
        return self::$routes;
    }

    // Clear all routes (for testing)
    public static function clearRoutes()
    {
        self::$routes = [];
        self::$namedRoutes = [];
        self::$globalMiddleware = [];
    }
}