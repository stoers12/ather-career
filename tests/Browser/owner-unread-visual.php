<?php

declare(strict_types=1);

// Disposable browser-gate database only; never exposed by the production router.
if (getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1'
    || getenv('EVIDENCE_HUB_VISUAL_TEST') !== '1'
    || !preg_match('/^ather_career_test_[a-f0-9]+$/D', (string) getenv('DB_NAME'))) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_flow.php';
require_once __DIR__ . '/../../includes/csrf.php';
startOwnerSession();
httpRequireMethod(['POST']);
$database = getDatabaseConnection();
$context = requireOwnerPortfolioContext($database);
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$action = $_POST['action'] ?? '';
if ($action === 'seed' && !isset($_SESSION['owner_unread_visual_id'])) {
    $statement = $database->prepare('INSERT INTO messages (recipient_portfolio_id,name,email,message,read_at) VALUES (?, ?, ?, ?, NULL)');
    $statement->execute([$context->portfolioId, 'Synthetic FE-02 unread message', 'fe02@example.invalid', 'Disposable unread badge check.']);
    $_SESSION['owner_unread_visual_id'] = (int) $database->lastInsertId();
} elseif ($action === 'cleanup') {
    $statement = $database->prepare('DELETE FROM messages WHERE id = ? AND recipient_portfolio_id = ? AND email = ?');
    $statement->execute([$_SESSION['owner_unread_visual_id'] ?? 0, $context->portfolioId, 'fe02@example.invalid']);
    unset($_SESSION['owner_unread_visual_id']);
} else {
    http_response_code(400);
    exit;
}
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['id' => $_SESSION['owner_unread_visual_id'] ?? null], JSON_THROW_ON_ERROR);
