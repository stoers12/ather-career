<?php
require_once __DIR__ . '/includes/admin_session.php';
require_once __DIR__ . '/includes/csrf.php';

startAdminSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!isAdminAuthenticated() || !isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid request.');
}

destroyAdminSession();

header('Location: login.php');
exit;
