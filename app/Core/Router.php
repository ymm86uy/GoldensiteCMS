<?php

namespace App\Core;

class Router
{
    private static ?Router $instance = null;
    private array $routes = [];

    public function __construct()
    {
        self::$instance = $this;
    }

    public static function getInstance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add(string $method, string $path, $handler, string $action = 'index'): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'handler' => $handler,
            'action'  => $action
        ];
    }

    public function get(string $path, $handler, string $action = 'index'): void
    {
        $this->add('GET', $path, $handler, $action);
    }

    public function post(string $path, $handler, string $action = 'index'): void
    {
        $this->add('POST', $path, $handler, $action);
    }

    public function hasRouteRoot(string $slug): bool
    {
        foreach ($this->routes as $route) {
            if ($route['path'] === '/'.$slug || str_starts_with($route['path'], '/'.$slug.'/')) return true;
        }
        return false;
    }

    public static function __callStatic($name, $arguments)
    {
        $router = self::getInstance();
        if (in_array(strtolower($name), ['get', 'post', 'add'])) {
            return call_user_func_array([$router, strtolower($name)], $arguments);
        }
    }

    public function dispatch(string $method, string $uri): void
    {
        // Inspección del Firewall previa a cualquier enrutamiento
        \App\Middleware\WafMiddleware::inspect();

        $parsedUrl = parse_url($uri, PHP_URL_PATH);
        $parsedUrl = AdminPath::normalize((string)$parsedUrl);
        if (ProductEdition::current()==='commercial' && ($parsedUrl==='/admin/owner' || str_starts_with($parsedUrl,'/admin/owner/')
            || $parsedUrl==='/admin/deploy-manager' || str_starts_with($parsedUrl,'/admin/deploy-manager/'))) {
            http_response_code(404);
            return;
        }

        if ($parsedUrl === '/admin' || str_starts_with((string) $parsedUrl, '/admin/')) {
            Auth::requireAdmin();
            IdentityAccess::enforce((string)$parsedUrl,$method);
            header('Cache-Control: no-store, private');
        }
        if (!in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            if (!(strtoupper($method)==='POST' && $parsedUrl==='/login/saml/acs')
                && !\App\Controllers\ScimController::isWriteRoute($method,(string)$parsedUrl)) {
                RequestGuard::csrf();
            }
            if (str_starts_with((string)$parsedUrl, '/admin/') && FrontendEngine::isModuleActive('activity-log')) \App\Controllers\Admin\ActivityLogController::log('admin_request', 'Solicitud ' . strtoupper($method) . ' ' . substr((string)$parsedUrl, 0, 200));
        }
        ModuleGate::admin((string)$parsedUrl);
        ModuleGate::publicRoute((string)$parsedUrl);
        \App\Middleware\MaintenanceMiddleware::handle();
        PublicRuntime::start(strtoupper($method), (string)$parsedUrl);

        foreach ($this->routes as $route) {
            $parameters = [];
            $pattern = preg_replace('/\\\\\{[A-Za-z_][A-Za-z0-9_]*\\\\\}/', '([^/]+)', preg_quote($route['path'], '~'));
            $matches = [];
            if ($route['method'] === strtoupper($method) && preg_match('~^' . $pattern . '$~D', (string) $parsedUrl, $matches)) {
                $parameters = array_map('rawurldecode', array_slice($matches, 1));
                $handler = $route['handler'];
                $action  = $route['action'];

                if (is_array($handler) && count($handler) === 2) {
                    $controllerName = $handler[0];
                    $action         = $handler[1];

                    if (class_exists($controllerName)) {
                        $controller = new $controllerName();
                        if (method_exists($controller, $action)) {
                            $controller->$action(...$parameters);
                            return;
                        }
                    }
                } elseif (is_string($handler)) {
                    if (strpos($handler, '@') !== false) {
                        list($controllerName, $action) = explode('@', $handler, 2);
                    } else {
                        $controllerName = $handler;
                    }

                    if (!str_contains($controllerName, '\\')) {
                        $controllerName = 'App\\Controllers\\Admin\\' . $controllerName;
                    }

                    if (class_exists($controllerName)) {
                        $controller = new $controllerName();
                        if (method_exists($controller, $action)) {
                            $controller->$action(...$parameters);
                            return;
                        }
                    }
                } elseif (is_callable($handler)) {
                    call_user_func_array($handler, $parameters);
                    return;
                }
            }
        }

        ErrorPage::notFound();
    }
}
