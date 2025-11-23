<?php

namespace App\Console\Commands;

abstract class BaseCommand
{
    abstract public function handle(array $argv);

    protected function success($message)
    {
        echo "\e[32m✅ $message\e[0m\n";
    }

    protected function error($message)
    {
        echo "\e[31m❌ $message\e[0m\n";
    }

    protected function warning($message)
    {
        echo "\e[33m⚠️  $message\e[0m\n";
    }

    protected function info($message)
    {
        echo "\e[36mℹ️  $message\e[0m\n";
    }

    protected function ensureDirectoryExists($path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \Exception("Cannot create directory: $dir");
            }
        }
    }

    protected function getStub($stubName)
    {
        $stubPath = __DIR__ . "/stubs/{$stubName}.stub";
        if (!file_exists($stubPath)) {
            // Create stubs directory if it doesn't exist
            $stubsDir = __DIR__ . "/stubs";
            if (!is_dir($stubsDir)) {
                mkdir($stubsDir, 0755, true);
            }
            
            // Create the stub file with default content
            $defaultContent = $this->getDefaultStubContent($stubName);
            file_put_contents($stubPath, $defaultContent);
            
            $this->info("Created missing stub file: {$stubPath}");
        }
        return file_get_contents($stubPath);
    }

    protected function getDefaultStubContent($stubName)
    {
        switch ($stubName) {
            case 'controller':
                return $this->getControllerStubContent();
            case 'controller.resource':
                return $this->getResourceControllerStubContent();
            case 'model':
                return $this->getModelStubContent();
            case 'middleware':
                return $this->getMiddlewareStubContent();
            case 'view':
                return $this->getViewStubContent();
            case 'migration':
                return $this->getMigrationStubContent();
            case 'seeder':
                return $this->getSeederStubContent();
            case 'config':
                return $this->getConfigStubContent();
            default:
                return "<?php\n\n// {{StubName}} stub\n// Edit this file to customize the template\n";
        }
    }

    protected function getControllerStubContent()
    {
        return '<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\CsrfToken;
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
}
';
    }

    protected function getResourceControllerStubContent()
    {
        return '<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\CsrfToken;
{{UseModel}}

class {{ControllerClass}} extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // ${{ModelVariable}}s = {{ModelClass}}::all();
        return $this->view(\'{{ModelVariable}}.index\');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return $this->view(\'{{ModelVariable}}.create\');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        CsrfToken::validate();
        
        // Validate request
        // $validated = $this->validate([
        //     \'name\' => \'required|string|max:255\',
        //     \'email\' => \'required|email|unique:users\',
        // ]);
        
        // ${{ModelVariable}} = {{ModelClass}}::create($validated);
        
        Session::flash(\'success\', \'{{ModelClass}} created successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // ${{ModelVariable}} = {{ModelClass}}::findOrFail($id);
        return $this->view(\'{{ModelVariable}}.show\', compact(\'{{ModelVariable}}\'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        // ${{ModelVariable}} = {{ModelClass}}::findOrFail($id);
        return $this->view(\'{{ModelVariable}}.edit\', compact(\'{{ModelVariable}}\'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($id)
    {
        CsrfToken::validate();
        
        // ${{ModelVariable}} = {{ModelClass}}::findOrFail($id);
        
        // Validate request
        // $validated = $this->validate([
        //     \'name\' => \'required|string|max:255\',
        //     \'email\' => \'required|email|unique:users,email,\' . ${{ModelVariable}}->id,
        // ]);
        
        // ${{ModelVariable}}->update($validated);
        
        Session::flash(\'success\', \'{{ModelClass}} updated successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        CsrfToken::validate();
        
        // ${{ModelVariable}} = {{ModelClass}}::findOrFail($id);
        // ${{ModelVariable}}->delete();
        
        Session::flash(\'success\', \'{{ModelClass}} deleted successfully!\');
        return $this->redirect(\'/{{ModelVariable}}\');
    }
}
';
    }

    protected function getModelStubContent()
    {
        return '<?php

namespace App\Models;

use App\Core\Model;

class {{ModelClass}} extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = \'{{TableName}}\';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // Add your fillable attributes here
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        // Add attributes to hide (e.g., passwords)
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Add your attribute casts here
        // \'created_at\' => \'datetime\',
        // \'updated_at\' => \'datetime\',
    ];

    /**
     * Define relationships here
     */
    
    // Example: One-to-Many relationship
    // public function posts()
    // {
    //     return $this->hasMany(Post::class);
    // }
    
    // Example: Belongs-to relationship
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }
}
';
    }

    protected function getMiddlewareStubContent()
    {
        return '<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class {{MiddlewareClass}}
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, callable $next)
    {
        // Before the request is handled
        
        // Add your middleware logic here
        // For example:
        // if (!$this->checkCondition()) {
        //     return Response::json([\'error\' => \'Unauthorized\'], 401);
        // }
        
        $response = $next($request);
        
        // After the request is handled
        // You can modify the response here if needed
        
        return $response;
    }
    
    /**
     * Add your helper methods here
     */
    protected function checkCondition()
    {
        // Your condition logic
        return true;
    }
}
';
    }

    protected function getViewStubContent()
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ViewTitle}} | NineVerse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Warna kustom agar tetap sama persis */
        :root {
            --bg-main: #161d2f;
            --accent: #DC143C;
        }
        body {
            background: var(--bg-main);
        }
        .btn-accent {
            background: var(--accent);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-lg bg-white/5 backdrop-blur rounded-2xl shadow-2xl p-8 text-white space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold tracking-wide">BaseView</h1>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-[#DC143C]">{{ViewName}}</span>
        </div>

        <!-- Content -->
        <p class="text-gray-300">
            This page is your starting point. Throw away the old code, start a new story.
        </p>

        <!-- CTA -->
        <button class="btn-accent w-full py-3 rounded-lg font-semibold text-white hover:opacity-90 transition">
            Start coding now
        </button>

        <!-- Footer info -->
        <div class="pt-4 border-t border-white/10 text-xs text-gray-400 text-center">
            Path: {{FilePath}}
        </div>
    </div>
</body>
</html>';
    }

    protected function getMigrationStubContent()
    {
        return '<?php

use App\Core\Migration;
use App\Core\Schema;

class {{MigrationClass}} extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(\'{{TableName}}\', function ($table) {
            $table->id();
            
            // Add your columns here
            // $table->string(\'name\');
            // $table->string(\'email\')->unique();
            // $table->text(\'description\')->nullable();
            // $table->boolean(\'is_active\')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists(\'{{TableName}}\');
    }
}
';
    }

    protected function getSeederStubContent()
    {
        return '<?php

use App\Core\Seeder;
use App\Core\DB;

class {{SeederClass}} extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Method 1: Insert single record
        DB::table(\'{{TableName}}\')->insert([
            \'name\' => \'Sample Name\',
            \'email\' => \'sample@example.com\',
            \'created_at\' => date(\'Y-m-d H:i:s\'),
            \'updated_at\' => date(\'Y-m-d H:i:s\'),
        ]);

        echo "✅ {{SeederClass}} completed\n";
    }
}
';
    }

    protected function getConfigStubContent()
    {
        return '<?php

/**
 * {{ConfigTitle}} Configuration
 */

return [

    \'default\' => env(\'{{ConfigName|upper}}_DEFAULT\', \'local\'),

    \'connections\' => [

        \'local\' => [
            \'driver\' => \'local\',
            \'host\' => env(\'{{ConfigName|upper}}_HOST\', \'localhost\'),
            \'port\' => env(\'{{ConfigName|upper}}_PORT\', 587),
            \'username\' => env(\'{{ConfigName|upper}}_USERNAME\'),
            \'password\' => env(\'{{ConfigName|upper}}_PASSWORD\'),
        ],

    ],

    \'options\' => [
        \'timeout\' => env(\'{{ConfigName|upper}}_TIMEOUT\', 60),
        \'debug\' => env(\'{{ConfigName|upper}}_DEBUG\', false),
    ],

];
';
    }

    protected function replaceStubVariables($stub, $replacements)
    {
        foreach ($replacements as $search => $replace) {
            $stub = str_replace("{{" . $search . "}}", $replace, $stub);
        }
        return $stub;
    }

    protected function parseOptions($argv)
    {
        $options = [];
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', substr($arg, 2), 2);
                    $options[$key] = $value;
                } else {
                    $options[substr($arg, 2)] = true;
                }
            }
        }
        return $options;
    }

    protected function hasOption($argv, $option)
    {
        return in_array("--$option", $argv);
    }

    protected function getOptionValue($argv, $option)
    {
        foreach ($argv as $i => $arg) {
            if ($arg === "--$option" && isset($argv[$i + 1])) {
                return $argv[$i + 1];
            }
            if (str_starts_with($arg, "--$option=")) {
                return substr($arg, strlen("--$option="));
            }
        }
        return null;
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        echo "\e[95m{$question}\e[0m [" . ($default ? 'Y/n' : 'y/N') . "]: ";
        
        $handle = fopen("php://stdin", "r");
        $input = trim(strtolower(fgets($handle)));
        fclose($handle);
        
        if (empty($input)) {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    protected function ask(string $question, ?string $default = null): string
    {
        $defaultText = $default ? " (default: {$default})" : "";
        echo "\e[95m{$question}\e[0m{$defaultText}: ";
        
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        return empty($input) ? $default : $input;
    }

    protected function choice(string $question, array $choices, $default = null): string
    {
        echo "\n\e[95m{$question}\e[0m\n";
        foreach ($choices as $key => $choice) {
            $marker = ($choice === $default) ? '*' : ' ';
            echo "  [{$marker}] {$key}) {$choice}\n";
        }
        echo "Choose an option" . ($default ? " (default: {$default})" : "") . ": ";
        
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        if (empty($input) && $default !== null) {
            return $default;
        }
        
        return $choices[$input] ?? $input;
    }

    protected function line(string $message = ''): void
    {
        echo $message . "\n";
    }
}