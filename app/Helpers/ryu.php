<?php
function view_include(string $path, array $data = [])
{
    $baseDir = realpath(__DIR__ . '/../views'); // misalnya views/ sejajar dengan app/

    if (str_contains($path, '/') || str_starts_with($path, '../')) {
        $file = realpath($baseDir . '/' . $path . '.php');
    } else {
        // Gunakan dot notation
        $file = $baseDir . '/' . str_replace('.', '/', $path) . '.php';
    }

    extract($data);

    if ($file && file_exists($file)) {
        require $file;
    } else {
        echo "<!-- View not found: $file -->";
    }
}


function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit; // agar tidak ada output tambahan setelahnya
}

function method($method)
{
    return <<<HTML
    <input type="hidden" name="_method" value="$method">
    HTML;
}

function root_path(string $path = ''): string
{
    $root = dirname(__DIR__);
    return $root . ($path ? '/' . ltrim($path, '/') : '');
}