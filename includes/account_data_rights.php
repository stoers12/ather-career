<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/auth0_management.php';
require_once __DIR__ . '/security_events.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/zip_stream.php';

/*
 * Data rights (L-03, P-05; contract docs/contracts/account-data-rights.md).
 *
 * Export: every row the account owns, as JSON, a readable HTML copy and the
 * original images, streamed as one ZIP and never stored on the server.
 *
 * Deletion: a request hides the public page at once and signs the account out
 * everywhere (authz_version + 1). The owner can sign in and cancel for
 * ACCOUNT_DELETION_GRACE_DAYS. After that, purgeDueAccountDeletions() deletes
 * the Auth0 user, the media and every row, and keeps only account_deletions.
 *
 * Both actions need a sign-in from the last ACCOUNT_RECENT_AUTHENTICATION_SECONDS.
 */
const ACCOUNT_DELETION_GRACE_DAYS = 30;
const ACCOUNT_RECENT_AUTHENTICATION_SECONDS = 600;
const ACCOUNT_DELETION_UNPUBLISHED_PHRASE = 'delete';
const ACCOUNT_EXPORT_RATE_LIMIT_ATTEMPTS = 5;
const ACCOUNT_EXPORT_RATE_LIMIT_WINDOW_SECONDS = 3600;

/** Tables keyed by the user id, in export order. */
const ACCOUNT_DATA_USER_TABLES = [
    'users' => 'id',
    'user_identities' => 'user_id',
    'policy_acceptances' => 'user_id',
    'onboarding_attestations' => 'user_id',
    'onboarding_drafts' => 'user_id',
    'onboarding_completions' => 'user_id',
    'portfolios' => 'owner_user_id',
];

/** Tables keyed by the portfolio id, in export order. */
const ACCOUNT_DATA_PORTFOLIO_TABLES = [
    'personal_info' => 'portfolio_id',
    'projects' => 'portfolio_id',
    'project_evidence_sources' => 'portfolio_id',
    'experiences' => 'portfolio_id',
    'skills' => 'portfolio_id',
    'messages' => 'recipient_portfolio_id',
    'recommendation_dispositions' => 'portfolio_id',
    'technology_owner_mappings' => 'portfolio_id',
    'technology_proposals' => 'portfolio_id',
];

/**
 * Tables that reference an account but are neither exported nor deleted. The
 * invitation row is the operator's record of a spent code: its link to the user
 * is cleared by the foreign key, and the purge also clears its label.
 */
const ACCOUNT_DATA_EXCLUDED_TABLES = ['registration_invitations'];

/** Children first, so every RESTRICT foreign key is satisfied. */
const ACCOUNT_PURGE_ORDER = [
    'onboarding_completions', 'project_evidence_sources', 'projects', 'experiences', 'skills', 'messages',
    'recommendation_dispositions', 'technology_owner_mappings', 'technology_proposals', 'personal_info',
    'portfolios', 'onboarding_attestations', 'onboarding_drafts', 'policy_acceptances', 'user_identities', 'users',
];

/** Collections exported as files: the originals, not the derived display copies. */
const ACCOUNT_EXPORT_MEDIA_COLLECTIONS = ['profile_original', 'projects'];

final class AccountDataRightsException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Account data rights: ' . $reason);
    }
}

/** True when the current session's sign-in is recent enough for export or deletion. */
function accountSessionIsRecentlyAuthenticated(?int $now = null): bool
{
    $expiresAt = accountRecentAuthenticationExpiresAt();

    return $expiresAt !== null && ($now ?? time()) <= $expiresAt;
}

/** Unix time until which the current sign-in counts as recent, or null without a session. */
function accountRecentAuthenticationExpiresAt(): ?int
{
    $session = currentInternalUserSession();

    return $session === null ? null : $session['authenticated_at'] + ACCOUNT_RECENT_AUTHENTICATION_SECONDS;
}

/**
 * The schedule as UTC ISO 8601 times, independent of the connection time zone.
 *
 * @return array{requested_at: string, due_at: string}|null
 */
