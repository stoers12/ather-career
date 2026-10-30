<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Message read state (B-03, docs/contracts/owner-messages.md). MySQL behavior: run-phase2-owner-flow-rehearsal.php. */
final class OwnerMessagesReadStateTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_flow.php';
    }

    public function testUnreadCountIsAbsentWithoutAnAuthorizedOwner(): void
    {
        self::assertNull(ownerWorkspaceUnreadMessageCount(), 'No badge may render before the request authorized an Owner');
    }

    public function testMigrationIsGuardedAndBackfillsExistingMessagesAsRead(): void
    {
        $runner = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/database/migrate.php');
        self::assertStringContainsString("\$migration['version'] === '025' && \$migration['name'] === 'message_read_state') { executeMessageReadStateMigration(\$database); return; }", $runner);
        self::assertFileExists(PHASE2_REPOSITORY_ROOT . '/database/migrations/025_message_read_state.sql');

        $migration = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/database/message_read_state.php');
        self::assertStringContainsString("fetchColumnDefinition(\$database, 'messages', 'read_at') === null", $migration, 'A retry must not repeat the DDL');
        self::assertStringContainsString('UPDATE messages SET read_at = created_at WHERE read_at IS NULL', $migration, 'Messages from before the feature are read (owner decision)');
    }

    public function testHandlerWritesOnlyThroughCsrfPostOrAGetThatOpensAMessage(): void
    {
        $handler = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/handlers/owner_messages.php');
        $order = [
            'requireOwnerPortfolioContext($database)',
            "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {",
            "requireValidCsrfToken(\$_POST['csrf_token'] ?? null);",
            "!== 'mark_unread'",
            'setAuthorizedMessageRead($database, $context, $messageId, false)',
            "httpRedirect('owner_messages.php');",
        ];
        $position = -1;
        foreach ($order as $step) {
            $next = strpos($handler, $step);
            self::assertNotFalse($next, "Missing: {$step}");
            self::assertGreaterThan($position, $next, "{$step} runs out of order");
            $position = $next;
        }
        self::assertStringContainsString(
            "} elseif (\$_SERVER['REQUEST_METHOD'] === 'GET') {\n            // Opening a message reads it (owner decision, B-03). HEAD never writes.\n            setAuthorizedMessageRead(\$database, \$context, (int) \$selectedMessage['id'], true);",
            $handler,
            'Only GET of an owned message marks it read',
        );
        self::assertSame(2, substr_count($handler, 'setAuthorizedMessageRead('), 'No other write path');
    }
}
