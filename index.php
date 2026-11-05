<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$file = __DIR__ . '/index.html';
if (!is_file($file)) { http_response_code(500); exit('index.html is missing'); }
$html = file_get_contents($file);
if ($html === false) { http_response_code(500); exit('Could not read index.html'); }
$script = '<script src="db-bridge.js" defer></script>';
if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $script . "\n</body>", $html);
} else {
    $html .= $script;
}
echo $html;
