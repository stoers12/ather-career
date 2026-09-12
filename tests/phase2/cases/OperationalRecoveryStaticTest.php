<?php

declare(strict_types=1);

final class OperationalRecoveryStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $recovery = self::read('scripts/stage4c-recovery.php');
        $backupWrapper = self::read('scripts/backup-production.sh');
        $restoreWrapper = self::read('scripts/restore-production.sh');
        $fixture = self::read('scripts/stage4c-dr-fixture.php');
        $negative = self::read('scripts/stage4c-negative-recovery-tests.php');
        $rehearsal = self::read('scripts/run-stage4c-dr-rehearsal.ps1');
        $documentation = self::read('docs/STAGE4C_RECOVERY_OPERATIONS.md');
        $productionCompose = self::read('docker-compose.production.yml');
        $developmentCompose = self::read('docker-compose.yml');
        $ownerCompose = self::read('docker-compose.owner-https.yml');
        $productionDockerfile = self::read('Dockerfile.production');
        $dockerignore = self::read('.dockerignore');

        phase2Assert(str_contains($recovery, "const STAGE4C_BACKUP_FORMAT = 'ather-career-stage4c-backup'")
            && str_contains($recovery, 'const STAGE4C_BACKUP_VERSION = 1'), 'Stage-4C backup manifest format/version is missing.');
        foreach (['--single-transaction', '--skip-lock-tables', '--routines', '--events', '--triggers', '--default-character-set=utf8mb4', '--hex-blob', '--no-tablespaces'] as $option) {
            phase2Assert(str_contains($recovery, $option), "Consistent logical dump option {$option} is missing.");
        }
        phase2Assert(str_contains($recovery, 'stage4cCreateStagingDirectory') && str_contains($recovery, 'stage4cDeleteGeneratedTree') && str_contains($recovery, '@rename($staging, $finalDirectory)'), 'Backup atomic finalization and incomplete-output cleanup are missing.');
        phase2Assert(str_contains($recovery, 'STAGE4C_QUIESCED_CONFIRMATION') && str_contains($recovery, 'Database or recognized managed-media state changed during the backup boundary.'), 'Quiesced-state and media consistency boundary are missing.');
        phase2Assert(str_contains($recovery, 'stage4cManagedMediaKey') && str_contains($recovery, 'Managed media contains an unsupported symbolic link.') && str_contains($recovery, 'stage4cValidateArchiveEntries'), 'Approved media-root and archive-entry enforcement is missing.');
        phase2Assert(str_contains($recovery, 'database_media_references') && str_contains($recovery, 'A database-referenced managed-media file is missing.') && str_contains($recovery, 'unreferenced_recognized_media_count'), 'Database/media reference consistency metadata is incomplete.');
        phase2Assert(str_contains($recovery, 'sessions\' => \'excluded\'') && str_contains($recovery, 'rate_limit_state\' => \'excluded\'') && str_contains($recovery, 'runtime_logs\' => \'excluded\''), 'Transient state exclusions are not explicit.');
        phase2Assert(str_contains($recovery, 'Production backup requires established encryption') && str_contains($recovery, "'unencrypted-disposable'") && str_contains($recovery, "'age'") && str_contains($recovery, "'gpg'"), 'Production encryption fail-closed behavior is incomplete.');
        phase2Assert(str_contains($recovery, 'stage4cRequireEmptyMediaTarget') && str_contains($recovery, 'STAGE4C_RESTORE_DATABASE_PATTERN') && str_contains($recovery, 'information_schema.processlist'), 'Empty-target, approved-namespace, and running-target restore refusals are incomplete.');
        phase2Assert(str_contains($recovery, 'Database dump checksum mismatch.') && str_contains($recovery, 'Media archive checksum mismatch.') && str_contains($recovery, 'The restored migration ledger is incompatible with the backup manifest.'), 'Restore checksum and migration compatibility checks are missing.');
        phase2Assert(!str_contains($recovery, 'DROP DATABASE') && !str_contains($recovery, 'docker compose') && !str_contains($recovery, 'rm -rf'), 'Recovery tooling must not discover or destructively replace a live Compose deployment.');
        phase2Assert(str_contains($backupWrapper, 'stage4c-recovery.php" backup') && str_contains($restoreWrapper, 'stage4c-recovery.php" restore'), 'Legacy operational entry points do not delegate to the safe Stage-4C tool.');
        phase2Assert(str_contains($fixture, 'ATHERCAR_STAGE4C_DR') && str_contains($fixture, "['projects'] < 7") && str_contains($fixture, "['skills'] < 5") && str_contains($fixture, "['experiences'] < 4") && str_contains($fixture, "['messages'] < 1"), 'The isolated DR fixture is not contractually representative.');
        foreach (['truncated database dump', 'modified database checksum', 'modified media checksum', 'missing archive', 'missing referenced media', 'extra unrecognized archive entry', 'path traversal archive name', 'escaping symbolic link', 'unsupported manifest version', 'incompatible migration state', 'non-empty restore target', 'running or wrong database target', 'interrupted backup staging', 'interrupted restore staging', 'unencrypted artifact in production mode', 'missing encryption recipient'] as $case) {
            phase2Assert(str_contains($negative, "'{$case}'"), "Negative recovery coverage is missing {$case}.");
        }
        phase2Assert(str_contains($rehearsal, "'up', '--detach', '--no-build'") && str_contains($rehearsal, "'down', '--volumes', '--remove-orphans'") && str_contains($rehearsal, 'ATHERCAR_STAGE4C_DR_PROJECT') && str_contains($rehearsal, 'stage4c-negative-recovery-tests.php'), 'The isolated source/destroy/restore rehearsal is incomplete.');
        foreach ([$productionCompose, $developmentCompose, $ownerCompose] as $compose) {
            phase2Assert(str_contains($compose, 'no-new-privileges:true'), 'Compose service hardening omits no-new-privileges.');
            phase2Assert(!preg_match('/(?im)^\s*privileged\s*:\s*true\s*$/', $compose) && !str_contains($compose, '/var/run/docker.sock'), 'Compose configuration requests a prohibited privileged capability or Docker socket mount.');
        }
        phase2Assert(str_contains($productionDockerfile, 'default-mysql-client') && !str_contains($productionDockerfile, 'curl | sh'), 'The production recovery image lacks the reviewed MySQL client or installs an unreviewed tool.');
        phase2Assert(str_contains($dockerignore, '.env.*') && str_contains($dockerignore, '!.env.example'), 'The production image must include the non-secret environment template while excluding local environment files.');
        phase2Assert(!preg_match('/(?i)(?:password|secret|token)\s*=\s*[\'\"][^\$][^\'\"]{8,}[\'\"]/', $recovery), 'Recovery source appears to contain a hard-coded sensitive value.');
        phase2Assert(stripos($documentation, 'retention') !== false
            && str_contains($documentation, 'Stage-5')
            && preg_match('/never restores sessions,\s+rate-limit state,\s+logs,\s+locks,\s+or test cookies/i', $documentation) === 1, 'Recovery operations documentation is incomplete.');
    }

    private static function read(string $path): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
        phase2Assert(is_string($contents), "{$path} is unreadable.");
        return $contents;
    }
}
