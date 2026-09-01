<?php

const RATE_LIMIT_RETENTION_SECONDS = 3600;
const RATE_LIMIT_CLEANUP_FILE_LIMIT = 25;

function rateLimitClientIp(): string
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false
        ? $address
        : 'unknown';
}

function rateLimitDirectory(): string
{
    $configuredDirectory = getenv('RATE_LIMIT_STATE_DIR');
    $directory = is_string($configuredDirectory) && $configuredDirectory !== ''
        ? $configuredDirectory
        : dirname(__DIR__) . '/runtime/rate-limit';

    if (!is_dir($directory)) {
        // Another request can create this managed directory between the check and mkdir.
        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Rate limit state directory is unavailable.');
        }
    }

    $resolvedDirectory = realpath($directory);
    if ($resolvedDirectory === false || !is_dir($resolvedDirectory) || !is_writable($resolvedDirectory)) {
        throw new RuntimeException('Rate limit state directory is not writable.');
    }

    return $resolvedDirectory;
}

function rateLimitStatePath(string $directory, string $scope, string $identity): string
{
    if (!preg_match('/^[a-z0-9_-]{1,64}$/', $scope)) {
        throw new InvalidArgumentException('Rate limit scope is invalid.');
    }

    return $directory . DIRECTORY_SEPARATOR . hash('sha256', $scope . "\0" . $identity) . '.json';
}

function rateLimitStateFromJson(string $contents): array
{
    if ($contents === '') {
        return ['window_started' => 0, 'attempts' => 0];
    }

    $state = json_decode($contents, true);
    if (!is_array($state)
        || !isset($state['window_started'], $state['attempts'])
        || !is_int($state['window_started'])
        || !is_int($state['attempts'])
        || $state['window_started'] < 0
        || $state['attempts'] < 0) {
        throw new RuntimeException('Rate limit state is corrupt.');
    }

    return $state;
}

function writeRateLimitState($handle, array $state): void
{
    $encodedState = json_encode($state, JSON_THROW_ON_ERROR);
    $written = false;
    if (rewind($handle) !== false && ftruncate($handle, 0) !== false) {
        $written = fwrite($handle, $encodedState);
    }

    if ($written !== strlen($encodedState) || fflush($handle) === false) {
        throw new RuntimeException('Rate limit state could not be written.');
    }
}

function consumeRateLimit(string $scope, string $identity, int $limit, int $windowSeconds, ?int $now = null): array
{
    if ($limit < 1 || $windowSeconds < 1) {
        throw new InvalidArgumentException('Rate limit policy is invalid.');
    }

    $currentTime = $now ?? time();
    $directory = rateLimitDirectory();
    $path = rateLimitStatePath($directory, $scope, $identity);
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Rate limit state could not be opened.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Rate limit state could not be locked.');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false) {
            throw new RuntimeException('Rate limit state could not be read.');
        }

        $state = rateLimitStateFromJson($contents);
        if ($state['window_started'] === 0 || $currentTime < $state['window_started'] || ($currentTime - $state['window_started']) >= $windowSeconds) {
            $state = ['window_started' => $currentTime, 'attempts' => 0];
        }

        if ($state['attempts'] >= $limit) {
            return ['allowed' => false, 'retry_after' => max(1, ($state['window_started'] + $windowSeconds) - $currentTime)];
        }

        $state['attempts']++;
        writeRateLimitState($handle, $state);
        @chmod($path, 0600);

        return ['allowed' => true, 'retry_after' => 0];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);

        if (mt_rand(1, 100) === 1) {
            try {
                cleanupRateLimitState($directory, $currentTime);
            } catch (Throwable) {
                // Expired-state cleanup is best effort and must not affect a request decision.
            }
        }
    }
}

function clearRateLimit(string $scope, string $identity): void
{
    $directory = rateLimitDirectory();
    $path = rateLimitStatePath($directory, $scope, $identity);
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Rate limit state could not be opened.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Rate limit state could not be locked.');
        }

        writeRateLimitState($handle, ['window_started' => 0, 'attempts' => 0]);
        @chmod($path, 0600);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function cleanupRateLimitState(string $directory, int $now): void
{
    $checked = 0;
    foreach (new DirectoryIterator($directory) as $file) {
        if ($file->isDot() || !$file->isFile() || !preg_match('/^[a-f0-9]{64}\.json$/', $file->getFilename())) {
            continue;
        }

        if (++$checked > RATE_LIMIT_CLEANUP_FILE_LIMIT) {
            break;
        }

        if (($now - $file->getMTime()) > RATE_LIMIT_RETENTION_SECONDS) {
            @unlink($file->getPathname());
        }
    }
}
