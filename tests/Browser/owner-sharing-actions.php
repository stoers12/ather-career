<?php

declare(strict_types=1);

// Scoped synthetic gate data only. This file is outside the production document root.
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
if ($action === 'seed' && !isset($_SESSION['owner_sharing_visual'])) {
    $statement = $database->prepare('SELECT public_slug,is_published,published_at FROM portfolios WHERE id = ? AND owner_user_id = ?');
    $statement->execute([$context->portfolioId, $context->userId]);
    $state = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($state)) throw new RuntimeException('Synthetic gate portfolio missing.');
    $_SESSION['owner_sharing_visual'] = ['state' => $state];
    $statement = $database->prepare('UPDATE portfolios SET public_slug = NULL,is_published = 0,published_at = NULL WHERE id = ? AND owner_user_id = ?');
    $statement->execute([$context->portfolioId, $context->userId]);
    $statement = $database->prepare('INSERT INTO messages (recipient_portfolio_id,name,email,message,read_at) VALUES (?, ?, ?, ?, NULL)');
    $statement->execute([$context->portfolioId, 'Synthetic FE-06 <sender>', 'fe06+reply@example.invalid', "Synthetic <body> & text.\nSecond line."]);
    $_SESSION['owner_sharing_visual']['message_id'] = (int) $database->lastInsertId();
} elseif ($action === 'cleanup' && isset($_SESSION['owner_sharing_visual'])) {
    $fixture = $_SESSION['owner_sharing_visual'];
    $state = $fixture['state'];
    $statement = $database->prepare('UPDATE portfolios SET public_slug = ?,is_published = ?,published_at = ? WHERE id = ? AND owner_user_id = ?');
    $statement->execute([$state['public_slug'], $state['is_published'], $state['published_at'], $context->portfolioId, $context->userId]);
    $statement = $database->prepare('DELETE FROM messages WHERE id = ? AND recipient_portfolio_id = ? AND email = ?');
    $statement->execute([$fixture['message_id'], $context->portfolioId, 'fe06+reply@example.invalid']);
    unset($_SESSION['owner_sharing_visual']);
} else {
    http_response_code(400);
    exit;
}
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['id' => $_SESSION['owner_sharing_visual']['message_id'] ?? null], JSON_THROW_ON_ERROR);
