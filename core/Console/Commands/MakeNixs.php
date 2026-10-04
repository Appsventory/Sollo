<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class MakeNixs extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;
        $type = $this->getOptionValue($argv, 'type') ?? 'page';

        if (!$name) {
            $this->error("Nixs template name is required.");
            $this->showUsage();
            return;
        }

        if (!$this->isSafeTemplatePath($name)) {
            $this->error("Invalid template name (use letters, digits, dot or slash separators; no double dots).");
            return;
        }

        $this->createNixsTemplate($name, $type, $argv);
    }

    protected function showUsage()
    {
        echo "\n\e[1;33mUsage:\e[0m\n";
        echo "  \e[36mphp fany make:nixs <name> [--type=<type>] [options]\e[0m\n\n";
        echo "\e[1;32mTemplate Types:\e[0m\n";
        echo "  \e[36mpage\e[0m     - Basic page template (default)\n";
        echo "  \e[36mlayout\e[0m   - Layout template with sections\n";
        echo "  \e[36mpartial\e[0m  - Partial template for inclusion\n";
        echo "  \e[36mform\e[0m     - Form template with CSRF\n";
        echo "  \e[36mcrud\e[0m     - Full CRUD templates (index, create, edit, show)\n";
        echo "  \e[36mapi\e[0m      - API response templates\n\n";
        echo "\e[1;32mOptions:\e[0m\n";
        echo "  \e[36m--layout=<name>\e[0m    Extend specific layout\n";
        echo "  \e[36m--section=<name>\e[0m   Create with specific section\n";
        echo "  \e[36m--bootstrap\e[0m        Include Bootstrap CSS\n";
        echo "  \e[36m--tailwind\e[0m         Include Tailwind CSS\n";
        echo "  \e[36m--alpine\e[0m           Include Alpine.js\n\n";
        echo "\e[1;32mExamples:\e[0m\n";
        echo "  \e[2mphp fany make:nixs users.index --type=crud\e[0m\n";
        echo "  \e[2mphp fany make:nixs layouts.app --type=layout --bootstrap\e[0m\n";
        echo "  \e[2mphp fany make:nixs partials.header --type=partial\e[0m\n";
        echo "  \e[2mphp fany make:nixs users.form --type=form --layout=layouts.app\e[0m\n";
    }

    protected function createNixsTemplate($name, $type, $argv)
    {
        switch ($type) {
            case 'crud':
                $this->createCrudTemplates($name, $argv);
                break;
            case 'layout':
                $this->createLayoutTemplate($name, $argv);
                break;
            case 'partial':
                $this->createPartialTemplate($name, $argv);
                break;
            case 'form':
                $this->createFormTemplate($name, $argv);
                break;
            case 'api':
                $this->createApiTemplate($name, $argv);
                break;
            case 'page':
            default:
                $this->createPageTemplate($name, $argv);
                break;
        }
    }

    protected function createPageTemplate($name, $argv)
    {
        $path = "resources/Views/" . str_replace('.', '/', $name) . ".nixs.php";

        if (file_exists($path)) {
            $this->warning("Template {$name} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $layout = $this->getOptionValue($argv, 'layout');
        $section = $this->getOptionValue($argv, 'section') ?? 'content';
        $framework = $this->detectFramework($argv);

        $replacements = [
            'TemplateName' => $name,
            'TemplateTitle' => ucfirst(str_replace(['.', '_', '-'], ' ', $name)),
            'Layout' => $layout,
            'Section' => $section,
            'Framework' => $framework,
            'HasLayout' => $layout ? 'true' : 'false'
        ];

        $content = $this->generatePageContent($replacements);

        file_put_contents($path, $content);
        $this->success("Nixs template created: {$path}");
    }

    protected function createLayoutTemplate($name, $argv)
    {
        $path = "resources/Views/" . str_replace('.', '/', $name) . ".nixs.php";

        if (file_exists($path)) {
            $this->warning("Layout {$name} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $framework = $this->detectFramework($argv);

        $replacements = [
            'LayoutName' => $name,
            'LayoutTitle' => ucfirst(str_replace(['.', '_', '-'], ' ', $name)),
            'Framework' => $framework
        ];

        $content = $this->generateLayoutContent($replacements);

        file_put_contents($path, $content);
        $this->success("Nixs layout created: {$path}");
    }

    protected function createPartialTemplate($name, $argv)
    {
        $path = "resources/Views/" . str_replace('.', '/', $name) . ".nixs.php";

        if (file_exists($path)) {
            $this->warning("Partial {$name} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $replacements = [
            'PartialName' => $name,
            'PartialTitle' => ucfirst(str_replace(['.', '_', '-'], ' ', $name))
        ];

        $content = $this->generatePartialContent($replacements);

        file_put_contents($path, $content);
        $this->success("Nixs partial created: {$path}");
    }

    protected function createFormTemplate($name, $argv)
    {
        $path = "resources/Views/" . str_replace('.', '/', $name) . ".nixs.php";

        if (file_exists($path)) {
            $this->warning("Form template {$name} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $layout = $this->getOptionValue($argv, 'layout');
        $framework = $this->detectFramework($argv);

        $replacements = [
            'FormName' => $name,
            'FormTitle' => ucfirst(str_replace(['.', '_', '-'], ' ', $name)),
            'Layout' => $layout,
            'Framework' => $framework,
            'HasLayout' => $layout ? 'true' : 'false'
        ];

        $content = $this->generateFormContent($replacements);

        file_put_contents($path, $content);
        $this->success("Nixs form template created: {$path}");
    }

    protected function createCrudTemplates($name, $argv)
    {
        $basePath = "resources/Views/" . str_replace('.', '/', $name);
        $templates = ['index', 'create', 'edit', 'show'];

        foreach ($templates as $template) {
            $templatePath = "{$basePath}/{$template}.nixs.php";

            if (file_exists($templatePath)) {
                $this->warning("Template {$name}.{$template} already exists.");
                continue;
            }

            $this->ensureDirectoryExists($templatePath);

            $layout = $this->getOptionValue($argv, 'layout');
            $framework = $this->detectFramework($argv);

            $replacements = [
                'ResourceName' => $name,
                'TemplateName' => $template,
                'TemplateTitle' => ucfirst($name) . ' - ' . ucfirst($template),
                'Layout' => $layout,
                'Framework' => $framework,
                'HasLayout' => $layout ? 'true' : 'false'
            ];

            $content = $this->generateCrudContent($template, $replacements);

            file_put_contents($templatePath, $content);
            $this->success("Created: {$templatePath}");
        }
    }

    protected function createApiTemplate($name, $argv)
    {
        $path = "resources/Views/" . str_replace('.', '/', $name) . ".nixs.php";

        if (file_exists($path)) {
            $this->warning("API template {$name} already exists.");
            return;
        }

        $this->ensureDirectoryExists($path);

        $replacements = [
            'ApiName' => $name,
            'ApiTitle' => ucfirst(str_replace(['.', '_', '-'], ' ', $name))
        ];

        $content = $this->generateApiContent($replacements);

        file_put_contents($path, $content);
        $this->success("Nixs API template created: {$path}");
    }

    protected function detectFramework($argv)
    {
        if ($this->hasOption($argv, 'bootstrap')) {
            return 'bootstrap';
        } elseif ($this->hasOption($argv, 'tailwind')) {
            return 'tailwind';
        }
        return 'vanilla';
    }

    protected function generatePageContent($replacements)
    {
        extract($replacements);

        if ($HasLayout === 'true') {
            return "@extends('{$Layout}')

@section('{$Section}')
<div class=\"page-header\">
    <h1>{$TemplateTitle}</h1>
</div>

<div class=\"page-content\">
    <!-- Your {$TemplateName} content here -->
    <p>This is the {$TemplateName} page template.</p>
    
    @if(\$data ?? false)
        <div class=\"data-display\">
            <h3>Data:</h3>
            <pre>{{ print_r(\$data, true) }}</pre>
        </div>
    @endif
</div>
@endsection
";
        }

        $frameworkLinks = $this->getFrameworkLinks($Framework);

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$TemplateTitle} - FANY Framework</title>
    {$frameworkLinks}
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class=\"container\">
        <h1>{$TemplateTitle}</h1>
        
        <!-- Your {$TemplateName} content here -->
        <p>This is the {$TemplateName} page template.</p>
        
        @if(\$data ?? false)
            <div class=\"data-display\">
                <h3>Data:</h3>
                <pre>{{ print_r(\$data, true) }}</pre>
            </div>
        @endif
    </div>
</body>
</html>";
    }

    protected function generateLayoutContent($replacements)
    {
        extract($replacements);
        $frameworkLinks = $this->getFrameworkLinks($Framework);

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>@yield('title', '{$LayoutTitle} - FANY Framework')</title>
    {$frameworkLinks}
    @yield('styles')
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; background: #f8f9fa; }
        .navbar { background: #007bff; color: white; padding: 1rem 0; margin-bottom: 20px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .main-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .footer { margin-top: 40px; padding: 20px 0; background: #6c757d; color: white; text-align: center; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class=\"navbar\">
        <div class=\"container\">
            <h1>@yield('app-name', 'FANY App')</h1>
            <div>
                @yield('navigation')
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class=\"main-content\">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class=\"footer\">
        <div class=\"container\">
            @yield('footer', '&copy; 2024 FANY Framework')
        </div>
    </footer>

    @yield('scripts')
</body>
</html>";
    }

    protected function generatePartialContent($replacements)
    {
        extract($replacements);

        return "<!-- Partial: {$PartialName} -->
<div class=\"partial-{$PartialName}\">
    <h3>{$PartialTitle}</h3>
    
    <!-- Your {$PartialName} partial content here -->
    <p>This is a reusable {$PartialName} partial.</p>
    
    @if(\$items ?? false)
        <ul>
        @foreach(\$items as \$item)
            <li>{{ \$item }}</li>
        @endforeach
        </ul>
    @endif
</div>
<!-- End Partial: {$PartialName} -->";
    }

    protected function generateFormContent($replacements)
    {
        extract($replacements);

        $formContent = "<form method=\"POST\" action=\"{{ \$action ?? '' }}\" class=\"form-{$FormName}\">
    @csrf
    
    <div class=\"form-group\">
        <label for=\"name\">Name:</label>
        <input type=\"text\" id=\"name\" name=\"name\" value=\"{{ \$data['name'] ?? '' }}\" required>
    </div>
    
    <div class=\"form-group\">
        <label for=\"email\">Email:</label>
        <input type=\"email\" id=\"email\" name=\"email\" value=\"{{ \$data['email'] ?? '' }}\" required>
    </div>
    
    <div class=\"form-group\">
        <label for=\"description\">Description:</label>
        <textarea id=\"description\" name=\"description\">{{ \$data['description'] ?? '' }}</textarea>
    </div>
    
    <div class=\"form-actions\">
        <button type=\"submit\" class=\"btn btn-primary\">{{ \$submitText ?? 'Submit' }}</button>
        <a href=\"{{ \$cancelUrl ?? '#' }}\" class=\"btn btn-secondary\">Cancel</a>
    </div>
</form>";

        if ($HasLayout === 'true') {
            return "@extends('{$Layout}')

@section('content')
<div class=\"form-container\">
    <h1>{$FormTitle}</h1>
    {$formContent}
</div>
@endsection
";
        }

        $frameworkLinks = $this->getFrameworkLinks($Framework);

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$FormTitle} - FANY Framework</title>
    {$frameworkLinks}
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .form-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .form-actions { margin-top: 30px; }
        .btn { padding: 10px 20px; margin-right: 10px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class=\"form-container\">
        <h1>{$FormTitle}</h1>
        {$formContent}
    </div>
</body>
</html>";
    }

    protected function generateCrudContent($template, $replacements)
    {
        extract($replacements);

        $content = '';

        switch ($template) {
            case 'index':
                $content = $this->getCrudIndexContent($replacements);
                break;
            case 'create':
                $content = $this->getCrudCreateContent($replacements);
                break;
            case 'edit':
                $content = $this->getCrudEditContent($replacements);
                break;
            case 'show':
                $content = $this->getCrudShowContent($replacements);
                break;
        }

        return $content;
    }

    protected function getCrudIndexContent($replacements)
    {
        extract($replacements);

        if ($HasLayout === 'true') {
            return "@extends('{$Layout}')

@section('content')
<div class=\"index-header\">
    <h1>{$TemplateTitle}</h1>
    <a href=\"/{$ResourceName}/create\" class=\"btn btn-primary\">Add New</a>
</div>

<div class=\"table-container\">
    @if(\$items ?? false)
        <table class=\"table\">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach(\$items as \$item)
                <tr>
                    <td>{{ \$item['id'] ?? '' }}</td>
                    <td>{{ \$item['name'] ?? '' }}</td>
                    <td>{{ \$item['created_at'] ?? '' }}</td>
                    <td>
                        <a href=\"/{$ResourceName}/{{ \$item['id'] }}\">View</a> |
                        <a href=\"/{$ResourceName}/{{ \$item['id'] }}/edit\">Edit</a> |
                        <form method=\"DELETE\" action=\"/{$ResourceName}/{{ \$item['id'] }}\" style=\"display: inline;\">
                            @csrf
                            <button type=\"submit\" onclick=\"return confirm('Are you sure?')\">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>No {$ResourceName} found.</p>
    @endif
</div>
@endsection
";
        }

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$TemplateTitle} - FANY Framework</title>
</head>
<body>
    <div class=\"container\">
        <div class=\"index-header\">
            <h1>{$TemplateTitle}</h1>
            <a href=\"/{$ResourceName}/create\" class=\"btn btn-primary\">Add New</a>
        </div>
        <!-- Table content here -->
    </div>
</body>
</html>";
    }

    protected function getCrudCreateContent($replacements)
    {
        extract($replacements);

        if ($HasLayout === 'true') {
            return "@extends('{$Layout}')

@section('content')
<div class=\"create-header\">
    <h1>Create {$ResourceName}</h1>
</div>

<form method=\"POST\" action=\"/{$ResourceName}\" class=\"create-form\">
    @csrf
    
    <div class=\"form-group\">
        <label for=\"name\">Name:</label>
        <input type=\"text\" id=\"name\" name=\"name\" required>
    </div>
    
    <div class=\"form-actions\">
        <button type=\"submit\" class=\"btn btn-primary\">Create</button>
        <a href=\"/{$ResourceName}\" class=\"btn btn-secondary\">Cancel</a>
    </div>
</form>
@endsection
";
        }

        return "<!DOCTYPE html>
<html>
<head><title>Create {$ResourceName}</title></head>
<body>
    <h1>Create {$ResourceName}</h1>
    <form method=\"POST\" action=\"/{$ResourceName}\">
        <!-- Form fields here -->
    </form>
</body>
</html>";
    }

    protected function getCrudEditContent($replacements)
    {
        extract($replacements);

        if ($HasLayout === 'true') {
            return "@extends('{$Layout}')

@section('content')
<div class=\"edit-header\">
    <h1>Edit {$ResourceName}</h1>
</div>

<form method=\"PUT\" action=\"/{$ResourceName}/{{ \$item['id'] }}\" class=\"edit-form\">
    @csrf
    
    <div class=\"form-group\">
        <label for=\"name\">Name:</label>
        <input type=\"text\" id=\"name\" name=\"name\" value=\"{{ \$item['name'] ?? '' }}\" required>
    </div>
    
    <div class=\"form-actions\">
        <button type=\"submit\" class=\"btn btn-primary\">Update</button>
        <a href=\"/{$ResourceName}\" class=\"btn btn-secondary\">Cancel</a>
    </div>
</form>
@endsection
";
        }

        return "<!DOCTYPE html>
<html>
<head><title>Edit {$ResourceName}</title></head>
<body>
    <h1>Edit {$ResourceName}</h1>
    <!-- Edit form here -->
</body>
</html>";
    }

    protected function getCrudShowContent($replacements)
    {
        extract($replacements);

        if ($HasLayout === 'true') {
            return "@extends('{$Layout}')

@section('content')
<div class=\"show-header\">
    <h1>{$ResourceName} Details</h1>
    <div class=\"actions\">
        <a href=\"/{$ResourceName}/{{ \$item['id'] }}/edit\" class=\"btn btn-primary\">Edit</a>
        <a href=\"/{$ResourceName}\" class=\"btn btn-secondary\">Back to List</a>
    </div>
</div>

<div class=\"show-content\">
    @if(\$item ?? false)
        <dl>
            <dt>ID:</dt>
            <dd>{{ \$item['id'] ?? '' }}</dd>
            
            <dt>Name:</dt>
            <dd>{{ \$item['name'] ?? '' }}</dd>
            
            <dt>Created:</dt>
            <dd>{{ \$item['created_at'] ?? '' }}</dd>
        </dl>
    @else
        <p>{$ResourceName} not found.</p>
    @endif
</div>
@endsection
";
        }

        return "<!DOCTYPE html>
<html>
<head><title>Show {$ResourceName}</title></head>
<body>
    <h1>{$ResourceName} Details</h1>
    <!-- Show content here -->
</body>
</html>";
    }

    protected function generateApiContent($replacements)
    {
        extract($replacements);

        return "@php
    header('Content-Type: application/json');
@endphp
{
    \"success\": true,
    \"message\": \"{{ \$message ?? '{$ApiName} API response' }}\",
    \"data\": @if(\$data ?? false){{ json_encode(\$data) }}@else{}@endif,
    \"timestamp\": \"{{ date('Y-m-d H:i:s') }}\"
}";
    }

    protected function getFrameworkLinks($framework)
    {
        switch ($framework) {
            case 'bootstrap':
                return '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
            case 'tailwind':
                return '<script src="https://cdn.tailwindcss.com"></script>';
            default:
                return '';
        }
    }
}