function accountDeletionSchedule(PDO $database, int $userId): ?array
{
    $statement = $database->prepare(
        'SELECT UNIX_TIMESTAMP(deletion_requested_at) AS requested_at, UNIX_TIMESTAMP(deletion_due_at) AS due_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || $row['requested_at'] === null || $row['due_at'] === null) {
        return null;
    }

    return ['requested_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $row['requested_at']), 'due_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $row['due_at'])];
}

/** What the owner types to confirm deletion: the public slug, or a fixed word before one is chosen. */
function accountDeletionConfirmationPhrase(PDO $database, int $userId): string
{
    $statement = $database->prepare('SELECT public_slug FROM portfolios WHERE owner_user_id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $slug = $statement->fetchColumn();

    return is_string($slug) && $slug !== '' ? $slug : ACCOUNT_DELETION_UNPUBLISHED_PHRASE;
}

/**
 * Schedules deletion and signs the account out everywhere. The caller ends the
 * current session afterwards.
 *
 * @return array{requested_at: string, due_at: string}
 * @throws AccountDataRightsException reauthentication_required | confirmation_mismatch | already_scheduled
 */
function requestAccountDeletion(PDO $database, AuthenticatedUserContext $user, mixed $confirmation, ?int $now = null): array
{
    if (!accountSessionIsRecentlyAuthenticated($now)) {
        throw new AccountDataRightsException('reauthentication_required');
    }
    $database->beginTransaction();
    try {
        $lock = $database->prepare("SELECT deletion_requested_at FROM users WHERE id = :id AND account_status = 'active' FOR UPDATE");
        $lock->execute(['id' => $user->userId]);
        $current = $lock->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current)) {
            throw new AuthorizationDeniedException('Account is not active.');
        }
        if ($current['deletion_requested_at'] !== null) {
            throw new AccountDataRightsException('already_scheduled');
        }
        $typed = is_string($confirmation) ? strtolower(trim($confirmation)) : '';
        if (!hash_equals(accountDeletionConfirmationPhrase($database, $user->userId), $typed)) {
            throw new AccountDataRightsException('confirmation_mismatch');
        }
        $update = $database->prepare(
            'UPDATE users
             SET deletion_requested_at = CURRENT_TIMESTAMP,
                 deletion_due_at = CURRENT_TIMESTAMP + INTERVAL ' . ACCOUNT_DELETION_GRACE_DAYS . ' DAY,
                 authz_version = authz_version + 1
             WHERE id = :id AND deletion_requested_at IS NULL'
        );
        $update->execute(['id' => $user->userId]);
        $schedule = accountDeletionSchedule($database, $user->userId);
        if ($update->rowCount() !== 1 || $schedule === null) {
            throw new RuntimeException('Account deletion could not be scheduled.');
        }
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        throw $exception;
    }
    reportSecurityEvent('account_deletion_request', 'success', ['internal_user_id' => $user->userId]);

    return $schedule;
}

/**
 * Cancels a scheduled deletion before it is due. Everything is as it was: the
 * deletion request changed nothing else.
 *
 * @throws AccountDataRightsException not_scheduled
 */
function cancelAccountDeletion(PDO $database, AuthenticatedUserContext $user): void
{
    $update = $database->prepare(
        "UPDATE users
         SET deletion_requested_at = NULL, deletion_due_at = NULL
         WHERE id = :id AND account_status = 'active'
           AND deletion_requested_at IS NOT NULL AND deletion_due_at > CURRENT_TIMESTAMP"
    );
    $update->execute(['id' => $user->userId]);
    if ($update->rowCount() !== 1) {
        throw new AccountDataRightsException('not_scheduled');
    }
    reportSecurityEvent('account_deletion_cancel', 'success', ['internal_user_id' => $user->userId]);
}

/**
 * The "Your data" section of Settings (contract: docs/contracts/account-data-rights.md).
 *
 * @return array{export_url: string, reauthenticate_url: string, deletion_url: string, recently_authenticated: bool,
 *     recent_until: ?int, confirmation_phrase: string, grace_days: int, notice: ?string}
 */
