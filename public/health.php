<?php

declare(strict_types=1);

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Method not allowed.');
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "OK\n";
