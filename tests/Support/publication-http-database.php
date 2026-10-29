<?php
declare(strict_types=1);
// Loaded only by the test's private config copy, never by production config.
if (PHP_SAPI !== 'cli-server' || !is_file('/.dockerenv') || getenv('APP_ENV') !== 'test'
    || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/\Aather_career_test_[a-f0-9]{24}\z/D', (string)getenv('DB_NAME')) !== 1
    || getenv('DB_NAME') !== getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }

final class PublicationHttpDatabase extends PDO
{
    private string $mode = '';
    public function beginTransaction(): bool
    {
        $root = (string)getenv('ATHERCAR_PUBLICATION_FIXTURE_CONTROL');
        $this->mode = trim((string)file_get_contents($root.'/mode'));
        $started = parent::beginTransaction();
        if ($this->mode === 'barrier') {
            file_put_contents($root.'/admitted', 'ready', LOCK_EX);
            $deadline = microtime(true)+12;
            while (!is_file($root.'/release') && microtime(true)<$deadline) { usleep(10000); }
            if (!is_file($root.'/release')) { throw new RuntimeException('HTTP fixture barrier timeout.'); }
        }
        return $started;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->mode === 'statement' && str_starts_with($query, 'UPDATE projects')) {
            throw new PDOException('Synthetic HTTP statement failure.');
        }
        return parent::prepare($query, $options);
    }
    public function commit(): bool
    {
        $result = parent::commit();
        if ($this->mode === 'after') { throw new PDOException('Synthetic HTTP lost COMMIT acknowledgement.'); }
        return $result;
    }
}
