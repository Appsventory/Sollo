<?php
// === AMBIL DATA DARI $data ===
$exception = $data['exception'] ?? null;
$message = $data['message'] ?? 'Unknown error';
$file = $data['file'] ?? 'Unknown file';
$line = $data['line'] ?? 0;
$codeLines = $data['codePreview']['lines'] ?? [];
$errorLineInFile = $data['errorLine'] ?? 0;

// Hitung baris di snippet
$startLineInFile = min(array_keys($codeLines)); // baris pertama di file
$relativeErrorLine = $errorLineInFile - $startLineInFile + 1; // baris di dalam <pre>

// Validasi: pastikan baris error ada di snippet
if ($relativeErrorLine < 1 || $relativeErrorLine > count($codeLines)) {
    $relativeErrorLine = 1; // fallback
}

// Stack trace, request, env
$stackTrace = $data['stackTrace'] ?? [];
$request = $data['requestData'] ?? [];
$method = $request['method'] ?? 'GET';
$url = $request['url'] ?? '/';
$ip = $request['ip'] ?? 'Unknown';
$userAgent = $request['userAgent'] ?? 'Unknown';
$env = $data['environmentData'] ?? [];
$appEnv = $env['app_env'] ?? 'unknown';
$appDebug = $env['app_debug'] === 'true' ? 'true' : 'false';
$phpVersion = $data['serverData']['php_version'] ?? 'Unknown';
$serverName = $data['serverData']['server_name'] ?? 'localhost';
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Error: <?= htmlspecialchars($message) ?></title>

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Prism + Line Highlight -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-highlight/prism-line-highlight.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-highlight/prism-line-highlight.min.js"></script>

  <style>
    /* Highlight baris error */
    .line-highlight {
      background: linear-gradient(to right, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.05)) !important;
      border-left: 5px solid #ef4444 !important;
      margin-left: -1rem !important;
      padding-left: 0.75rem !important;
      position: relative;
    }
    .line-highlight:before {
      content: "Error";
      position: absolute;
      left: -3.5rem;
      top: 0;
      font-size: 0.75rem;
      color: #fff;
      background: #ef4444;
      padding: 0.125rem 0.5rem;
      border-radius: 0.25rem;
      font-weight: bold;
    }
    .line-highlight + .token-line .token {
      color: #dc2626 !important;
      font-weight: bold !important;
    }
    pre {
      margin: 0 !important;
      padding: 1rem 0 !important;
    }
    .line-numbers .line-numbers-rows {
      border-right: 1px solid #e5e7eb;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

  <div class="min-h-screen py-8 px-4">
    <div class="max-w-5xl mx-auto">

      <!-- Header -->
      <div class="bg-red-600 text-white p-6 rounded-t-lg shadow-lg">
        <h1 class="text-2xl font-bold flex items-center gap-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <?= htmlspecialchars(get_class($exception)) ?>
        </h1>
        <p class="mt-1 opacity-90"><?= htmlspecialchars($message) ?></p>
      </div>

      <!-- Summary -->
      <div class="bg-white border-x border-b border-gray-200 p-6 -mt-1">
        <div class="grid md:grid-cols-2 gap-6">
          <div>
            <h3 class="font-semibold text-gray-700 mb-2">File & Line</h3>
            <p class="text-sm font-mono bg-gray-100 px-3 py-2 rounded">
              <span class="text-red-600"><?= htmlspecialchars(basename($file)) ?></span>:<span class="text-blue-600"><?= $line ?></span>
            </p>
          </div>
          <div>
            <h3 class="font-semibold text-gray-700 mb-2">Route</h3>
            <p class="text-sm font-mono bg-gray-100 px-3 py-2 rounded text-green-700">
              <?= htmlspecialchars($method) ?> <?= htmlspecialchars($url) ?>
            </p>
          </div>
        </div>
      </div>

      <!-- CODE PREVIEW - AKURAT! -->
      <div class="bg-white border-x border-gray-200 p-6">
        <h3 class="font-semibold text-lg mb-3 text-gray-800">Code Preview</h3>
        <div class="rounded-lg overflow-hidden border border-gray-300">
          <pre class="language-php line-numbers" 
               data-line="<?= $relativeErrorLine ?>" 
               data-start="<?= $startLineInFile ?>"><code><?php
            foreach ($codeLines as $num => $codeLine) {
                echo htmlspecialchars(rtrim($codeLine)) . "\n";
            }
          ?></code></pre>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          File: <code><?= htmlspecialchars($file) ?></code> • 
          Line: <code><?= $line ?></code> (baris <?= $relativeErrorLine ?> di snippet)
        </p>
      </div>

      <!-- Stack Trace -->
      <div class="bg-white border-x border-gray-200 p-6">
        <h3 class="font-semibold text-lg mb-3 text-gray-800">Stack Trace</h3>
        <div class="space-y-3 text-sm">
          <?php foreach ($stackTrace as $i => $frame): ?>
            <?php
            $func = $frame['function'] ?? 'unknown';
            $fileFrame = $frame['file'] ?? '[internal]';
            $lineFrame = $frame['line'] ?? '';
            $isVendor = $frame['isVendor'] ?? false;
            $displayFile = $isVendor ? basename($fileFrame) : str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', $fileFrame);
            ?>
            <details class="border rounded-lg p-3 <?= $isVendor ? 'bg-gray-50 opacity-75' : 'bg-white' ?>">
              <summary class="cursor-pointer font-mono text-blue-600 hover:text-blue-800 flex justify-between items-center">
                <span>#<?= $i ?> <?= htmlspecialchars($func) ?></span>
                <span class="text-xs text-gray-500"><?= htmlspecialchars($displayFile) ?>:<?= $lineFrame ?></span>
              </summary>
              <div class="mt-2 pl-4 text-xs text-gray-600 space-y-1">
                <?php if (!empty($frame['args'])): ?>
                  <div><strong>Args:</strong> <code class="text-xs"><?= htmlspecialchars(print_r($frame['args'], true)) ?></code></div>
                <?php endif; ?>
                <div><strong>File:</strong> <code><?= htmlspecialchars($fileFrame) ?></code></div>
                <?php if ($lineFrame): ?>
                  <div><strong>Line:</strong> <code><?= $lineFrame ?></code></div>
                <?php endif; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Request Info -->
      <div class="bg-white border-x border-gray-200 p-6">
        <h3 class="font-semibold text-lg mb-3 text-gray-800">Request Information</h3>
        <div class="grid md:grid-cols-3 gap-4 text-sm">
          <div>
            <span class="font-medium text-gray-600">Method:</span>
            <span class="ml-2 font-mono <?= $method === 'GET' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?> px-2 py-1 rounded"><?= htmlspecialchars($method) ?></span>
          </div>
          <div>
            <span class="font-medium text-gray-600">URL:</span>
            <span class="ml-2 font-mono text-blue-700"><?= htmlspecialchars($url) ?></span>
          </div>
          <div>
            <span class="font-medium text-gray-600">IP:</span>
            <span class="ml-2 font-mono"><?= htmlspecialchars($ip) ?></span>
          </div>
        </div>
        <div class="mt-4">
          <span class="font-medium text-gray-600">User Agent:</span>
          <p class="text-xs mt-1 text-gray-600 break-all font-mono bg-gray-50 p-2 rounded">
            <?= htmlspecialchars($userAgent) ?>
          </p>
        </div>
      </div>

      <!-- Environment -->
      <div class="bg-white border-x border-b border-gray-200 rounded-b-lg p-6">
        <h3 class="font-semibold text-lg mb-3 text-gray-800">Environment</h3>
        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div><strong>APP_ENV:</strong> <code class="bg-yellow-100 px-2 py-1 rounded"><?= htmlspecialchars($appEnv) ?></code></div>
          <div><strong>APP_DEBUG:</strong> <code class="bg-green-100 text-green-800 px-2 py-1 rounded"><?= htmlspecialchars($appDebug) ?></code></div>
          <div><strong>PHP:</strong> <code><?= htmlspecialchars($phpVersion) ?></code></div>
          <div><strong>Server:</strong> <code><?= htmlspecialchars($serverName) ?>:8000</code></div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-8 text-center text-xs text-gray-500">
        NineVerse Error Handler • <?= date('F d, Y H:i:s') ?>
      </div>
    </div>
  </div>

  <!-- Force Prism Highlight -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const pre = document.querySelector('pre.line-numbers');
      if (pre) {
        Prism.highlightElement(pre.querySelector('code'));
      }
    });
  </script>
</body>
</html>