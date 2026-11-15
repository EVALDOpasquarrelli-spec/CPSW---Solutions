<?php
declare(strict_types=1);

// PHP's built-in server serves existing static files directly and sends API
// routes through this router to the single REST front controller.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
