<?php

namespace Tiny;

use Exception;

class Router
{
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => []
    ];

    public function add(string $request_type, string $route, $resolve)
    {
        $this->routes[$request_type][$route] = $resolve;
    }

    public function resolve()
    {
        $request_method = $_SERVER['REQUEST_METHOD'];
        $request_path = $_SERVER['REQUEST_URI'];

        $route_info = $this->match_route($request_method, $request_path);

        if ($route_info['resolve'] !== null) {
            if (array_key_exists('params', $route_info)) {
                $this->handle_resolve($route_info['resolve'], $route_info['params']);
            } else {
                $this->handle_resolve($route_info['resolve'], []);
            }
        }
    }

    public function handle_resolve($resolve, $params)
    {
        if (is_callable($resolve)) {
            $this->handle_callback($resolve, $params);
        } else if (is_array($resolve)) {
            $this->handle_controller($resolve, $params);
        } else if (is_string($resolve)) {
            $this->handle_static($resolve);
        } else {
            throw new Exception("Invalid resolve format. It should be a callable, an array or a string.");
        }
    }

    public function handle_static(string $resolve)
    {
        if (file_exists($resolve)) {
            include $resolve;
        } else {
            throw new Exception("File $resolve not found");
        }
    }

    public function handle_callback(callable $resolve, array $params = [])
    {
        try {
            call_user_func_array($resolve, $params);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function handle_controller(array $resolve, array $params = [])
    {
        if (is_array($resolve) && count($resolve) >= 2) {
            $controllerClass = $resolve[0];
            $method = $resolve[1];

            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                if (method_exists($controller, $method)) {
                    $controller->{$method}(...$params);
                } else {
                    throw new Exception("Method $method not found in controller $controllerClass");
                }
            } else {
                throw new Exception("Controller class $controllerClass not found");
            }
        } else {
            throw new Exception("Invalid resolve format. It should be an array with a controller class and method name.");
        }
    }

    public function match_route(string $request_method, string $request_path)
    {
        if (array_key_exists($request_path, $this->routes[$request_method])) {
            return ['resolve' => $this->routes[$request_method][$request_path]];
        } else {
            $request_path_parts = explode('/', $request_path);
            $route_params = [];
            foreach ($this->routes[$request_method] as $route => $resolve) {
                $route_parts = explode('/', $route);

                if (count($request_path_parts) !== count($route_parts)) {
                    continue;
                }

                for ($i = 0; $i < count($request_path_parts); $i++) {
                    if (strpos($route_parts[$i], ':') !== false) {
                        $route_params[$route_parts[$i]] = $request_path_parts[$i];
                    } else if ($route_parts[$i] !== $request_path_parts[$i]) {
                        break;
                    }
                }
            }

            if (count($route_params) > 0) {
                return ['resolve' => $resolve, 'params' => $route_params];
            } else {
                return ['resolve' => null];
            }
        }
    }
}
