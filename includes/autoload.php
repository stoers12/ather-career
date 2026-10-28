<?php

declare(strict_types=1);

// Composer is the only class loader for src/ (namespace Ather\, see docs/ARCHITECTURE.md).
// Images install it at build time; a source checkout needs `composer install`.
if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    throw new RuntimeException('Composer autoloader is missing; run composer install.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
