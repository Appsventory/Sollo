<?php

namespace Core\Foundation;

use Core\Foundation\Http\HttpException;
use Core\Foundation\Http\Request;
use Core\Foundation\Http\Session;
use Core\Foundation\Routing\Router;
use Core\Foundation\Storage\Storage;
use Core\Framework\Velo\Nixs\NixsCompiler as Nixs;

abstract class Controller
{
    protected $middleware = [];
    protected $data = [];

    public function __construct()
    {
        $this->initializeController();
    }

    /**
     * Initialize controller - override in child classes
     * (a good place to register controller middleware).
     */
    protected function initializeController()
    {
        // Override in child controllers for initialization logic
    }

    /**
     * Render a view with data. Rendering errors propagate to the global
     * error handler (proper 500 page / JSON), they are not swallowed here.
     */
    public function view($view, $data = [])
    {
        Nixs::render($view, array_merge($this->data, $data));
    }

    /**
     * Return JSON response
     */
    public function json($data, $status = 200, $headers = [])
    {
        http_response_code($status);

        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate'
        ];

        foreach (array_merge($defaultHeaders, $headers) as $key => $value) {
            header("{$key}: {$value}");
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Return success JSON response
     */
    public function jsonSuccess($data = [], $message = 'Success', $status = 200)
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], $status);
    }

    /**
     * Return error JSON response
     */
    public function jsonError($message = 'Error', $data = [], $status = 400)
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $data,
            'timestamp' => date('c')
        ], $status);
    }

    /**
     * Redirect to URL
     */
    public function redirect($url, $status = 302)
    {
        // Header injection guard: a Location value can never contain a line break.
        $url = str_replace(["\r", "\n"], '', (string) $url);

        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect back to previous page (same-site referer only, "/" otherwise)
     */
    public function back()
    {
        $this->redirect(Request::previousUrl());
    }

    /**
     * Redirect with flash message
     */
    public function redirectWith($url, $type, $message, $status = 302)
    {
        Session::flash($type, $message);
        $this->redirect($url, $status);
    }

    /**
     * Validate request data and return the validated fields.
     *
     * On failure a ValidationException is thrown; the error handler turns it
     * into a redirect back with $errors/old input (web) or a 422 JSON (API/AJAX).
     */
    protected function validate(array $rules, array $messages = []): array
    {
        return Request::validate($rules, $messages);
    }

    /**
     * Check if request is AJAX
     */
    protected function isAjaxRequest()
    {
        return Request::ajax();
    }

    /**
     * Check if request is API
     */
    protected function isApiRequest()
    {
        return Request::isApi();
    }

    /**
     * Get request input with default
     */
    protected function input($key, $default = null)
    {
        return Request::input($key, $default);
    }

    /**
     * Get all request inputs
     */
    protected function inputs()
    {
        return Request::all();
    }

    /**
     * Get specific inputs only
     */
    protected function only(array $keys)
    {
        return Request::only($keys);
    }

    /**
     * Set data for view
     */
    protected function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Share data with all views
     */
    protected function share($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Nixs::share($k, $v);
            }
        } else {
            Nixs::share($key, $value);
        }

        return $this;
    }

    /**
     * Handle a file upload: validates the extension and stores the file under
     * public/storage/{$directory} with a random name. Returns the path relative
     * to public/storage (use storage($path) to build the URL).
     */
    protected function uploadFile($inputName, $directory = 'uploads', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'])
    {
        $file = Request::file($inputName);

        if (!$file || isset($file[0]) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, array_map('strtolower', $allowedTypes), true)) {
            throw new \RuntimeException('File type not allowed');
        }

        $stored = Storage::putFile($directory, $file['tmp_name'], bin2hex(random_bytes(16)) . '.' . $extension);

        if ($stored === null) {
            throw new \RuntimeException('Failed to save file');
        }

        return $stored;
    }

    /**
     * Register middleware for this controller:
     *
     *     $this->middleware('auth', ['only' => ['edit', 'update']]);
     *     $this->middleware('auth', ['except' => ['index']]);
     */
    protected function middleware($middleware, $options = [])
    {
        $this->middleware[] = [
            'middleware' => $middleware,
            'options' => $options
        ];

        return $this;
    }

    /**
     * Run the controller middleware for an action. Called by the Router
     * before the action executes.
     */
    public function runMiddleware(string $action): void
    {
        foreach ($this->middleware as $config) {
            $options = $config['options'];

            if (isset($options['only']) && !in_array($action, (array) $options['only'], true)) {
                continue;
            }

            if (isset($options['except']) && in_array($action, (array) $options['except'], true)) {
                continue;
            }

            Router::callMiddleware($config['middleware']);
        }
    }

    /**
     * Authorize an action. Denies by default: override can() in your
     * controller (or a base controller) to grant access.
     */
    protected function authorize($ability, $resource = null)
    {
        if (!$this->can($ability, $resource)) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
    }

    /**
     * Check if user can perform ability - override in child classes.
     * Fail-closed: without an override nobody is authorized.
     */
    protected function can($ability, $resource = null)
    {
        return false;
    }

    /**
     * Get authenticated user - override with your auth system
     */
    protected function user()
    {
        return Session::get('user');
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated()
    {
        return Session::has('user');
    }

    /**
     * Require authentication
     */
    protected function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            if (Request::wantsJson()) {
                throw new HttpException(401, 'Authentication required');
            }

            Session::flash('error', 'Please log in to access this page.');
            $this->redirect('/login');
        }
    }

    /**
     * Set cache headers
     */
    protected function cache($minutes = 60)
    {
        $expires = gmdate('D, d M Y H:i:s T', time() + ($minutes * 60));
        header("Cache-Control: public, max-age=" . ($minutes * 60));
        header("Expires: {$expires}");

        return $this;
    }

    /**
     * Disable cache
     */
    protected function noCache()
    {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        return $this;
    }

    /**
     * Set custom headers
     */
    protected function header($key, $value)
    {
        header("{$key}: {$value}");
        return $this;
    }

    /**
     * Calling a protected/unknown method must fail loudly.
     */
    public function __call($method, $parameters)
    {
        throw new \BadMethodCallException("Method {$method} does not exist in controller " . get_class($this));
    }
}
