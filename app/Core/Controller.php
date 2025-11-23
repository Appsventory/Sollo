<?php

namespace App\Core;

use App\Console\Nixs;

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
     */
    protected function initializeController()
    {
        // Override in child controllers for initialization logic
    }

    /**
     * Render a view with data
     */
    public function view($view, $data = [])
    {
        // Merge controller data with passed data
        $data = array_merge($this->data, $data);
        
        try {
            Nixs::render($view, $data);
        } catch (\Exception $e) {
            $this->handleViewError($e, $view, $data);
        }
    }

    /**
     * Return JSON response
     */
    public function json($data, $status = 200, $headers = [])
    {
        http_response_code($status);
        
        // Set default JSON headers
        $defaultHeaders = [
            'Content-Type' => 'application/json',
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
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect back to previous page
     */
    public function back()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
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
     * Validate request data
     */
    protected function validate(array $rules, array $messages = [])
    {
        $validator = new Validator();
        $data = Request::all();
        
        $errors = $validator->validate($data, $rules, $messages);
        
        if (!empty($errors)) {
            if ($this->isAjaxRequest()) {
                $this->jsonError('Validation failed', $errors, 422);
            } else {
                Session::flash('errors', $errors);
                Session::flash('old', $data);
                $this->back();
            }
        }
        
        return array_intersect_key($data, $rules);
    }

    /**
     * Check if request is AJAX
     */
    protected function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request is API
     */
    protected function isApiRequest()
    {
        return str_starts_with(Request::uri(), '/api/') || 
               str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
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
     * Handle file upload
     */
    protected function uploadFile($inputName, $destinationPath = 'uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'])
    {
        $file = Request::file($inputName);
        
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('File upload failed');
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($extension), $allowedTypes)) {
            throw new \Exception('File type not allowed');
        }
        
        $filename = uniqid() . '.' . $extension;
        $destination = $destinationPath . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \Exception('Failed to save file');
        }
        
        return $destination;
    }

    /**
     * Apply middleware to controller
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
     * Execute controller middleware
     */
    public function executeMiddleware()
    {
        foreach ($this->middleware as $middlewareConfig) {
            $middleware = $middlewareConfig['middleware'];
            $options = $middlewareConfig['options'];
            
            // Check if middleware should be applied to current method
            if (isset($options['only']) && !in_array(debug_backtrace()[1]['function'], $options['only'])) {
                continue;
            }
            
            if (isset($options['except']) && in_array(debug_backtrace()[1]['function'], $options['except'])) {
                continue;
            }
            
            // Execute middleware
            $middlewareClass = "App\\Middleware\\{$middleware}";
            if (class_exists($middlewareClass)) {
                $instance = new $middlewareClass();
                if (method_exists($instance, 'handle')) {
                    $instance->handle();
                }
            }
        }
    }

    /**
     * Handle view rendering errors
     */
    protected function handleViewError(\Exception $e, $view, $data)
    {
        if ($this->isAjaxRequest() || $this->isApiRequest()) {
            $this->jsonError('View rendering failed', [
                'view' => $view,
                'error' => $e->getMessage()
            ], 500);
            return;
        }

        // ---- new UI with embedded SVG noise ----
        echo '
        <style>
            :root{--bg:#323745;--brand:#DC143C;--surface:#161d2f;--text:#e2e8f0;--mute:#94a3b8;--radius:16px}
            /* grainy noise from base-64 SVG */
            body{
                margin:0;padding:0;background:var(--bg);
                font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
                display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;
            }
            .view-error-overlay{position:fixed;inset:0;background:rgba(10,10,26,.55);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;z-index:9999}
            .view-error-card{
                background:var(--surface);border:1px solid rgba(255,255,255,.07);border-radius:var(--radius);
                max-width:600px;width:100%;padding:35px 30px;box-shadow:0 25px 45px rgba(0, 0, 0, 0.16);color:var(--text);
            }
            .view-error-head{display:flex;align-items:center;gap:12px;margin-bottom:22px}
            .view-error-icon{width:28px;height:28px;color:var(--brand);flex-shrink:0}
            .view-error-title{font-size:1.3rem;font-weight:600}
            .view-error-label{font-size:.8rem;color:var(--mute);margin-bottom:4px}
            .view-error-file{background:rgba(220,20,60,.1);border-left:3px solid var(--brand);padding:10px 12px;border-radius:6px;font-size:.8rem;word-break:break-all;margin-bottom:12px}
            .view-error-msg{background:rgba(255,255,255,.05);padding:10px 12px;border-radius:6px;font-size:.8rem;color:#fecaca}
            .view-error-close{
                margin-top:25px;background:var(--brand);color:#fff;border:none;padding:10px 22px;border-radius:9999px;font-weight:600;cursor:pointer;transition:background .2s;
            }
            .view-error-close:hover{background:#b91c3c}
            @media(max-width:480px){.view-error-card{padding:28px 20px}}
        </style>

        <div class="view-error-overlay">
            <div class="view-error-card">
                <div class="view-error-head">
                    <svg class="view-error-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <div class="view-error-title">View Rendering Failed</div>
                </div>
                <div class="view-error-label">Template file</div>
                <div class="view-error-file">' . htmlspecialchars($view) . '</div>
                <div class="view-error-label">Error message</div>
                <div class="view-error-msg">' . htmlspecialchars($e->getMessage()) . '</div>
            </div>
        </div>';
    }

    /**
     * Authorize action
     */
    protected function authorize($ability, $resource = null)
    {
        // Basic authorization - extend with your auth system
        if (!$this->can($ability, $resource)) {
            if ($this->isAjaxRequest()) {
                $this->jsonError('Unauthorized', [], 403);
            } else {
                $this->redirect('/unauthorized');
            }
        }
    }

    /**
     * Check if user can perform ability - override in child classes
     */
    protected function can($ability, $resource = null)
    {
        // Override in child controllers or create authorization service
        return true;
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
            if ($this->isAjaxRequest()) {
                $this->jsonError('Authentication required', [], 401);
            } else {
                Session::flash('error', 'Please log in to access this page.');
                $this->redirect('/login');
            }
        }
    }

    /**
     * Log activity - override with your logging system
     */
    protected function logActivity($action, $data = [])
    {
        // Implement activity logging
        error_log("Controller Activity: {$action} - " . json_encode($data));
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
     * Magic method to call undefined methods
     */
    public function __call($method, $parameters)
    {
        throw new \Exception("Method {$method} does not exist in controller " . get_class($this));
    }
}