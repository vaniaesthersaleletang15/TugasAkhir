<?php

namespace Tests\Unit;

use App\Domain\System\BackupFilePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BackupFilePolicyTest extends TestCase
{
    private BackupFilePolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new BackupFilePolicy;
    }

    #[DataProvider('createFilenameProvider')]
    public function test_create_filename_validation(string $filename, bool $expected): void
    {
        $this->assertSame($expected, $this->policy->validCreateFilename($filename));
    }

    public static function createFilenameProvider(): array
    {
        return [
            'valid' => ['prima-db-2026-10-29.dump', true],
            'wrong prefix' => ['backup-2026.dump', false],
            'wrong extension' => ['prima-db-2026.sql', false],
            'path traversal' => ['../prima-db-2026.dump', false],
            'space' => ['prima-db-2026 final.dump', false],
        ];
    }

    #[DataProvider('restoreFilenameProvider')]
    public function test_restore_filename_validation(string $filename, bool $expected): void
    {
        $this->assertSame($expected, $this->policy->validRestoreFilename($filename));
    }

    public static function restoreFilenameProvider(): array
    {
        return [
            'postgres dump' => ['prima-db-2026.dump', true],
            'portable backup' => ['prima-portable-2026.json.gz', true],
            'path traversal' => ['../prima-db-2026.dump', false],
            'absolute-like path' => ['backups/prima-db-2026.dump', false],
            'wrong extension' => ['prima-db-2026.zip', false],
        ];
    }

    public function test_matching_checksum_is_accepted(): void
    {
        $checksum = hash('sha256', 'backup-content');

        $this->assertTrue($this->policy->checksumMatches(" $checksum\n", $checksum));
    }

    public function test_different_or_empty_checksum_is_rejected(): void
    {
        $this->assertFalse($this->policy->checksumMatches(hash('sha256', 'a'), hash('sha256', 'b')));
        $this->assertFalse($this->policy->checksumMatches('', hash('sha256', 'b')));
    }
}