function ownerDataRightsSettingsData(PDO $database, AuthorizedPortfolioContext $context, mixed $notice): array
{
    return [
        'export_url' => '/owner_data_export.php',
        'reauthenticate_url' => '/owner_reauthenticate.php',
        'deletion_url' => '/owner_account_deletion.php',
        'recently_authenticated' => accountSessionIsRecentlyAuthenticated(),
        'recent_until' => accountRecentAuthenticationExpiresAt(),
        'confirmation_phrase' => accountDeletionConfirmationPhrase($database, $context->userId),
        'grace_days' => ACCOUNT_DELETION_GRACE_DAYS,
        'notice' => in_array($notice, ['reauthenticate', 'confirmation'], true) ? $notice : null,
    ];
}

/**
 * The page an account scheduled for deletion sees after signing in.
 *
 * @param array{requested_at: string, due_at: string} $schedule
 * @return array{locale: 'ar'|'en', requested_at: string, due_at: string, grace_days: int, cancel_url: string,
 *     export_url: string, reauthenticate_url: string, logout_url: string, recently_authenticated: bool, notice: ?string}
 */
function ownerAccountDeletionPageData(array $schedule, string $locale, mixed $notice): array
{
    return [
        'locale' => $locale === 'en' ? 'en' : 'ar',
        'requested_at' => $schedule['requested_at'],
        'due_at' => $schedule['due_at'],
        'grace_days' => ACCOUNT_DELETION_GRACE_DAYS,
        'cancel_url' => '/owner_account_deletion.php',
        'export_url' => '/owner_data_export.php',
        'reauthenticate_url' => '/owner_reauthenticate.php',
        'logout_url' => '/owner_logout.php',
        'recently_authenticated' => accountSessionIsRecentlyAuthenticated(),
        'notice' => $notice === 'reauthenticate' ? 'reauthenticate' : null,
    ];
}

function accountPortfolioId(PDO $database, int $userId): ?int
{
    $statement = $database->prepare('SELECT id FROM portfolios WHERE owner_user_id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);

    return authorizationPositiveInteger($statement->fetchColumn());
}

/**
 * Every row the account owns, keyed by table. Each query is bound to this user
 * or its own portfolio, so nothing of another account can appear.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function collectAccountData(PDO $database, int $userId): array
{
    $data = [];
    foreach (ACCOUNT_DATA_USER_TABLES as $table => $column) {
        $data[$table] = accountTableRows($database, $table, $column, $userId);
    }
    $portfolioId = accountPortfolioId($database, $userId);
    foreach (ACCOUNT_DATA_PORTFOLIO_TABLES as $table => $column) {
        $data[$table] = $portfolioId === null ? [] : accountTableRows($database, $table, $column, $portfolioId);
    }

    return $data;
}

/** @return list<array<string, mixed>> */
function accountTableRows(PDO $database, string $table, string $column, int $id): array
{
    // Table and column names come only from the constants above.
    $statement = $database->prepare('SELECT * FROM `' . $table . '` WHERE `' . $column . '` = :id');
    $statement->execute(['id' => $id]);

    return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
}

/** @return list<array{name: string, path: string}> */
function accountExportMediaFiles(?int $portfolioId): array
{
    if ($portfolioId === null) {
        return [];
    }
    $files = [];
    foreach (ACCOUNT_EXPORT_MEDIA_COLLECTIONS as $collection) {
        $probe = managedMediaKey($portfolioId, $collection, 'probe');
        $directory = $probe === null ? null : requirePrivateStorageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, dirname($probe));
        if ($directory === null || !is_dir($directory)) {
            continue;
        }
        $entries = scandir($directory);
        foreach ($entries === false ? [] : $entries as $filename) {
            $path = $directory . DIRECTORY_SEPARATOR . $filename;
            if (managedMediaKey($portfolioId, $collection, $filename) !== null && is_file($path) && !is_link($path)) {
                $files[] = ['name' => 'images/' . PRIVATE_MEDIA_COLLECTIONS[$collection][0] . '/' . $filename, 'path' => $path];
            }
        }
    }

    return $files;
}

/**
 * Writes the export ZIP to $output: data.json, readable.html and images/.
 *
 * @param resource $output
 */
