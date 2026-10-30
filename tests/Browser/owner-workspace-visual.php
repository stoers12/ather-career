<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/owner_dashboard_presentation.php';
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
$locale = ownerLocale();
$_COOKIE['ather_owner_locale'] = $locale;
$state = $_GET['state'] ?? 'preview';
$data = [
    'owner_name' => 'Sami Ahmad', 'first_name' => $locale === 'ar' ? 'سامي' : 'Sami',
    'overview' => ['total' => 4, 'published' => 4, 'drafts' => 0, 'needs_writing' => 0, 'recent' => [
        ['id' => 3, 'title' => 'HR Dashboard', 'category' => 'Data Analysis', 'published' => true, 'written_parts' => 3],
        ['id' => 2, 'title' => 'Portfolio Platform View', 'category' => 'Full Stack Development', 'published' => true, 'written_parts' => 3],
        ['id' => 1, 'title' => 'CareerFit AI', 'category' => 'Machine Learning / AI', 'published' => true, 'written_parts' => 3],
    ]],
    'next_step' => 'preview', 'publication_state' => 'published', 'public_url' => 'https://portfolio.example.test/p/sami',
];
if ($state === 'empty') {
    $data['overview'] = ['total' => 0, 'published' => 0, 'drafts' => 0, 'needs_writing' => 0, 'recent' => []];
    $data['next_step'] = 'first_project';
    $data['publication_state'] = 'no_slug';
    $data['public_url'] = null;
} elseif ($state === 'write_evidence' || $state === 'publish_drafts') {
    $data['next_step'] = $state;
    $data['overview']['needs_writing'] = $state === 'write_evidence' ? 2 : 0;
    $data['overview']['drafts'] = 2;
    $data['overview']['published'] = 2;
    $data['overview']['recent'][0]['published'] = false;
    $data['publication_state'] = 'offline';
    $data['public_url'] = null;
} elseif ($state === 'long') {
    $data['owner_name'] = '<script>owner</script> ' . str_repeat('اسم Owner طويل ', 12);
    $data['first_name'] = '<script>owner</script>';
    $data['overview']['recent'][0]['title'] = str_repeat('مشروع Project طويل ', 12);
    $data['overview']['recent'][0]['category'] = str_repeat('مجال Category ', 12);
}
renderOwnerDashboard($state === 'unavailable' ? null : $data, $locale);
