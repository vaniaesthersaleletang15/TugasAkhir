<?php

namespace App\Domain\System;

class BackupFilePolicy
{
    public function validCreateFilename(string $filename): bool
    {
        return basename($filename) === $filename
            && preg_match('/^prima-db-[A-Za-z0-9-]+\.dump$/', $filename) === 1;
    }

    public function validRestoreFilename(string $filename): bool
    {
        return basename($filename) === $filename
            && (str_ends_with($filename, '.dump') || str_ends_with($filename, '.json.gz'));
    }

    public function checksumMatches(string $expected, string $actual): bool
    {
        $expected = trim($expected);
        $actual = trim($actual);

        return $expected !== '' && $actual !== '' && hash_equals($expected, $actual);
    }
}
