<?php

declare(strict_types=1);

/*
 * Stage-4C recovery tool.
 *
 * This is deliberately CLI-only. It never discovers a Docker project, never
 * defaults to a repository volume, and never performs an in-place restore.
 * Operators provide a narrowly scoped database name, storage root, and backup
 * directory for every invocation. Secrets are supplied only through an
 * already-provisioned environment variable or a MySQL defaults file; neither
 * source is serialized, echoed, or passed on a command line.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const STAGE4C_BACKUP_FORMAT = 'ather-career-stage4c-backup';
const STAGE4C_BACKUP_VERSION = 1;
const STAGE4C_MEDIA_FORMAT = 'ather-career-stage4c-media';
const STAGE4C_MEDIA_VERSION = 1;
const STAGE4C_QUIESCED_CONFIRMATION = 'I_CONFIRM_QUIESCED_STAGE4C';
const STAGE4C_RESTORE_DATABASE_PATTERN = '/^ather_(?:stage4c|career)_restore_[a-f0-9]{24}$/';

final class Stage4cRecoveryException extends RuntimeException
{
}

/** @return never */
function stage4cRefuse(string $reason): never
{
    throw new Stage4cRecoveryException($reason);
}

function stage4cSafeFailure(Throwable $exception): string
{
    if ($exception instanceof Stage4cRecoveryException) {
        return $exception->getMessage();
    }

    return 'The requested recovery operation did not complete safely.';
}

/** @return array{0: string, 1: array<string, string|bool>} */
function stage4cParseArguments(array $arguments): array
{
    $command = $arguments[1] ?? '';
    if (!is_string($command) || !in_array($command, ['backup', 'restore', 'verify', 'retention-plan'], true)) {
        stage4cRefuse('An allowed recovery command is required.');
    }

    $options = [];
    for ($index = 2, $count = count($arguments); $index < $count; ++$index) {
        $argument = $arguments[$index];
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            stage4cRefuse('Recovery arguments must use explicit named options.');
        }
        $argument = substr($argument, 2);
        if ($argument === '' || str_contains($argument, "\0")) {
            stage4cRefuse('A recovery option is malformed.');
        }
        if (str_contains($argument, '=')) {
            [$name, $value] = explode('=', $argument, 2);
        } else {
            $name = $argument;
            $next = $arguments[$index + 1] ?? null;
            if (is_string($next) && !str_starts_with($next, '--')) {
                $value = $next;
                ++$index;
            } else {
                $value = true;
            }
        }
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $name) !== 1 || array_key_exists($name, $options)) {
            stage4cRefuse('A recovery option is invalid or duplicated.');
        }
        if (!is_bool($value) && (str_contains($value, "\0") || strlen($value) > 4096)) {
            stage4cRefuse('A recovery option value is invalid.');
        }
        $options[$name] = $value;
    }

    return [$command, $options];
}

/** @param array<string, string|bool> $options */
function stage4cOption(array $options, string $name, bool $required = true): ?string
{
    $value = $options[$name] ?? null;
    if ($value === null) {
        if ($required) {
            stage4cRefuse("The --{$name} option is required.");
        }
        return null;
    }
    if (!is_string($value) || $value === '') {
        stage4cRefuse("The --{$name} option requires a value.");
    }

    return $value;
}

/** @param array<string, string|bool> $options */
function stage4cFlag(array $options, string $name): bool
{
    return ($options[$name] ?? false) === true;
}

function stage4cNormalizedPath(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    return rtrim($normalized, '/') === '' ? '/' : rtrim($normalized, '/');
}

function stage4cIsWithin(string $candidate, string $parent): bool
{
    $candidate = stage4cNormalizedPath($candidate);
    $parent = stage4cNormalizedPath($parent);
    return $candidate === $parent || str_starts_with($candidate . '/', $parent . '/');
}

function stage4cAbsoluteExistingDirectory(string $value, string $label): string
{
    if (!str_starts_with($value, '/') || str_contains($value, "\0")) {
        stage4cRefuse("The {$label} must be an explicit absolute directory.");
    }
    $resolved = realpath($value);
    if ($resolved === false || !is_dir($resolved) || is_link($value)) {
        stage4cRefuse("The {$label} is unavailable or unsafe.");
    }
    return stage4cNormalizedPath($resolved);
}

function stage4cAssertOperationalDirectory(string $path, string $label): void
{
    $path = stage4cNormalizedPath($path);
    $forbidden = ['/', stage4cNormalizedPath(getcwd() ?: '/')];
    $home = getenv('HOME');
    if (is_string($home) && $home !== '') {
        $resolvedHome = realpath($home);
        if ($resolvedHome !== false) {
            $forbidden[] = stage4cNormalizedPath($resolvedHome);
        }
    }
    if (in_array($path, $forbidden, true)) {
        stage4cRefuse("The {$label} is too broad for a recovery operation.");
    }
}

function stage4cRandomSuffix(): string
{
    return bin2hex(random_bytes(12));
}

function stage4cCreateDirectory(string $path, int $permissions = 0700): void
{
    if (!@mkdir($path, $permissions, false) && !is_dir($path)) {
        stage4cRefuse('A required recovery directory could not be created.');
    }
}

function stage4cCreateStagingDirectory(string $parent, string $prefix): string
{
    for ($attempt = 0; $attempt < 5; ++$attempt) {
        $candidate = $parent . '/.' . $prefix . '-' . stage4cRandomSuffix();
        if (@mkdir($candidate, 0700, false)) {
            return $candidate;
        }
    }
    stage4cRefuse('A unique recovery staging directory could not be created.');
}

function stage4cDeleteGeneratedTree(string $path, string $parent): void
{
    $resolvedParent = stage4cNormalizedPath(realpath($parent) ?: $parent);
    $normalized = stage4cNormalizedPath($path);
    if (!stage4cIsWithin($normalized, $resolvedParent) || $normalized === $resolvedParent || !is_dir($path)) {
        stage4cRefuse('Refusing to remove an unsafe recovery staging path.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entryPath = $entry->getPathname();
        if ($entry->isDir() && !$entry->isLink()) {
            if (!@rmdir($entryPath)) {
                stage4cRefuse('A generated recovery staging directory could not be removed.');
            }
        } elseif (!@unlink($entryPath)) {
            stage4cRefuse('A generated recovery staging file could not be removed.');
        }
    }
    if (!@rmdir($path)) {
        stage4cRefuse('A generated recovery staging root could not be removed.');
    }
}

function stage4cSafeFilename(mixed $value): bool
{
    return is_string($value) && preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $value) === 1;
}

