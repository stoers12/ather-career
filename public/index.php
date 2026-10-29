<?php

declare(strict_types=1);

// The single web entry point (ENG-09, docs/ARCHITECTURE.md). Apache serves files
// that exist under public/ directly and rewrites every other request here; the
// route table is config/routes.php. The handler is required at file scope so it
// runs exactly as it did from its former public/ entry script.
$atherApplicationRoot = dirname(__DIR__);
if (is_file($atherApplicationRoot . '/app/config/routes.php')) {
    $atherApplicationRoot .= '/app';
}
require_once $atherApplicationRoot . '/includes/autoload.php';

$atherHandler = Ather\Http\FrontController::resolve($atherApplicationRoot, require $atherApplicationRoot . '/config/routes.php');
unset($atherApplicationRoot);
if ($atherHandler !== null) {
    require $atherHandler;
}
