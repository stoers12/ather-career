<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('APP_ENV=development');
putenv('PUBLIC_BASE_URL=https://localhost:8443');

require_once __DIR__ . '/../../../includes/owner_publication_presentation.php';

$variant = $argv[1] ?? 'published';
$state = match ($variant) {
    'no-slug' => ['public_slug' => null, 'is_published' => 0, 'published_at' => null],
    'reserved' => ['public_slug' => 'reserved-owner', 'is_published' => 0, 'published_at' => null],
    'offline' => ['public_slug' => 'offline-owner', 'is_published' => 0, 'published_at' => '2026-01-01 00:00:00'],
    'long' => ['public_slug' => 'a-' . str_repeat('long-', 11) . 'slug', 'is_published' => 1, 'published_at' => '2026-01-01 00:00:00'],
    'published' => ['public_slug' => 'momen-qasim-al-omari', 'is_published' => 1, 'published_at' => '2026-01-01 00:00:00'],
    default => throw new InvalidArgumentException('Unknown publication visual variant.'),
};

$_SESSION = [];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin.css"><script src="/admin.js" defer></script></head>
<body><div class="admin-layout"><main class="admin-content"><section><?php renderOwnerPublicationPresentation($state, ownerPublicationPublicUrl($state)); ?></section></main></div></body></html>