function writeAccountExport(PDO $database, int $userId, string $locale, $output, ?int $now = null): void
{
    $data = collectAccountData($database, $userId);
    $exportedAt = gmdate('Y-m-d\TH:i:s\Z', $now ?? time());
    $document = ['format' => 'ather-account-export', 'version' => 1, 'exported_at' => $exportedAt, 'data' => $data];
    $zip = new ZipStreamWriter($output, $now);
    $zip->addFromString('data.json', json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    $zip->addFromString('readable.html', renderAccountExportReadableCopy($data, $locale, $exportedAt));
    foreach (accountExportMediaFiles(accountPortfolioId($database, $userId)) as $file) {
        $zip->addFromPath($file['name'], $file['path']);
    }
    $zip->finish();
}

/** @param array<string, list<array<string, mixed>>> $data */
function renderAccountExportReadableCopy(array $data, string $locale, string $exportedAt): string
{
    $ar = $locale === 'ar';
    $titles = [
        'users' => ['Account', 'الحساب'], 'user_identities' => ['Sign-in identities', 'هويات تسجيل الدخول'],
        'policy_acceptances' => ['Accepted policies', 'السياسات المقبولة'], 'onboarding_attestations' => ['Onboarding confirmations', 'إقرارات الإعداد'],
        'onboarding_drafts' => ['Onboarding draft', 'مسودة الإعداد'], 'onboarding_completions' => ['Onboarding completion', 'إكمال الإعداد'],
        'portfolios' => ['Page', 'الصفحة'], 'personal_info' => ['Profile', 'الملف الشخصي'], 'projects' => ['Projects', 'المشاريع'],
        'project_evidence_sources' => ['Evidence sources', 'مصادر الأدلة'], 'experiences' => ['Experience', 'الخبرات'],
        'skills' => ['Skills', 'المهارات'], 'messages' => ['Messages received', 'الرسائل الواردة'],
        'recommendation_dispositions' => ['Recommendation choices', 'قرارات التوصيات'],
        'technology_owner_mappings' => ['Technology choices', 'اختيارات التقنيات'], 'technology_proposals' => ['Technology proposals', 'اقتراحات التقنيات'],
    ];
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $heading = $ar ? 'بياناتك في أثر' : 'Your data on Ather';
    $html = '<!DOCTYPE html><html lang="' . ($ar ? 'ar' : 'en') . '" dir="' . ($ar ? 'rtl' : 'ltr') . '"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $heading . '</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:60rem;margin:2rem auto;padding:0 1rem;line-height:1.5}'
        . 'table{border-collapse:collapse;width:100%;margin-bottom:1.5rem}th,td{border:1px solid #ccc;padding:.4rem;text-align:start;vertical-align:top}'
        . 'th{width:14rem;background:#f4f4f4}td{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body>'
        . '<h1>' . $heading . '</h1><p>' . ($ar ? 'تاريخ التصدير: ' : 'Exported: ') . $escape($exportedAt) . '</p>'
        . '<p>' . ($ar ? 'النسخة الكاملة القابلة للقراءة آلياً في data.json، والصور في مجلد images.' : 'The complete machine-readable copy is data.json; images are in the images folder.') . '</p>';
    foreach ($data as $table => $rows) {
        if ($rows === []) {
            continue;
        }
        $html .= '<h2>' . $escape($titles[$table][$ar ? 1 : 0] ?? $table) . '</h2>';
        foreach ($rows as $row) {
            $html .= '<table>';
            foreach ($row as $field => $value) {
                $text = $value === null ? '—' : (is_scalar($value) ? (string) $value : (string) json_encode($value));
                $html .= '<tr><th scope="row">' . $escape((string) $field) . '</th><td>' . $escape(mb_scrub($text, 'UTF-8')) . '</td></tr>';
            }
            $html .= '</table>';
        }
    }

    return $html . '</body></html>';
}

/**
 * Permanently deletes every account whose grace period has ended.
 *
 * @return array{deleted: list<int>, failed: list<int>}
 */
function purgeDueAccountDeletions(PDO $database, AccountIdentityEraser $eraser, string $expectedIssuer, int $limit = 25): array
{
    $statement = $database->prepare('SELECT id FROM users WHERE deletion_due_at <= CURRENT_TIMESTAMP ORDER BY deletion_due_at, id LIMIT ' . max(1, min(500, $limit)));
    $statement->execute();
    $result = ['deleted' => [], 'failed' => []];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $userId = (int) $id;
        try {
            if (purgeAccount($database, $eraser, $expectedIssuer, $userId)) {
                $result['deleted'][] = $userId;
            }
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            $reason = $exception instanceof Auth0ManagementException ? $exception->safeReason : 'purge_failure';
            reportSecurityEvent('account_deletion_purge', 'failed', ['internal_user_id' => $userId, 'reason' => $reason]);
            $result['failed'][] = $userId;
        }
    }

    return $result;
}

