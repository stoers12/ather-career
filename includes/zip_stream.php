<?php

declare(strict_types=1);

/**
 * A small ZIP writer (PKWARE APPNOTE 6.3, no ZIP64) for the data export (L-03).
 * Each entry goes to the output stream as soon as it is added, so an export is
 * never stored on the server and the PHP zip extension is not needed. Text is
 * deflated; files are stored as they are, because images are already compressed.
 */
final class ZipStreamWriter
{
    private const MAX_OFFSET = 0xFFFFFFFF;
    private const MAX_ENTRIES = 0xFFFF;
    private const FLAG_UTF8_NAMES = 0x0800;

    /** @var list<string> */
    private array $centralDirectory = [];
    /** @var array<string, true> */
    private array $names = [];
    private int $offset = 0;
    private bool $finished = false;
    private int $dosTime;
    private int $dosDate;

    /** @param resource $stream */
    public function __construct(private $stream, ?int $timestamp = null)
    {
        $parts = getdate($timestamp ?? time());
        $this->dosTime = ($parts['hours'] << 11) | ($parts['minutes'] << 5) | intdiv($parts['seconds'], 2);
        $this->dosDate = ((max(1980, $parts['year']) - 1980) << 9) | ($parts['mon'] << 5) | $parts['mday'];
    }

    public function addFromString(string $name, string $contents): void
    {
        $deflated = gzdeflate($contents, 6);
        if ($deflated === false) {
            throw new RuntimeException('ZIP entry could not be compressed.');
        }
        $this->writeEntry($name, 8, crc32($contents), strlen($deflated), strlen($contents), static fn ($stream): int => (int) fwrite($stream, $deflated));
    }

    public function addFromPath(string $name, string $path): void
    {
        $size = @filesize($path);
        $crc = @hash_file('crc32b', $path);
        if ($size === false || $crc === false || !is_file($path)) {
            throw new RuntimeException('ZIP entry source is unreadable.');
        }
        $this->writeEntry($name, 0, (int) hexdec($crc), $size, $size, static function ($stream) use ($path): int {
            $source = @fopen($path, 'rb');
            if ($source === false) {
                throw new RuntimeException('ZIP entry source is unreadable.');
            }
            try {
                return (int) stream_copy_to_stream($source, $stream);
            } finally {
                fclose($source);
            }
        });
    }

    public function finish(): void
    {
        if ($this->finished) {
            return;
        }
        $directory = implode('', $this->centralDirectory);
        $count = count($this->centralDirectory);
        $this->write($directory);
        $this->write(pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($directory), $this->offset - strlen($directory), 0));
        $this->finished = true;
    }

    /** @param callable(resource): int $writeData */
    private function writeEntry(string $name, int $method, int $crc, int $compressedSize, int $size, callable $writeData): void
    {
        if ($this->finished || !self::validName($name) || isset($this->names[$name])) {
            throw new InvalidArgumentException('ZIP entry name is invalid.');
        }
        if (count($this->centralDirectory) >= self::MAX_ENTRIES || $compressedSize >= self::MAX_OFFSET || $size >= self::MAX_OFFSET
            || $this->offset + 30 + strlen($name) + $compressedSize >= self::MAX_OFFSET) {
            throw new RuntimeException('ZIP archive is too large.');
        }
        $this->names[$name] = true;
        $offset = $this->offset;
        $this->write(pack('VvvvvvVVVvv', 0x04034b50, 20, self::FLAG_UTF8_NAMES, $method, $this->dosTime, $this->dosDate, $crc, $compressedSize, $size, strlen($name), 0) . $name);
        if ($writeData($this->stream) !== $compressedSize) {
            throw new RuntimeException('ZIP entry could not be written.');
        }
        $this->offset += $compressedSize;
        $this->centralDirectory[] = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, self::FLAG_UTF8_NAMES, $method, $this->dosTime, $this->dosDate, $crc, $compressedSize, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
    }

    private function write(string $bytes): void
    {
        if ($bytes !== '' && fwrite($this->stream, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('ZIP output could not be written.');
        }
        $this->offset += strlen($bytes);
    }

    private static function validName(string $name): bool
    {
        return $name !== '' && strlen($name) <= 240 && mb_check_encoding($name, 'UTF-8')
            && preg_match('#^[^/\\\\\x00-\x1F](?:[^\\\\\x00-\x1F]*[^/\\\\\x00-\x1F])?$#u', $name) === 1
            && !in_array('..', explode('/', $name), true);
    }
}
