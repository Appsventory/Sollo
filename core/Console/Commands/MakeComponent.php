<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class MakeComponent extends BaseCommand
{
    public function handle(array $argv)
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Component name is required.");
            $this->showUsage();
            return;
        }

        $this->createComponent($name, $argv);
    }

    protected function showUsage()
    {
        echo "\n\e[1;33mUsage:\e[0m\n";
        echo "  \e[36mphp fany make:component <name> [options]\e[0m\n\n";
        echo "\e[1;32mOptions:\e[0m\n";
        echo "  \e[36m--props=<props>\e[0m     Component props (comma-separated)\n";
        echo "  \e[36m--slots\e[0m             Include slot support\n";
        echo "  \e[36m--alpine\e[0m           Include Alpine.js functionality\n";
        echo "  \e[36m--class\e[0m            Create component class file\n\n";
        echo "\e[1;32mExamples:\e[0m\n";
        echo "  \e[2mphp fany make:component Button --props=text,color,size\e[0m\n";
        echo "  \e[2mphp fany make:component Card --slots --alpine\e[0m\n";
        echo "  \e[2mphp fany make:component Modal --class --slots\e[0m\n";
    }

    protected function createComponent($name, $argv)
    {
        $componentPath = "resources/Views/components/{$name}.nixs.php";

        if (file_exists($componentPath)) {
            $this->warning("Component {$name} already exists.");
            return;
        }

        $this->ensureDirectoryExists($componentPath);

        $props = $this->getOptionValue($argv, 'props');
        $hasSlots = $this->hasOption($argv, 'slots');
        $hasAlpine = $this->hasOption($argv, 'alpine');
        $hasClass = $this->hasOption($argv, 'class');

        $replacements = [
            'ComponentName' => $name,
            'ComponentClass' => ucfirst($name),
            'Props' => $props ? explode(',', $props) : [],
            'HasSlots' => $hasSlots,
            'HasAlpine' => $hasAlpine,
            'HasClass' => $hasClass
        ];

        $content = $this->generateComponentContent($replacements);

        file_put_contents($componentPath, $content);
        $this->success("Component created: {$componentPath}");

        // Create component class if requested
        if ($hasClass) {
            $this->createComponentClass($name, $replacements);
        }

        // Show usage example
        $this->showComponentUsage($name, $replacements);
    }

    protected function generateComponentContent($replacements)
    {
        extract($replacements);

        // Generate props handling
        $propsCode = '';
        if (!empty($Props)) {
            $propsCode = "<?php\n";
            foreach ($Props as $prop) {
                $prop = trim($prop);
                $propsCode .= "\$$prop = \$$prop ?? null;\n";
            }
            $propsCode .= "?>\n\n";
        }

        // Generate Alpine.js attributes
        $alpineAttr = $HasAlpine ? ' x-data="{}" x-init="init()"' : '';

        // Generate component content
        $content = $propsCode;

        if ($HasSlots) {
            $content .= "<?php \$slot = \$slot ?? ''; \$header = \$header ?? ''; \$footer = \$footer ?? ''; ?>\n\n";
        }

        $content .= "<div class=\"component-{$ComponentName}\"{$alpineAttr}>\n";

        if ($HasSlots) {
            $content .= "    @if(\$header)\n";
            $content .= "        <div class=\"component-header\">\n";
            $content .= "            {!! \$header !!}\n";
            $content .= "        </div>\n";
            $content .= "    @endif\n\n";
        }

        $content .= "    <div class=\"component-body\">\n";

        if (!empty($Props)) {
            foreach ($Props as $prop) {
                $prop = trim($prop);
                $content .= "        @if(\${$prop})\n";
                $content .= "            <div class=\"{$prop}\">{{\${$prop}}}</div>\n";
                $content .= "        @endif\n";
            }
        }

        if ($HasSlots) {
            $content .= "        <div class=\"component-content\">\n";
            $content .= "            {!! \$slot !!}\n";
            $content .= "        </div>\n";
        } else {
            $content .= "        <!-- {$ComponentName} component content -->\n";
            $content .= "        <p>This is the {$ComponentName} component.</p>\n";
        }

        $content .= "    </div>\n";

        if ($HasSlots) {
            $content .= "\n    @if(\$footer)\n";
            $content .= "        <div class=\"component-footer\">\n";
            $content .= "            {!! \$footer !!}\n";
            $content .= "        </div>\n";
            $content .= "    @endif\n";
        }

        $content .= "</div>\n";

        // Add Alpine.js script if needed
        if ($HasAlpine) {
            $content .= "\n<script>\n";
            $content .= "function {$ComponentName}Component() {\n";
            $content .= "    return {\n";
            $content .= "        init() {\n";
            $content .= "            console.log('{$ComponentName} component initialized');\n";
            $content .= "        }\n";
            $content .= "    }\n";
            $content .= "}\n";
            $content .= "</script>\n";
        }

        // Add CSS styles
        $content .= "\n<style>\n";
        $content .= ".component-{$ComponentName} {\n";
        $content .= "    /* Add your {$ComponentName} component styles here */\n";
        $content .= "    border: 1px solid #ddd;\n";
        $content .= "    border-radius: 8px;\n";
        $content .= "    padding: 16px;\n";
        $content .= "    margin: 8px 0;\n";
        $content .= "}\n";

        if ($HasSlots) {
            $content .= "\n.component-{$ComponentName} .component-header {\n";
            $content .= "    border-bottom: 1px solid #eee;\n";
            $content .= "    padding-bottom: 8px;\n";
            $content .= "    margin-bottom: 16px;\n";
            $content .= "}\n";
            $content .= "\n.component-{$ComponentName} .component-footer {\n";
            $content .= "    border-top: 1px solid #eee;\n";
            $content .= "    padding-top: 8px;\n";
            $content .= "    margin-top: 16px;\n";
            $content .= "}\n";
        }

        $content .= "</style>\n";

        return $content;
    }

    protected function createComponentClass($name, $replacements)
    {
        extract($replacements);

        $classPath = "resources/Views/Components/{$ComponentClass}.php";

        if (file_exists($classPath)) {
            $this->warning("Component class {$ComponentClass} already exists.");
            return;
        }

        $this->ensureDirectoryExists($classPath);

        $propsProperties = '';
        $propsConstructor = '';
        $propsParams = '';

        if (!empty($Props)) {
            foreach ($Props as $prop) {
                $prop = trim($prop);
                $propsProperties .= "    public \${$prop};\n";
                $propsParams .= "\${$prop}, ";
                $propsConstructor .= "        \$this->{$prop} = \${$prop};\n";
            }
            $propsParams = rtrim($propsParams, ', ');
        }

        $content = "<?php\n\n";
        $content .= "namespace App\\View\\Components;\n\n";
        $content .= "class {$ComponentClass}\n";
        $content .= "{\n";
        $content .= $propsProperties;
        $content .= "\n";
        $content .= "    public function __construct({$propsParams})\n";
        $content .= "    {\n";
        $content .= $propsConstructor;
        $content .= "    }\n\n";
        $content .= "    public function render()\n";
        $content .= "    {\n";
        $content .= "        return Nixs::component('{$ComponentName}', [\n";

        if (!empty($Props)) {
            foreach ($Props as $prop) {
                $prop = trim($prop);
                $content .= "            '{$prop}' => \$this->{$prop},\n";
            }
        }

        $content .= "        ]);\n";
        $content .= "    }\n";
        $content .= "}\n";

        file_put_contents($classPath, $content);
        $this->success("Component class created: {$classPath}");
    }

    protected function showComponentUsage($name, $replacements)
    {
        extract($replacements);

        echo "\n\e[1;32m📋 Component Usage Examples:\e[0m\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        echo "\n\e[33m1. Using Nixs::component() method:\e[0m\n";
        echo "   \e[36m<?php echo Nixs::component('{$name}'";

        if (!empty($Props)) {
            echo ", [\n";
            foreach ($Props as $prop) {
                $prop = trim($prop);
                echo "       '{$prop}' => 'value',\n";
            }
            echo "   ]";
        }

        echo "); ?>\e[0m\n";

        if ($HasSlots) {
            echo "\n\e[33m2. With slots:\e[0m\n";
            echo "   \e[36m<?php echo Nixs::component('{$name}', [\n";
            echo "       'slot' => 'Main content here',\n";
            echo "       'header' => 'Header content',\n";
            echo "       'footer' => 'Footer content'\n";
            echo "   ]); ?>\e[0m\n";
        }

        if ($HasClass) {
            echo "\n\e[33m3. Using component class:\e[0m\n";
            echo "   \e[36m<?php\n";
            echo "   \$component = new App\\View\\Components\\{$ComponentClass}(";

            if (!empty($Props)) {
                $exampleValues = [];
                foreach ($Props as $prop) {
                    $prop = trim($prop);
                    $exampleValues[] = "'example_{$prop}'";
                }
                echo implode(', ', $exampleValues);
            }

            echo ");\n";
            echo "   echo \$component->render();\n";
            echo "   ?>\e[0m\n";
        }

        if (!empty($Props)) {
            echo "\n\e[33m4. Available props:\e[0m\n";
            foreach ($Props as $prop) {
                $prop = trim($prop);
                echo "   \e[36m\${$prop}\e[0m - Component {$prop} property\n";
            }
        }

        if ($HasAlpine) {
            echo "\n\e[33m5. Alpine.js integration:\e[0m\n";
            echo "   \e[36mThe component includes Alpine.js functionality.\e[0m\n";
            echo "   \e[36mMake sure to include Alpine.js in your layout:\e[0m\n";
            echo "   \e[2m<script defer src=\"https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js\"></script>\e[0m\n";
        }

        echo "\n\e[1;32m💡 Tip:\e[0m Edit the component file to customize its appearance and behavior.\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
}
