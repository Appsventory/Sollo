<?php

namespace Core\Framework\Velo\Nixs\Compilers;

/**
 * Handles form-related template directives
 */
class FormCompiler
{
    /**
     * Compile CSRF tokens and form helpers
     */
    public static function compile($content)
    {
        // @csrf directive
        $content = str_replace('@csrf', '<?php echo Core\Framework\Velo\Nixs\Compilers\FormHelper::csrfField(); ?>', $content);

        // @method('PUT') directive - use callback for proper variable substitution
        $content = preg_replace_callback(
            '/@method\s*\(\s*[\'"](\w+)[\'"]\s*\)/',
            function ($matches) {
                $method = $matches[1];
                return '<?php echo Core\Framework\Velo\Nixs\Compilers\FormHelper::methodField("' . $method . '"); ?>';
            },
            $content
        );

        // Convert form method attributes
        // Strategy: Find form closing > while respecting {{ }} expressions
        $pos = 0;
        while (($pos = strpos($content, '<form', $pos)) !== false) {
            // Find the actual closing > of the form tag, skipping {{ }} expressions
            $endPos = $pos + 5; // Start after '<form'
            $inBraces = 0;
            while ($endPos < strlen($content)) {
                if (substr($content, $endPos, 2) === '{{') {
                    $inBraces++;
                    $endPos += 2;
                } elseif (substr($content, $endPos, 2) === '}}' && $inBraces > 0) {
                    $inBraces--;
                    $endPos += 2;
                } elseif ($content[$endPos] === '>' && $inBraces === 0) {
                    break;
                } else {
                    $endPos++;
                }
            }

            if ($endPos >= strlen($content)) {
                $pos++;
                continue;
            }

            // Extract the form tag
            $formTag = substr($content, $pos, $endPos - $pos + 1);

            // Check if this form has method="PUT|DELETE|PATCH"
            if (!preg_match('/method\s*=\s*["\']?(PUT|DELETE|PATCH)["\']?/i', $formTag, $methodMatch)) {
                $pos = $endPos + 1;
                continue;
            }

            $method = strtoupper($methodMatch[1]);

            // Replace the method value with POST
            $newFormTag = preg_replace(
                '/(method\s*=\s*)["\']?(PUT|DELETE|PATCH)["\']?/i',
                '${1}"POST"',
                $formTag
            );

            // Add hidden method input after the form tag
            $hiddenInput = "\n        " . '<input type="hidden" name="_method" value="' . $method . '">';
            $newFormTag = $newFormTag . $hiddenInput;

            // Replace in content
            $content = substr_replace($content, $newFormTag, $pos, $endPos - $pos + 1);

            $pos = $pos + strlen($newFormTag);
        }

        return $content;
    }
}
