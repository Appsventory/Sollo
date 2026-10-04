<?php

namespace Core\Framework\Velo\Nixs\Compilers;

use Core\Foundation\Http\CsrfToken;

/**
 * Runtime helpers used by compiled @csrf / @method directives.
 * Lives in its own file so PSR-4 can autoload it from cached templates.
 */
class FormHelper
{
    public static function csrfField(): string
    {
        return CsrfToken::input();
    }

    public static function methodField($method): string
    {
        $method = strtoupper((string) $method);
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '">';
    }
}
