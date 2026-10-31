<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/legal_presentation.php';
require_once dirname(__DIR__) . '/includes/http.php';

if (!legalPagesPublished()) {
    http_response_code(404);
    require __DIR__ . '/not_found.php';
    return;
}
httpRequireMethod(['GET', 'HEAD']);
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
header('Cache-Control: public, max-age=300');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') { return; }
renderLegalDocumentPage('terms', $locale);
