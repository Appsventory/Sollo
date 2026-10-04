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
        echo "\e[1;32mCreates:\e[0m resources/Views/components/<name>.nixs.php\n";
        echo "\e[1;32mUse in views:\e[0m @nixscomponent('Name', [...]) ... @endnixscomponent\n\n";
        echo "\e[1;32mOptions:\e[0m\n";
        echo "  \e[36m--props=<props>\e[0m     Props (comma-separated), e.g. text,color\n";
        echo "  \e[36m--slots\e[0m             Extra header/footer slots\n";
        echo "  \e[36m--alpine\e[0m            Alpine.js scaffold\n";
        echo "  \e[36m--class\e[0m             PHP class at app/View/Components/\n\n";
        echo "\e[1;32mExamples:\e[0m\n";
        echo "  \e[2mphp fany make:component Button --props=text,color\e[0m\n";
        echo "  \e[2mphp fany make:component Card --slots\e[0m\n";
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

        // Always support body/slot from @nixscomponent (and optional header/footer with --slots)
        $content = $propsCode;
        $content .= "<?php\n";
        $content .= "\$body = \$body ?? (\$slot ?? '');\n";
        $content .= "\$slot = \$slot ?? \$body;\n";
        if ($HasSlots) {
            $content .= "\$header = \$header ?? '';\n";
            $content .= "\$footer = \$footer ?? '';\n";
        }
        $content .= "?>\n\n";

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

        $content .= "        <div class=\"component-content\">\n";
        $content .= "            {!! \$body !!}\n";
        $content .= "        </div>\n";

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

        // PSR-4: App\ → app/ (never under resources/)
        $classPath = "app/View/Components/{$ComponentClass}.php";

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
                $propsParams .= "\${$prop} = null, ";
                $propsConstructor .= "        \$this->{$prop} = \${$prop};\n";
            }
            $propsParams = rtrim($propsParams, ', ');
        }

        $content = "<?php\n\n";
        $content .= "namespace App\\View\\Components;\n\n";
        $content .= "use Core\\Framework\\Velo\\Nixs\\NixsCompiler as Nixs;\n\n";
        $content .= "class {$ComponentClass}\n";
        $content .= "{\n";
        $content .= $propsProperties;
        $content .= "\n";
        $content .= "    public function __construct({$propsParams})\n";
        $content .= "    {\n";
        $content .= $propsConstructor;
        $content .= "    }\n\n";
        $content .= "    public function render(): string\n";
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

        $propsExample = '';
        if (!empty($Props)) {
            $parts = [];
            foreach ($Props as $prop) {
                $prop = trim($prop);
                $parts[] = "'{$prop}' => 'value'";
            }
            $propsExample = implode(', ', $parts);
        }

        echo "\n\e[33m1. In a view — with body (@nixscomponent):\e[0m\n";
        echo "   \e[36m@nixscomponent('{$name}'";
        if ($propsExample !== '') {
            echo ", [{$propsExample}]";
        }
        echo ")\n";
        echo "       <p>Content goes here</p>\n";
        echo "   @endnixscomponent\e[0m\n";

        echo "\n\e[33m2. From PHP — Nixs::component():\e[0m\n";
        echo "   \e[36muse Core\\Framework\\Velo\\Nixs\\NixsCompiler as Nixs;\n";
        echo "   echo Nixs::component('{$name}'";
        if ($propsExample !== '') {
            echo ", [{$propsExample}]";
        }
        echo ");\e[0m\n";

        if ($HasClass) {
            echo "\n\e[33m3. Component class:\e[0m\n";
            echo "   \e[36m\$c = new App\\View\\Components\\{$ComponentClass}(";
            if (!empty($Props)) {
                $vals = [];
                foreach ($Props as $prop) {
                    $vals[] = "'example_" . trim($prop) . "'";
                }
                echo implode(', ', $vals);
            }
            echo ");\n";
            echo "   echo \$c->render();\e[0m\n";
        }

        if (!empty($Props)) {
            echo "\n\e[33mProps:\e[0m\n";
            foreach ($Props as $prop) {
                $prop = trim($prop);
                echo "   \e[36m\${$prop}\e[0m\n";
            }
        }

        echo "\n\e[33mSlot variables in template:\e[0m \$body / \$slot";
        if ($HasSlots) {
            echo " / \$header / \$footer";
        }
        echo "\n";

        if ($HasAlpine) {
            echo "\n\e[33mAlpine.js:\e[0m include CDN in layout:\n";
            echo "   \e[2m<script defer src=\"https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js\"></script>\e[0m\n";
        }

        echo "\n\e[1;32mFile:\e[0m resources/Views/components/{$name}.nixs.php\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
}
