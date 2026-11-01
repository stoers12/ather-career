<?php

declare(strict_types=1);

/**
 * Reads a ZIP archive written by ZipStreamWriter, independently of it: the
 * central directory, each local header, the data and its CRC-32. The runtime
 * image has no zip extension, so the tests cannot use ZipArchive.
 *
 * @return array<string, string> entry name => contents
 */
function testReadZipEntries(string $archive): array
{
    $end = strrpos($archive, "PK\x05\x06");
    if ($end === false || strlen($archive) - $end !== 22) {
        throw new RuntimeException('ZIP end record is missing.');
    }
    $record = unpack('Vsignature/vdisk/vcd_disk/vdisk_entries/ventries/Vcd_size/Vcd_offset/vcomment', substr($archive, $end, 22));
    if ($record === false || $record['cd_offset'] + $record['cd_size'] !== $end || $record['entries'] !== $record['disk_entries']) {
        throw new RuntimeException('ZIP central directory bounds are wrong.');
    }
    $entries = [];
    $position = $record['cd_offset'];
    for ($index = 0; $index < $record['entries']; $index++) {
        $central = unpack('Vsignature/vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname_length/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset', substr($archive, $position, 46));
        if ($central === false || $central['signature'] !== 0x02014b50) {
            throw new RuntimeException('ZIP central entry is malformed.');
        }
        $name = substr($archive, $position + 46, $central['name_length']);
        $position += 46 + $central['name_length'] + $central['extra'] + $central['comment'];
        $local = unpack('Vsignature/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname_length/vextra', substr($archive, $central['offset'], 30));
        if ($local === false || $local['signature'] !== 0x04034b50 || substr($archive, $central['offset'] + 30, $local['name_length']) !== $name
            || $local['crc'] !== $central['crc'] || $local['compressed'] !== $central['compressed'] || $local['method'] !== $central['method']) {
            throw new RuntimeException('ZIP local header does not match its central entry: ' . $name);
        }
        $data = substr($archive, $central['offset'] + 30 + $local['name_length'] + $local['extra'], $central['compressed']);
        $contents = match ($central['method']) {
            0 => $data,
            8 => gzinflate($data),
            default => false,
        };
        if (!is_string($contents) || strlen($contents) !== $central['size'] || crc32($contents) !== $central['crc']) {
            throw new RuntimeException('ZIP entry data is corrupt: ' . $name);
        }
        $entries[$name] = $contents;
    }

    return $entries;
}
