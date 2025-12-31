<?php

namespace Core\Console\Commands;

use Core\Console\Traits\ConsoleOutputFormatter;
use Core\Console\Traits\CommandHelper;
use Core\Console\Support\ArgumentParser;

/**
 * BaseCommand Abstract Class
 * 
 * Base class for all console commands.
 * Uses traits for output formatting and file operations.
 * 
 * Provides:
 * - ConsoleOutputFormatter: success(), error(), warning(), info(), etc.
 * - CommandHelper: file operations, string utilities
 * - ArgumentParser integration
 */
abstract class BaseCommand
{
    use ConsoleOutputFormatter;
    use CommandHelper;

    abstract public function handle(array $argv);

    /**
     * Create ArgumentParser from argv
     */
    protected function parseArguments(array $argv): ArgumentParser
    {
        return new ArgumentParser($argv);
    }

    /**
     * Get option value from argv (backward compatibility)
     * 
     * @deprecated Use parseArguments() and ArgumentParser instead
     */
    protected function getOptionValue(array $argv, $key)
    {
        $parser = new ArgumentParser($argv);
        return $parser->getOption($key);
    }

    /**
     * Check if option exists (backward compatibility)
     * 
     * @deprecated Use parseArguments() and ArgumentParser instead
     */
    protected function hasOption(array $argv, $key)
    {
        $parser = new ArgumentParser($argv);
        return $parser->hasOption($key);
    }

    /**
     * Override CommandHelper::getStub() to auto-create stubs from content methods
     */
    protected function getStub(string $stubName): string
    {
        $stubPath = __DIR__ . "/stubs/{$stubName}.stub";

        if (!file_exists($stubPath)) {
            $content = $this->getDefaultStubContent($stubName);
            $this->ensureDirectoryExists($stubPath);

            if (file_put_contents($stubPath, $content)) {
                $this->info("Created missing stub file: {$stubPath}");
            }
        }

        return file_get_contents($stubPath) ?: '';
    }

    /**
     * Get default stub content based on stub name
     */
    protected function getDefaultStubContent(string $stubName): string
    {
        return match ($stubName) {
            'controller' => $this->getControllerStubContent(),
            'controller.resource' => $this->getResourceControllerStubContent(),
            'model' => $this->getModelStubContent(),
            'middleware' => $this->getMiddlewareStubContent(),
            'view' => $this->getViewStubContent(),
            'migration' => $this->getMigrationStubContent(),
            'seeder' => $this->getSeederStubContent(),
            'config' => $this->getConfigStubContent(),
            'maintenance' => $this->getMaintenanceStubContent(),
            default => "<?php\n\n// {{StubName}} stub\n// Edit this file to customize the template\n"
        };
    }

    // ============ STUB CONTENT GENERATORS ============