/**
 * Deletes one due account. Each step can be retried: a failure leaves the
 * account disabled and due, and the next run continues from there.
 * False when the account is no longer due (it was cancelled in time).
 */
function purgeAccount(PDO $database, AccountIdentityEraser $eraser, string $expectedIssuer, int $userId): bool
{
    // 1. Close the account: no sign-in, no cancel and no session from here on.
    $database->beginTransaction();
    $lock = $database->prepare('SELECT account_status, deletion_requested_at FROM users WHERE id = :id AND deletion_due_at <= CURRENT_TIMESTAMP FOR UPDATE');
    $lock->execute(['id' => $userId]);
    $user = $lock->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $database->rollBack();
        return false;
    }
    if ($user['account_status'] !== 'disabled') {
        $database->prepare("UPDATE users SET account_status = 'disabled', authz_version = authz_version + 1 WHERE id = :id")->execute(['id' => $userId]);
    }
    $database->commit();

    // 2. The Auth0 account of every identity issued by our tenant.
    $identities = $database->prepare(
        'SELECT oidc_issuer, oidc_subject FROM users WHERE id = :user_id
         UNION SELECT oidc_issuer, oidc_subject FROM user_identities WHERE user_id = :identity_user_id'
    );
    $identities->execute(['user_id' => $userId, 'identity_user_id' => $userId]);
    foreach ($identities->fetchAll(PDO::FETCH_ASSOC) as $identity) {
        if (hash_equals($expectedIssuer, (string) $identity['oidc_issuer'])) {
            $eraser->eraseUser((string) $identity['oidc_subject']);
        }
    }

    // 3. Images and every other file of the portfolio.
    $portfolioId = accountPortfolioId($database, $userId);
    if ($portfolioId !== null) {
        deletePortfolioStorageDirectory($portfolioId);
    }

    // 4. Every row, and the one record that the account existed.
    $database->beginTransaction();
    try {
        $database->prepare('UPDATE registration_invitations SET label = NULL WHERE used_by_user_id = :id')->execute(['id' => $userId]);
        foreach (ACCOUNT_PURGE_ORDER as $table) {
            $column = ACCOUNT_DATA_USER_TABLES[$table] ?? ACCOUNT_DATA_PORTFOLIO_TABLES[$table];
            $key = isset(ACCOUNT_DATA_USER_TABLES[$table]) ? $userId : $portfolioId;
            if ($key !== null) {
                $database->prepare('DELETE FROM `' . $table . '` WHERE `' . $column . '` = :id')->execute(['id' => $key]);
            }
        }
        $database->prepare('INSERT INTO account_deletions (user_id, requested_at) VALUES (:id, :requested_at)')
            ->execute(['id' => $userId, 'requested_at' => $user['deletion_requested_at']]);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        throw $exception;
    }
    reportSecurityEvent('account_deletion_purge', 'success', ['internal_user_id' => $userId]);

    return true;
}

/** Removes storage/portfolios/{id} and everything under it. */
function deletePortfolioStorageDirectory(int $portfolioId): void
{
    $root = requirePrivateStorageRoot(true);
    $directory = $root . DIRECTORY_SEPARATOR . 'portfolios' . DIRECTORY_SEPARATOR . $portfolioId;
    if (!file_exists($directory) && !is_link($directory)) {
        return;
    }
    $resolved = realpath($directory);
    if ($resolved === false || is_link($directory) || !filesystemPathIsWithin($resolved, $root . DIRECTORY_SEPARATOR . 'portfolios')) {
        throw new RuntimeException('Portfolio storage directory is unsafe to delete.');
    }
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        /** @var SplFileInfo $entry */
        $ok = $entry->isDir() && !$entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        if (!$ok) {
            throw new RuntimeException('Portfolio storage could not be deleted.');
        }
    }
    if (!@rmdir($resolved)) {
        throw new RuntimeException('Portfolio storage could not be deleted.');
    }
}
