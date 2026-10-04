<?php

namespace Core\Foundation\Routing;

class RouteEntry
{
    public $method;
    public $uri;
    public $action;
    public $name;
    public $middleware = [];

    public function __construct($method, $uri, $action)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->action = $action;
    }

    public function middleware($name)
    {
        $this->middleware[] = $name;
        return $this;
    }

    public function name($name)
    {
        $this->name = $name;
        Router::$namedRoutes[$name] = $this->uri;
        return $this;
    }
}