    protected function getControllerStubContent(): string
    {
        return '<?php

namespace App\Controllers;

use Core\Foundation\Controller;
use Core\Foundation\Http\Session;
use Core\Foundation\Http\CsrfToken;
{{UseModel}}

class {{ControllerClass}} extends Controller
{
    public function index()
    {
        // Display all {{ModelVariable}} records
        return $this->view(\'{{ModelVariable}}.index\');
    }

    public function create()
    {
        // Show create form
        return $this->view(\'{{ModelVariable}}.create\');
    }

    public function store()
    {
        // Store new {{ModelVariable}}
        CsrfToken::validate();
        
        // Validation logic here
        
        // Save logic here
        
        Session::flash(\'success\', \'{{ModelClass}} created successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }

    public function edit($id)
    {
        // Show edit form
        return $this->view(\'{{ModelVariable}}.edit\', compact(\'id\'));
    }

    public function update($id)
    {
        // Update {{ModelVariable}}
        CsrfToken::validate();
        
        // Validation logic here
        
        // Update logic here
        
        Session::flash(\'success\', \'{{ModelClass}} updated successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }

    public function destroy($id)
    {
        // Delete {{ModelVariable}}
        CsrfToken::validate();
        
        // Delete logic here
        
        Session::flash(\'success\', \'{{ModelClass}} deleted successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }
}';
    }

    protected function getResourceControllerStubContent(): string
    {
        return '<?php

namespace App\Controllers;

use Core\Foundation\Controller;
use Core\Foundation\Http\Session;
use Core\Foundation\Http\CsrfToken;
{{UseModel}}

class {{ControllerClass}} extends Controller
{
    public function index()
    {
        return $this->view(\'{{ModelVariable}}.index\');
    }

    public function create()
    {
        return $this->view(\'{{ModelVariable}}.create\');
    }

    public function store()
    {
        CsrfToken::validate();
        Session::flash(\'success\', \'{{ModelClass}} created successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }

    public function show($id)
    {
        return $this->view(\'{{ModelVariable}}.show\', compact(\'id\'));
    }

    public function edit($id)
    {
        return $this->view(\'{{ModelVariable}}.edit\', compact(\'id\'));
    }

    public function update($id)
    {
        CsrfToken::validate();
        Session::flash(\'success\', \'{{ModelClass}} updated successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }

    public function destroy($id)
    {
        CsrfToken::validate();
        Session::flash(\'success\', \'{{ModelClass}} deleted successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }
}';
    }

    protected function getModelStubContent(): string
    {
        return '<?php

namespace App\Models;

use Core\Foundation\ORM\Model;

class {{ModelClass}} extends Model
{
    protected $table = \'{{TableName}}\';
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
}';
    }

    protected function getMiddlewareStubContent(): string
    {
        return '<?php

namespace App\Middleware;

use Core\Foundation\Http\Request;

class {{MiddlewareClass}}
{
    public function handle(Request $request, callable $next)
    {
        // Before the request is handled
        
        $response = $next($request);
        
        // After the request is handled
        
        return $response;
    }
}';
    }

    protected function getViewStubContent(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ViewTitle}} | Sollo</title>
</head>
<body>
    <div class="container">
        <h1>{{ViewName}}</h1>
        <p>Edit this view: {{FilePath}}</p>
    </div>
</body>
</html>';
    }

    protected function getMigrationStubContent(): string
    {
        return '<?php

use Core\Foundation\Database\Migration;
use Core\Foundation\Database\Schema;

class {{MigrationClass}} extends Migration
{
    public function up()
    {
        Schema::create(\'{{TableName}}\', function ($table) {
            $table->id();
            // Add your columns here
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'{{TableName}}\');
    }
}';
    }

    protected function getSeederStubContent(): string
    {
        return '<?php

use Core\Foundation\Seeding\Seeder;
use Core\Foundation\Database\DB;

class {{SeederClass}} extends Seeder
{
    public function run()
    {
        DB::table(\'{{TableName}}\')->insert([
            // Add your data here
        ]);
    }
}';
    }

    protected function getConfigStubContent(): string
    {
        return '<?php

return [
    \'default\' => env(\'{{CONFIG_NAME}}_DEFAULT\', \'local\'),
    
    \'options\' => [
        \'timeout\' => env(\'{{CONFIG_NAME}}_TIMEOUT\', 60),
    ],
];';
    }

    protected function getMaintenanceStubContent(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; }
        h1 { color: #333; }
        p { color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Application Maintenance</h1>
        <p>We are currently performing maintenance. Please try again later.</p>
    </div>
</body>
</html>';
    }

    /**
     * Replace stub variables with actual values
     * 
     * Handles placeholders like {{VariableName}} in stub content
     * 
     * @param string $content The stub content with placeholders
     * @param array $replacements Key-value pairs: ['VariableName' => 'value']
     * @return string The processed content with replacements
     */
    protected function replaceStubVariables(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $placeholder = "{{" . $key . "}}";
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }
}