function stage4cManagedMediaKey(mixed $value): bool
{
    return is_string($value)
        && preg_match('#^portfolios/[1-9][0-9]{0,9}/(?:profile/original|profile/presentation|projects|project/presentation)/[a-z0-9][a-z0-9._-]{0,127}$#', $value) === 1;
}

function stage4cHashFile(string $path): string
{
    if (!is_file($path) || is_link($path)) {
        stage4cRefuse('A required recovery file is unavailable or unsafe.');
    }
    $hash = hash_file('sha256', $path);
    if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        stage4cRefuse('A required recovery file could not be checksummed.');
    }
    return $hash;
}

/**
 * @return array{entries: list<array{path:string,size:int,sha256:string}>, content_hash:string, transient_locks:int, unrecognized_files:int}
 */
function stage4cScanManagedMedia(string $mediaRoot): array
{
    $entries = [];
    $transientLocks = 0;
    $unrecognizedFiles = 0;
    $rootLength = strlen($mediaRoot);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($mediaRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );
    foreach ($iterator as $entry) {
        $path = $entry->getPathname();
        $relative = ltrim(str_replace('\\', '/', substr($path, $rootLength)), '/');
        if ($entry->isLink()) {
            stage4cRefuse('Managed media contains an unsupported symbolic link.');
        }
        if (!$entry->isFile()) {
            stage4cRefuse('Managed media contains an unsupported filesystem entry.');
        }
        if (!stage4cManagedMediaKey($relative)) {
            ++$unrecognizedFiles;
            if (basename($relative) === '.quota.lock') {
                ++$transientLocks;
            }
            continue;
        }
        $size = $entry->getSize();
        if (!is_int($size) || $size < 1) {
            stage4cRefuse('A managed media file has an invalid size.');
        }
        $entries[] = ['path' => $relative, 'size' => $size, 'sha256' => stage4cHashFile($path)];
    }
    usort($entries, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
    $parts = [];
    foreach ($entries as $entry) {
        $parts[] = $entry['path'] . "\x1f" . $entry['sha256'];
    }
    return [
        'entries' => $entries,
        'content_hash' => hash('sha256', implode("\x1e", $parts)),
        'transient_locks' => $transientLocks,
        'unrecognized_files' => $unrecognizedFiles,
    ];
}

/** @param list<array{path:string,size:int,sha256:string}> $entries */
function stage4cMediaEntryMap(array $entries): array
{
    $map = [];
    foreach ($entries as $entry) {
        if (!stage4cManagedMediaKey($entry['path'] ?? null)
            || !is_int($entry['size'] ?? null)
            || (int) $entry['size'] < 1
            || !is_string($entry['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $entry['sha256']) !== 1
            || isset($map[$entry['path']])) {
            stage4cRefuse('The media manifest contains an invalid file entry.');
        }
        $map[$entry['path']] = $entry;
    }
    ksort($map, SORT_STRING);
    return $map;
}

function stage4cRequireStringOption(array $options, string $name, string $pattern, string $reason): string
{
    $value = stage4cOption($options, $name);
    if (preg_match($pattern, $value) !== 1) {
        stage4cRefuse($reason);
    }
    return $value;
}

/** @param array<string, string|bool> $options */
function stage4cDatabaseOptions(array $options, string $databaseOption = 'db-name'): array
{
    $host = stage4cRequireStringOption($options, 'db-host', '/^[A-Za-z0-9._:-]{1,255}$/', 'The database host is invalid.');
    $port = stage4cRequireStringOption($options, 'db-port', '/^[1-9][0-9]{0,4}$/', 'The database port is invalid.');
    if ((int) $port > 65535) {
        stage4cRefuse('The database port is invalid.');
    }
    $database = stage4cRequireStringOption($options, $databaseOption, '/^[A-Za-z0-9_]{1,64}$/', 'The database name is invalid.');
    $user = stage4cRequireStringOption($options, 'db-user', '/^[A-Za-z0-9_.-]{1,64}$/', 'The database user is invalid.');
    $passwordEnvironment = stage4cRequireStringOption($options, 'db-password-env', '/^[A-Z][A-Z0-9_]{2,127}$/', 'The database password environment variable name is invalid.');
    $password = getenv($passwordEnvironment);
    if (!is_string($password) || $password === '') {
        stage4cRefuse('The database credential is unavailable from its configured secret source.');
    }
    $defaultsFile = stage4cOption($options, 'mysql-defaults-file', false);
    if ($defaultsFile !== null && (!str_starts_with($defaultsFile, '/') || !is_file($defaultsFile) || is_link($defaultsFile) || !is_readable($defaultsFile))) {
        stage4cRefuse('The MySQL defaults file is unavailable or unsafe.');
    }
    return [
        'host' => $host,
        'port' => (int) $port,
        'database' => $database,
        'user' => $user,
        'password_environment' => $passwordEnvironment,
        'password' => $password,
        'defaults_file' => $defaultsFile,
    ];
}

/** @param array{host:string,port:int,database:string,user:string,password:string} $database */
function stage4cPdo(array $database, bool $includeDatabase = true): PDO
{
    $dsn = 'mysql:host=' . $database['host'] . ';port=' . $database['port'] . ';charset=utf8mb4';
    if ($includeDatabase) {
        $dsn .= ';dbname=' . $database['database'];
    }
    try {
        return new PDO($dsn, $database['user'], $database['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable) {
        stage4cRefuse('The explicit recovery database target is unavailable.');
    }
}

/** @return list<string> */
function stage4cDatabaseMediaReferences(PDO $database): array
{
    try {
        $references = [];
        foreach ([
            'SELECT profile_image_path FROM personal_info WHERE profile_image_path IS NOT NULL AND profile_image_path <> ""',
            'SELECT image_path FROM projects WHERE image_path IS NOT NULL AND image_path <> ""',
        ] as $query) {
            foreach ($database->query($query)->fetchAll(PDO::FETCH_COLUMN) as $key) {
                if (!stage4cManagedMediaKey($key) || isset($references[$key])) {
                    stage4cRefuse('The database contains an invalid managed-media reference.');
                }
                $references[$key] = true;
            }
        }
    } catch (Stage4cRecoveryException $exception) {
        throw $exception;
    } catch (Throwable) {
        stage4cRefuse('The database media-reference inventory could not be read.');
    }
    $keys = array_keys($references);
    sort($keys, SORT_STRING);
    return $keys;
}

/** @return array{count:int,ledger_hash:string,maximum_version:string} */
function stage4cMigrationSummary(PDO $database): array
{
    try {
        $ledger = $database->query('SELECT version, name FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        stage4cRefuse('The migration ledger could not be read.');
    }
    $normalized = [];
    foreach ($ledger as $row) {
        $version = $row['version'] ?? null;
        $name = $row['name'] ?? null;
        if (!is_string($version) || !is_string($name) || preg_match('/^[0-9]{3}$/', $version) !== 1 || preg_match('/^[a-z0-9][a-z0-9_-]{0,127}$/', $name) !== 1) {
            stage4cRefuse('The migration ledger is malformed.');
        }
        $normalized[] = $version . '_' . $name;
    }
    return [
        'count' => count($normalized),
        'ledger_hash' => hash('sha256', implode("\x1e", $normalized)),
        'maximum_version' => $normalized === [] ? 'none' : substr((string) end($normalized), 0, 3),
    ];
}

/** @return array<string, int> */
function stage4cCriticalCounts(PDO $database): array
{
    $counts = [];
    foreach (['users', 'personal_info', 'portfolios', 'projects', 'skills', 'experiences', 'messages', 'schema_migrations'] as $table) {
        try {
            $value = $database->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        } catch (Throwable) {
            stage4cRefuse('A critical database count could not be read.');
        }
        $counts[$table] = (int) $value;
    }
    return $counts;
}

function stage4cDatabaseVersion(PDO $database): string
{
    try {
        $version = $database->query('SELECT VERSION()')->fetchColumn();
    } catch (Throwable) {
        stage4cRefuse('The database engine version could not be read.');
    }
    if (!is_string($version) || $version === '' || strlen($version) > 128) {
        stage4cRefuse('The database engine version is invalid.');
    }
    return $version;
}

function stage4cExecutable(string $name): ?string
{
    $path = getenv('PATH');
    if (!is_string($path)) {
        return null;
    }
    foreach (explode(PATH_SEPARATOR, $path) as $directory) {
        $candidate = rtrim($directory, '/') . '/' . $name;
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/** @return array{exit:int,stdout:string,stderr:string} */
function stage4cRunCommand(array $command, ?string $workingDirectory = null, ?string $stdoutFile = null, ?string $stdinFile = null, ?array $environment = null): array
{
    if ($command === [] || !is_string($command[0]) || $command[0] === '') {
        stage4cRefuse('A recovery command is invalid.');
    }
    $descriptors = [
        0 => $stdinFile === null ? ['pipe', 'r'] : ['file', $stdinFile, 'rb'],
        1 => $stdoutFile === null ? ['pipe', 'w'] : ['file', $stdoutFile, 'wb'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $process = proc_open($command, $descriptors, $pipes, $workingDirectory, $environment, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        stage4cRefuse('A required recovery command could not be started.');
    }
    if ($stdinFile === null && isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }
    $stdout = '';
    if ($stdoutFile === null && isset($pipes[1]) && is_resource($pipes[1])) {
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    }
    $stderr = '';
    if (isset($pipes[2]) && is_resource($pipes[2])) {
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
    }
    $exit = proc_close($process);
    return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
}

function stage4cToolVersion(string $binary): string
{
    $path = stage4cExecutable($binary);
    if ($path === null) {
        return 'unavailable';
    }
    $result = stage4cRunCommand([$path, '--version']);
    if ($result['exit'] !== 0) {
        return 'unavailable';
    }
    $line = trim(strtok($result['stdout'], "\n") ?: '');
    return $line === '' ? 'available' : substr(preg_replace('/[\r\n]+/', ' ', $line) ?? 'available', 0, 160);
}

/** @param array{password_environment:string,password:string} $database */
function stage4cChildEnvironment(array $database): array
{
    $environment = $_ENV;
    $environment[$database['password_environment']] = $database['password'];
    $environment['MYSQL_PWD'] = $database['password'];
    return $environment;
}

/** @param array{host:string,port:int,database:string,user:string,defaults_file:?string,password_environment:string,password:string} $database */
function stage4cDumpDatabase(array $database, string $destination): void
{
    $binary = stage4cExecutable('mysqldump');
    if ($binary === null) {
        stage4cRefuse('The required MySQL dump client is unavailable.');
    }
    $command = [$binary];
    if ($database['defaults_file'] !== null) {
        $command[] = '--defaults-extra-file=' . $database['defaults_file'];
    }
    $command = [...$command,
        '--single-transaction', '--skip-lock-tables', '--routines', '--events', '--triggers',
        '--set-charset', '--default-character-set=utf8mb4', '--hex-blob', '--no-tablespaces',
        '--host=' . $database['host'], '--port=' . $database['port'], '--user=' . $database['user'],
        $database['database'],
    ];
    $result = stage4cRunCommand($command, null, $destination, null, stage4cChildEnvironment($database));
    clearstatcache(true, $destination);
    if ($result['exit'] !== 0 || !is_file($destination) || filesize($destination) < 1) {
        stage4cRefuse('The transaction-consistent database dump did not complete.');
    }
}

/** @param list<array{path:string,size:int,sha256:string}> $entries */
function stage4cCreateMediaArchive(string $mediaRoot, array $entries, string $destination, string $stagingDirectory): void
{
    $binary = stage4cExecutable('tar');
    if ($binary === null) {
        stage4cRefuse('The required archive tool is unavailable.');
    }
    $list = $stagingDirectory . '/media-files.list';
    $paths = array_map(static fn (array $entry): string => $entry['path'], $entries);
    if (@file_put_contents($list, implode("\0", $paths) . (count($paths) === 0 ? '' : "\0"), LOCK_EX) === false) {
        stage4cRefuse('The media archive file list could not be created.');
    }
    $result = stage4cRunCommand([
        $binary, '--create', '--gzip', '--no-recursion', '--format=posix', '--file=' . $destination, '--null', '--files-from=' . $list,
    ], $mediaRoot);
    @unlink($list);
    clearstatcache(true, $destination);
    if ($result['exit'] !== 0 || !is_file($destination) || filesize($destination) < 1) {
        stage4cRefuse('The managed-media archive did not complete.');
    }
}

/** @param list<string> $expectedPaths */
function stage4cValidateArchiveEntries(string $archive, array $expectedPaths): void
{
    $binary = stage4cExecutable('tar');
    if ($binary === null || !is_file($archive)) {
        stage4cRefuse('The media archive is unavailable for validation.');
    }
    $names = stage4cRunCommand([$binary, '-tzf', $archive]);
    $modes = stage4cRunCommand([$binary, '-tvzf', $archive]);
    if ($names['exit'] !== 0 || $modes['exit'] !== 0) {
        stage4cRefuse('The media archive cannot be read safely.');
    }
    $entries = array_values(array_filter(preg_split('/\r?\n/', trim($names['stdout'])) ?: [], static fn (string $value): bool => $value !== ''));
    if (count($entries) !== count($expectedPaths) || count(array_unique($entries)) !== count($entries)) {
        stage4cRefuse('The media archive has an unexpected entry set.');
    }
    foreach ($entries as $entry) {
        if (!stage4cManagedMediaKey($entry)) {
            stage4cRefuse('The media archive contains an unsafe or unrecognized entry.');
        }
    }
    sort($entries, SORT_STRING);
    $expected = $expectedPaths;
    sort($expected, SORT_STRING);
    if ($entries !== $expected) {
        stage4cRefuse('The media archive does not match its manifest.');
    }
    foreach (preg_split('/\r?\n/', trim($modes['stdout'])) ?: [] as $line) {
        if ($line === '' || $line[0] !== '-') {
            stage4cRefuse('The media archive contains an unsupported entry type.');
        }
    }
}

function stage4cWriteJson(string $path, array $value): void
{
    try {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable) {
        stage4cRefuse('Recovery metadata could not be encoded.');
    }
    if (@file_put_contents($path, $encoded, LOCK_EX) === false) {
        stage4cRefuse('Recovery metadata could not be written.');
    }
}

function stage4cReadJson(string $path, string $reason): array
{
    if (!is_file($path) || is_link($path) || filesize($path) < 2 || filesize($path) > 8_388_608) {
        stage4cRefuse($reason);
    }
    try {
        $value = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        stage4cRefuse($reason);
    }
    if (!is_array($value)) {
        stage4cRefuse($reason);
    }
    return $value;
}

/** @param array<string, string|bool> $options */
function stage4cEncryptionPlan(array $options, string $mode): array
{
    if (!in_array($mode, ['production', 'disposable-rehearsal'], true)) {
        stage4cRefuse('The backup mode is invalid.');
    }
    $method = stage4cOption($options, 'encrypt', false);
    $delegated = stage4cFlag($options, 'encrypted-storage-delegated');
    if ($method !== null && $delegated) {
        stage4cRefuse('Encryption cannot use both a tool and delegated storage.');
    }
    if ($method !== null) {
        if (!in_array($method, ['age', 'gpg'], true) || stage4cExecutable($method) === null) {
            stage4cRefuse('The requested established encryption tool is unavailable.');
        }
        $recipient = stage4cOption($options, 'recipient');
        if (preg_match('/^[^\s\x00-\x1f]{3,512}$/', $recipient) !== 1) {
            stage4cRefuse('The encryption recipient is invalid.');
        }
        return ['method' => $method, 'recipient_configured' => true, 'encrypted' => true];
    }
    if ($delegated) {
        return ['method' => 'delegated-storage', 'recipient_configured' => false, 'encrypted' => false];
    }
    if ($mode === 'production') {
        stage4cRefuse('Production backup requires established encryption or an explicitly delegated encrypted-storage layer.');
    }
    if (!stage4cFlag($options, 'allow-unencrypted-rehearsal')) {
        stage4cRefuse('Disposable unencrypted output requires explicit rehearsal authorization.');
    }
    return ['method' => 'unencrypted-disposable', 'recipient_configured' => false, 'encrypted' => false];
}

/** @param array{method:string,encrypted:bool} $plan */
function stage4cEncryptArtifact(string $path, array $plan, array $options): array
{
    $plain = ['filename' => basename($path), 'size' => filesize($path), 'sha256' => stage4cHashFile($path)];
    if (!$plan['encrypted']) {
        return [$path, $plain];
    }
    $destination = $path . ($plan['method'] === 'age' ? '.age' : '.gpg');
    $recipient = stage4cOption($options, 'recipient');
    $command = $plan['method'] === 'age'
        ? [stage4cExecutable('age'), '--recipient', $recipient, '--output', $destination, $path]
        : [stage4cExecutable('gpg'), '--batch', '--yes', '--encrypt', '--recipient', $recipient, '--output', $destination, $path];
    $result = stage4cRunCommand($command);
    if ($result['exit'] !== 0 || !is_file($destination) || filesize($destination) < 1) {
        stage4cRefuse('The requested encryption operation did not complete.');
    }
    if (!@unlink($path)) {
        stage4cRefuse('The plaintext rehearsal artifact could not be removed after encryption.');
    }
    return [$destination, $plain];
}

/** @param array<string, string|bool> $options */
function stage4cValidateBackupOptions(array $options): array
{
    $mode = stage4cOption($options, 'mode');
    $outputRoot = stage4cAbsoluteExistingDirectory(stage4cOption($options, 'output-dir'), 'backup output directory');
    stage4cAssertOperationalDirectory($outputRoot, 'backup output directory');
    if (!is_writable($outputRoot)) {
        stage4cRefuse('The explicit backup output directory is not writable.');
    }
    $mediaRoot = stage4cAbsoluteExistingDirectory(stage4cOption($options, 'media-root'), 'managed-media root');
    if ($mediaRoot === $outputRoot || stage4cIsWithin($outputRoot, $mediaRoot) || stage4cIsWithin($mediaRoot, $outputRoot)) {
        stage4cRefuse('The backup output and managed-media roots must be separate.');
    }
    if (!hash_equals(STAGE4C_QUIESCED_CONFIRMATION, stage4cOption($options, 'quiesced-confirmation'))) {
        stage4cRefuse('The explicit quiesced-state confirmation is required.');
    }
    $commit = stage4cOption($options, 'app-commit', false) ?? 'unknown';
    if ($commit !== 'unknown' && preg_match('/^[a-f0-9]{7,64}$/', $commit) !== 1) {
        stage4cRefuse('The application commit identity is invalid.');
    }
    $image = stage4cOption($options, 'image-identity', false) ?? 'unknown';
    if ($image !== 'unknown' && preg_match('/^(?:sha256:)?[a-f0-9]{12,64}$/', $image) !== 1) {
        stage4cRefuse('The image identity is invalid.');
    }
    return [
        'mode' => $mode,
        'output_root' => $outputRoot,
        'media_root' => $mediaRoot,
        'database' => stage4cDatabaseOptions($options),
        'plan' => stage4cEncryptionPlan($options, $mode),
        'commit' => $commit,
        'image' => $image,
    ];
}

/** @param array<string, string|bool> $options */
function stage4cBackup(array $options): array
{
    $configuration = stage4cValidateBackupOptions($options);
    $staging = stage4cCreateStagingDirectory($configuration['output_root'], 'stage4c-backup-incomplete');
    try {
        $database = stage4cPdo($configuration['database']);
        $referencesBefore = stage4cDatabaseMediaReferences($database);
        $mediaBefore = stage4cScanManagedMedia($configuration['media_root']);
        $entryMap = stage4cMediaEntryMap($mediaBefore['entries']);
        foreach ($referencesBefore as $reference) {
            if (!isset($entryMap[$reference])) {
                stage4cRefuse('A database-referenced managed-media file is missing.');
            }
        }
        $dumpPath = $staging . '/database.sql';
        stage4cDumpDatabase($configuration['database'], $dumpPath);
        $archivePath = $staging . '/managed-media.tar.gz';
        stage4cCreateMediaArchive($configuration['media_root'], $mediaBefore['entries'], $archivePath, $staging);
        stage4cValidateArchiveEntries($archivePath, array_keys($entryMap));
        $referencesAfter = stage4cDatabaseMediaReferences($database);
        $mediaAfter = stage4cScanManagedMedia($configuration['media_root']);
        if ($referencesBefore !== $referencesAfter
            || $mediaBefore['content_hash'] !== $mediaAfter['content_hash']
            || $mediaBefore['entries'] !== $mediaAfter['entries']) {
            stage4cRefuse('Database or recognized managed-media state changed during the backup boundary.');
        }
        $mediaManifest = [
            'format' => STAGE4C_MEDIA_FORMAT,
            'version' => STAGE4C_MEDIA_VERSION,
            'files' => $mediaBefore['entries'],
        ];
        $mediaManifestPath = $staging . '/media-manifest.json';
        stage4cWriteJson($mediaManifestPath, $mediaManifest);
        [$finalDumpPath, $plainDump] = stage4cEncryptArtifact($dumpPath, $configuration['plan'], $options);
        [$finalArchivePath, $plainArchive] = stage4cEncryptArtifact($archivePath, $configuration['plan'], $options);
        $migration = stage4cMigrationSummary($database);
        $manifest = [
            'format' => STAGE4C_BACKUP_FORMAT,
            'version' => STAGE4C_BACKUP_VERSION,
            'created_at_utc' => gmdate('c'),
            'application_commit' => $configuration['commit'],
            'image_identity' => $configuration['image'],
            'backup_mode' => $configuration['mode'],
            'encryption' => [
                'method' => $configuration['plan']['method'],
                'recipient_configured' => $configuration['plan']['recipient_configured'],
                'artifact_encrypted' => $configuration['plan']['encrypted'],
            ],
            'database_engine' => ['family' => 'mysql', 'version' => stage4cDatabaseVersion($database), 'character_set' => 'utf8mb4'],
            'migration_ledger' => $migration,
            'critical_record_counts' => stage4cCriticalCounts($database),
            'database_dump' => [
                'filename' => basename($finalDumpPath),
                'size' => filesize($finalDumpPath),
                'sha256' => stage4cHashFile($finalDumpPath),
                'plaintext_sha256' => $plainDump['sha256'],
            ],
            'media_archive' => [
                'filename' => basename($finalArchivePath),
                'size' => filesize($finalArchivePath),
                'sha256' => stage4cHashFile($finalArchivePath),
                'plaintext_sha256' => $plainArchive['sha256'],
            ],
            'media_manifest' => [
                'filename' => basename($mediaManifestPath),
                'size' => filesize($mediaManifestPath),
                'sha256' => stage4cHashFile($mediaManifestPath),
            ],
            'recognized_media_file_count' => count($mediaBefore['entries']),
            'recognized_media_content_manifest_sha256' => $mediaBefore['content_hash'],
            'database_media_references' => $referencesBefore,
            'unreferenced_recognized_media_count' => count($mediaBefore['entries']) - count($referencesBefore),
            'excluded_state' => [
                'transient_quota_lock_count' => $mediaBefore['transient_locks'],
                'unrecognized_media_file_count' => $mediaBefore['unrecognized_files'],
                'sessions' => 'excluded', 'rate_limit_state' => 'excluded', 'runtime_logs' => 'excluded', 'certificates' => 'excluded',
            ],
            'tool_versions' => [
                'php' => PHP_VERSION,
                'mysqldump' => stage4cToolVersion('mysqldump'),
                'tar' => stage4cToolVersion('tar'),
                'encryption' => $configuration['plan']['encrypted'] ? stage4cToolVersion($configuration['plan']['method']) : 'not-used',
            ],
        ];
        stage4cWriteJson($staging . '/manifest.json', $manifest);
        stage4cValidateBackupDirectory($staging, $configuration['mode'], $options, false);
        $finalName = 'stage4c-' . gmdate('Ymd\\THis\\Z') . '-' . stage4cRandomSuffix();
        $finalDirectory = $configuration['output_root'] . '/' . $finalName;
        if (!@rename($staging, $finalDirectory)) {
            stage4cRefuse('The validated backup could not be finalized atomically.');
        }
        return [
            'directory' => $finalName,
            'manifest_sha256' => stage4cHashFile($finalDirectory . '/manifest.json'),
            'recognized_media_file_count' => count($mediaBefore['entries']),
            'content_manifest_sha256' => $mediaBefore['content_hash'],
            'encryption' => $configuration['plan']['method'],
        ];
    } catch (Throwable $exception) {
        if (is_dir($staging)) {
            try {
                stage4cDeleteGeneratedTree($staging, $configuration['output_root']);
            } catch (Throwable) {
                // The parent is still a task-owned output root; do not hide the original safe refusal.
            }
        }
        throw $exception;
    }
}

function stage4cManifestArtifact(array $manifest, string $key): array
{
    $artifact = $manifest[$key] ?? null;
    if (!is_array($artifact)
        || !stage4cSafeFilename($artifact['filename'] ?? null)
        || !is_int($artifact['size'] ?? null)
        || (int) $artifact['size'] < 1
        || !is_string($artifact['sha256'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/', $artifact['sha256']) !== 1
        || !is_string($artifact['plaintext_sha256'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/', $artifact['plaintext_sha256']) !== 1) {
        stage4cRefuse('The recovery manifest contains an invalid artifact descriptor.');
    }
    return $artifact;
}

function stage4cValidateManifest(array $manifest, string $mode): array
{
    if (($manifest['format'] ?? null) !== STAGE4C_BACKUP_FORMAT || ($manifest['version'] ?? null) !== STAGE4C_BACKUP_VERSION) {
        stage4cRefuse('The recovery manifest format or version is unsupported.');
    }
    if (!in_array($mode, ['production', 'disposable-rehearsal'], true)) {
        stage4cRefuse('The recovery mode is invalid.');
    }
    $encryption = $manifest['encryption'] ?? null;
    if (!is_array($encryption) || !is_string($encryption['method'] ?? null) || !is_bool($encryption['artifact_encrypted'] ?? null)) {
        stage4cRefuse('The recovery manifest encryption state is invalid.');
    }
    if ($mode === 'production' && $encryption['method'] === 'unencrypted-disposable') {
        stage4cRefuse('An unencrypted disposable artifact is forbidden for production recovery.');
    }
    $references = $manifest['database_media_references'] ?? null;
    if (!is_array($references)) {
        stage4cRefuse('The recovery manifest media-reference set is invalid.');
    }
    $referenceMap = [];
    foreach ($references as $reference) {
        if (!stage4cManagedMediaKey($reference) || isset($referenceMap[$reference])) {
            stage4cRefuse('The recovery manifest media-reference set is invalid.');
        }
        $referenceMap[$reference] = true;
    }
    if (array_keys($referenceMap) !== array_values($references)) {
        stage4cRefuse('The recovery manifest media-reference order is invalid.');
    }
    $migration = $manifest['migration_ledger'] ?? null;
    if (!is_array($migration) || !is_int($migration['count'] ?? null) || !is_string($migration['ledger_hash'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $migration['ledger_hash']) !== 1) {
        stage4cRefuse('The recovery manifest migration summary is invalid.');
    }
    if (!is_array($manifest['critical_record_counts'] ?? null) || !is_int($manifest['recognized_media_file_count'] ?? null)
        || !is_string($manifest['recognized_media_content_manifest_sha256'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/', $manifest['recognized_media_content_manifest_sha256']) !== 1) {
        stage4cRefuse('The recovery manifest compatibility metadata is invalid.');
    }
    return [
        'database_dump' => stage4cManifestArtifact($manifest, 'database_dump'),
        'media_archive' => stage4cManifestArtifact($manifest, 'media_archive'),
        'media_manifest' => $manifest['media_manifest'] ?? null,
        'encryption' => $encryption,
        'references' => array_keys($referenceMap),
        'migration' => $migration,
    ];
}

function stage4cValidateBackupDirectory(string $backupDirectory, string $mode, array $options, bool $requireDecryptable): array
{
    $backupDirectory = stage4cAbsoluteExistingDirectory($backupDirectory, 'backup directory');
    stage4cAssertOperationalDirectory($backupDirectory, 'backup directory');
    $manifestPath = $backupDirectory . '/manifest.json';
    $manifest = stage4cReadJson($manifestPath, 'The recovery manifest is unavailable or malformed.');
    $validated = stage4cValidateManifest($manifest, $mode);
    $mediaDescriptor = $validated['media_manifest'];
    if (!is_array($mediaDescriptor)
        || !stage4cSafeFilename($mediaDescriptor['filename'] ?? null)
        || !is_int($mediaDescriptor['size'] ?? null)
        || !is_string($mediaDescriptor['sha256'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/', $mediaDescriptor['sha256']) !== 1) {
        stage4cRefuse('The recovery media manifest descriptor is invalid.');
    }
    $allowed = ['manifest.json', $validated['database_dump']['filename'], $validated['media_archive']['filename'], $mediaDescriptor['filename']];
    $actual = [];
    foreach (new DirectoryIterator($backupDirectory) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if (!$entry->isFile() || $entry->isLink() || !in_array($entry->getFilename(), $allowed, true)) {
            stage4cRefuse('The backup directory contains an unexpected file or entry.');
        }
        $actual[] = $entry->getFilename();
    }
    sort($actual, SORT_STRING);
    $expected = $allowed;
    sort($expected, SORT_STRING);
    if ($actual !== $expected) {
        stage4cRefuse('The backup directory is incomplete.');
    }
    foreach (['database_dump' => $validated['database_dump'], 'media_archive' => $validated['media_archive'], 'media_manifest' => $mediaDescriptor] as $key => $artifact) {
        $path = $backupDirectory . '/' . $artifact['filename'];
        if (filesize($path) !== $artifact['size'] || !hash_equals($artifact['sha256'], stage4cHashFile($path))) {
            stage4cRefuse($key === 'database_dump' ? 'Database dump checksum mismatch.' : ($key === 'media_archive' ? 'Media archive checksum mismatch.' : 'Media manifest checksum mismatch.'));
        }
    }
    $mediaManifest = stage4cReadJson($backupDirectory . '/' . $mediaDescriptor['filename'], 'The media manifest is unavailable or malformed.');
    if (($mediaManifest['format'] ?? null) !== STAGE4C_MEDIA_FORMAT || ($mediaManifest['version'] ?? null) !== STAGE4C_MEDIA_VERSION || !is_array($mediaManifest['files'] ?? null)) {
        stage4cRefuse('The media manifest format or version is unsupported.');
    }
    $mediaEntries = stage4cMediaEntryMap($mediaManifest['files']);
    if (count($mediaEntries) !== $manifest['recognized_media_file_count']) {
        stage4cRefuse('The media manifest file count is inconsistent.');
    }
    $parts = [];
    foreach ($mediaEntries as $entry) {
        $parts[] = $entry['path'] . "\x1f" . $entry['sha256'];
    }
    if (!hash_equals($manifest['recognized_media_content_manifest_sha256'], hash('sha256', implode("\x1e", $parts)))) {
        stage4cRefuse('The media content manifest checksum is inconsistent.');
    }
    foreach ($validated['references'] as $reference) {
        if (!isset($mediaEntries[$reference])) {
            stage4cRefuse('A database-referenced media file is absent from the backup manifest.');
        }
    }
    $validated['manifest'] = $manifest;
    $validated['media_entries'] = $mediaEntries;
    $validated['backup_directory'] = $backupDirectory;
    if ($requireDecryptable && $validated['encryption']['artifact_encrypted']) {
        stage4cRequireDecryptionCapability($validated['encryption'], $options);
    }
    return $validated;
}

/** @param array{method:string,artifact_encrypted:bool} $encryption */
function stage4cRequireDecryptionCapability(array $encryption, array $options): void
{
    if ($encryption['method'] === 'age') {
        $identity = stage4cOption($options, 'decrypt-identity-file');
        if (!str_starts_with($identity, '/') || !is_file($identity) || is_link($identity) || !is_readable($identity) || stage4cExecutable('age') === null) {
            stage4cRefuse('The required age decryption identity is unavailable.');
        }
        return;
    }
    if ($encryption['method'] === 'gpg' && stage4cExecutable('gpg') !== null) {
        return;
    }
    stage4cRefuse('The required established decryption capability is unavailable.');
}

/** @param array{method:string,artifact_encrypted:bool} $encryption */
function stage4cDecryptArtifact(string $source, array $encryption, array $options, string $destination): string
{
    if (!$encryption['artifact_encrypted']) {
        return $source;
    }
    stage4cRequireDecryptionCapability($encryption, $options);
    $command = $encryption['method'] === 'age'
        ? [stage4cExecutable('age'), '--decrypt', '--identity', stage4cOption($options, 'decrypt-identity-file'), '--output', $destination, $source]
        : [stage4cExecutable('gpg'), '--batch', '--yes', '--decrypt', '--output', $destination, $source];
    $result = stage4cRunCommand($command);
    if ($result['exit'] !== 0 || !is_file($destination) || filesize($destination) < 1) {
        stage4cRefuse('A required encrypted recovery artifact could not be decrypted.');
    }
    return $destination;
}

function stage4cRequireEmptyMediaTarget(string $value): string
{
    $target = stage4cAbsoluteExistingDirectory($value, 'restore managed-media target');
    stage4cAssertOperationalDirectory($target, 'restore managed-media target');
    $iterator = new FilesystemIterator($target, FilesystemIterator::SKIP_DOTS);
    if ($iterator->valid()) {
        stage4cRefuse('The explicit restore managed-media target is not empty.');
    }
    return $target;
}

function stage4cApprovedRestoreDatabaseName(string $database): bool
{
    return preg_match(STAGE4C_RESTORE_DATABASE_PATTERN, $database) === 1;
}

/** @param array{host:string,port:int,database:string,user:string,password:string} $database */
function stage4cAssertSafeRestoreDatabase(PDO $server, array $database): void
{
    if (!stage4cApprovedRestoreDatabaseName($database['database'])) {
        stage4cRefuse('The restore database target is not an approved disposable namespace.');
    }
    try {
        $tables = $server->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :database');
        $tables->execute(['database' => $database['database']]);
        if ((int) $tables->fetchColumn() !== 0) {
            stage4cRefuse('The explicit restore database target is not empty.');
        }
        $connections = $server->prepare('SELECT COUNT(*) FROM information_schema.processlist WHERE DB = :database');
        $connections->execute(['database' => $database['database']]);
        if ((int) $connections->fetchColumn() !== 0) {
            stage4cRefuse('The explicit restore database target is in use.');
        }
    } catch (Stage4cRecoveryException $exception) {
        throw $exception;
    } catch (Throwable) {
        stage4cRefuse('The explicit restore database target could not be validated.');
    }
}

/** @param array{host:string,port:int,database:string,user:string,defaults_file:?string,password_environment:string,password:string} $database */
function stage4cImportDatabase(array $database, string $dump): void
{
    $binary = stage4cExecutable('mysql');
    if ($binary === null) {
        stage4cRefuse('The required MySQL import client is unavailable.');
    }
    $command = [$binary];
    if ($database['defaults_file'] !== null) {
        $command[] = '--defaults-extra-file=' . $database['defaults_file'];
    }
    $command = [...$command, '--default-character-set=utf8mb4', '--host=' . $database['host'], '--port=' . $database['port'], '--user=' . $database['user'], $database['database']];
    $result = stage4cRunCommand($command, null, null, $dump, stage4cChildEnvironment($database));
    if ($result['exit'] !== 0) {
        stage4cRefuse('The isolated database import did not complete.');
    }
}

/** @param array<string, array{path:string,size:int,sha256:string}> $expected */
function stage4cValidateExtractedMedia(string $root, array $expected): void
{
    $scan = stage4cScanManagedMedia($root);
    $actual = stage4cMediaEntryMap($scan['entries']);
    if ($actual !== $expected) {
        stage4cRefuse('Restored managed media does not match the backup manifest.');
    }
    if ($scan['transient_locks'] !== 0 || $scan['unrecognized_files'] !== 0) {
        stage4cRefuse('Restored managed media contains excluded transient or unrecognized files.');
    }
}

function stage4cApplyMediaOwnership(string $root): void
{
    $binary = stage4cExecutable('chown');
    if ($binary === null) {
        stage4cRefuse('The media ownership tool is unavailable.');
    }
    $result = stage4cRunCommand([$binary, '-R', 'www-data:www-data', $root]);
    if ($result['exit'] !== 0) {
        stage4cRefuse('Restored managed-media ownership could not be applied.');
    }
}

/** @param array<string, string|bool> $options */
function stage4cRestore(array $options): array
{
    $mode = stage4cOption($options, 'mode');
    if ($mode === 'production' && !hash_equals('I_AUTHORIZE_NEW_TARGET_STAGE4C', stage4cOption($options, 'destructive-authorization'))) {
        stage4cRefuse('Production restore requires separate explicit authorization for a new target.');
    }
    $backup = stage4cValidateBackupDirectory(stage4cOption($options, 'backup-dir'), $mode, $options, true);
    $targetMedia = stage4cRequireEmptyMediaTarget(stage4cOption($options, 'target-media-root'));
    $database = stage4cDatabaseOptions($options, 'target-db');
    $server = stage4cPdo($database, false);
    stage4cAssertSafeRestoreDatabase($server, $database);
    $staging = stage4cCreateStagingDirectory($targetMedia, 'stage4c-restore-incomplete');
    try {
        $dump = stage4cDecryptArtifact(
            $backup['backup_directory'] . '/' . $backup['database_dump']['filename'],
            $backup['encryption'],
            $options,
            $staging . '/database.sql',
        );
        $archive = stage4cDecryptArtifact(
            $backup['backup_directory'] . '/' . $backup['media_archive']['filename'],
            $backup['encryption'],
            $options,
            $staging . '/managed-media.tar.gz',
        );
        if (!hash_equals($backup['database_dump']['plaintext_sha256'], stage4cHashFile($dump))
            || !hash_equals($backup['media_archive']['plaintext_sha256'], stage4cHashFile($archive))) {
            stage4cRefuse('A decrypted recovery artifact failed its plaintext checksum.');
        }
        stage4cValidateArchiveEntries($archive, array_keys($backup['media_entries']));
        $extractRoot = $staging . '/media';
        stage4cCreateDirectory($extractRoot);
        $tar = stage4cExecutable('tar');
        $extract = stage4cRunCommand([$tar, '--no-same-owner', '--no-same-permissions', '-xzf', $archive, '-C', $extractRoot]);
        if ($extract['exit'] !== 0) {
            stage4cRefuse('The media archive could not be extracted safely.');
        }
        stage4cValidateExtractedMedia($extractRoot, $backup['media_entries']);
        $server->exec('CREATE DATABASE `' . $database['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        stage4cImportDatabase($database, $dump);
        $restored = stage4cPdo($database);
        $migration = stage4cMigrationSummary($restored);
        if ($migration !== $backup['migration']) {
            stage4cRefuse('The restored migration ledger is incompatible with the backup manifest.');
        }
        if (stage4cCriticalCounts($restored) !== $backup['manifest']['critical_record_counts']) {
            stage4cRefuse('The restored critical record counts do not match the backup manifest.');
        }
        if (stage4cDatabaseMediaReferences($restored) !== $backup['references']) {
            stage4cRefuse('The restored database media references do not match the backup manifest.');
        }
        $sourcePortfolios = $extractRoot . '/portfolios';
        if (!is_dir($sourcePortfolios) || !@rename($sourcePortfolios, $targetMedia . '/portfolios')) {
            stage4cRefuse('The restored managed media could not be finalized safely.');
        }
        stage4cApplyMediaOwnership($targetMedia);
        stage4cDeleteGeneratedTree($staging, $targetMedia);
        return [
            'target_database' => $database['database'],
            'recognized_media_file_count' => count($backup['media_entries']),
            'content_manifest_sha256' => $backup['manifest']['recognized_media_content_manifest_sha256'],
            'migration_count' => $migration['count'],
        ];
    } catch (Throwable $exception) {
        if (is_dir($staging)) {
            @file_put_contents($staging . '/RESTORE-INCOMPLETE', "disposable target; no automatic cutover\n", LOCK_EX);
        }
        throw $exception;
    }
}

/** @param array<string, string|bool> $options */
function stage4cRetentionPlan(array $options): array
{
    if (!stage4cFlag($options, 'dry-run')) {
        stage4cRefuse('Retention planning requires --dry-run.');
    }
    $root = stage4cAbsoluteExistingDirectory(stage4cOption($options, 'backup-root'), 'backup retention directory');
    stage4cAssertOperationalDirectory($root, 'backup retention directory');
    $keep = stage4cRequireStringOption($options, 'keep-minimum', '/^[1-9][0-9]{0,3}$/', 'The minimum retention count is invalid.');
    $recognized = [];
    foreach (new DirectoryIterator($root) as $entry) {
        if ($entry->isDot() || !$entry->isDir() || $entry->isLink() || !str_starts_with($entry->getFilename(), 'stage4c-')) {
            continue;
        }
        try {
            $manifest = stage4cReadJson($entry->getPathname() . '/manifest.json', 'unrecognized');
            if (($manifest['format'] ?? null) === STAGE4C_BACKUP_FORMAT && ($manifest['version'] ?? null) === STAGE4C_BACKUP_VERSION) {
                $recognized[] = $entry->getFilename();
            }
        } catch (Stage4cRecoveryException) {
            // Unknown directories are deliberately never candidates for removal.
        }
    }
    rsort($recognized, SORT_STRING);
    return ['dry_run' => true, 'keep_minimum' => (int) $keep, 'recognized_backups' => count($recognized), 'candidates' => array_slice($recognized, (int) $keep)];
}

function stage4cPrintResult(array $result): void
{
    echo json_encode(['ok' => true, ...$result], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        [$command, $options] = stage4cParseArguments($argv);
        if ($command === 'backup') {
            stage4cPrintResult(stage4cBackup($options));
        } elseif ($command === 'restore') {
            stage4cPrintResult(stage4cRestore($options));
        } elseif ($command === 'verify') {
            $validated = stage4cValidateBackupDirectory(stage4cOption($options, 'backup-dir'), stage4cOption($options, 'mode'), $options, false);
            stage4cPrintResult([
                'manifest_sha256' => stage4cHashFile($validated['backup_directory'] . '/manifest.json'),
                'recognized_media_file_count' => count($validated['media_entries']),
                'content_manifest_sha256' => $validated['manifest']['recognized_media_content_manifest_sha256'],
                'encryption' => $validated['encryption']['method'],
            ]);
        } else {
            stage4cPrintResult(stage4cRetentionPlan($options));
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Stage-4C recovery refused: ' . stage4cSafeFailure($exception) . "\n");
        exit(1);
    }
}
