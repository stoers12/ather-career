<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/owner_publication_presentation.php';
require_once __DIR__ . '/../../includes/owner_messages_presentation.php';
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
$locale = ownerLocale();
$_COOKIE['ather_owner_locale'] = $locale;
$page = ($_GET['page'] ?? '') === 'messages' ? 'messages' : 'publication';
$variant = $_GET['state'] ?? 'published';
if ($page === 'publication' && $variant === 'configuration') http_response_code(503);
$_SERVER['REQUEST_URI'] = '/owner_' . $page . '.php?lang=' . $locale;
ownerLayoutStart($page === 'messages' ? 'Messages' : 'Publication', $page, true, ['account_name' => 'Sami Ahmad']);
if ($page === 'publication') {
    $state = ['public_slug' => $variant === 'no_slug' ? null : 'synthetic-portfolio', 'is_published' => in_array($variant, ['published', 'configuration'], true) ? 1 : 0, 'published_at' => in_array($variant, ['offline', 'published', 'configuration'], true) ? '2026-10-01 12:00:00' : null];
    $errors = $variant === 'error' ? ['Use 3–64 lowercase letters, numbers, and single hyphens.'] : [];
    if ($variant === 'configuration') { http_response_code(503); $errors = ['PUBLIC_BASE_URL configuration is unavailable.']; }
    $url = $variant === 'configuration' || $variant === 'no_slug' ? null : 'https://portfolio.example.test/p/synthetic-portfolio';
    renderOwnerPublicationPage($state, $url, '', $errors, $variant === 'error' ? ['public_slug' => $errors[0]] : [], $variant === 'error' ? 'Rejected <slug> & retained' : null);
} else {
    $messages = [
        ['id' => 31, 'name' => 'مرسل اختباري', 'email' => 'sender+portfolio@example.test', 'message' => "مرحبًا، اطّلعت على أعمالك وأرغب في معرفة المزيد عن خبرتك.\n\nهل يمكننا مناقشة فرصة تعاون؟", 'read_at' => null],
        ['id' => 30, 'name' => 'Example sender', 'email' => 'other@example.test', 'message' => 'An earlier conversation.', 'read_at' => '2026-10-01 12:00:00'],
    ];
    if ($variant === 'empty') $messages = [];
    renderOwnerMessagesPage($messages, $variant === 'selected' ? $messages[0] : null, '', false);
}
ownerLayoutEnd();
