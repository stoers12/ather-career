<?php

declare(strict_types=1);

// There is no production environment, query or cookie override for publication.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('ATHERCAR_TEST_MODE') !== '1' || !is_file('/.dockerenv')) { exit(2); }

/** Publish only a disposable source copy, verifying that exactly the two constants changed. */
function legalFixturePublish(string $applicationRoot): void
{
    $file = $applicationRoot . '/includes/legal_documents.php';
    $source = file_get_contents($file);
    $before = ['const LEGAL_PAGES_PUBLISHED = false;', 'const LEGAL_DOCUMENT_EFFECTIVE_DATE = null;'];
    $after = ['const LEGAL_PAGES_PUBLISHED = true;', "const LEGAL_DOCUMENT_EFFECTIVE_DATE = '2026-11-01';"];
    foreach ($before as $needle) {
        if (substr_count($source, $needle) !== 1) { throw new RuntimeException('Legal fixture constant seam changed.'); }
    }
    $published = str_replace($before, $after, $source);
    if (str_replace($after, $before, $published) !== $source || file_put_contents($file, $published) === false) {
        throw new RuntimeException('Legal fixture changed other source.');
    }
}

function legalFixtureCopyApplication(string $source, string $target): void
{
    mkdir($target, 0700);
    foreach (['handlers', 'includes', 'config', 'data', 'resources'] as $directory) {
        mkdir($target . '/' . $directory, 0700);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . '/' . $directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            if ($file->isLink()) { throw new RuntimeException('Unexpected legal fixture source link.'); }
            $destination = $target . '/' . $directory . '/' . substr($file->getPathname(), strlen($source . '/' . $directory) + 1);
            if ($file->isDir()) { mkdir($destination, 0700); }
            else { copy($file->getPathname(), $destination); chmod($destination, 0600); }
        }
    }
    foreach (['vendor', 'src', 'public'] as $directory) { symlink($source . '/' . $directory, $target . '/' . $directory); }
    legalFixturePublish($target);
}
