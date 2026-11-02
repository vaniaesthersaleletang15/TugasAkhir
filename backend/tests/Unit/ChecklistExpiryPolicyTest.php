<?php

namespace Tests\Unit;

use App\Domain\Checklist\ChecklistExpiryPolicy;
use PHPUnit\Framework\TestCase;

class ChecklistExpiryPolicyTest extends TestCase
{
    private ChecklistExpiryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ChecklistExpiryPolicy;
    }

    public function test_stnk_requires_expiry_date(): void
    {
        $error = $this->policy->validate([['nama' => 'STNK']], 'SPBU', '2026-10-29');

        $this->assertSame('Tanggal masa berlaku STNK wajib diisi.', $error);
    }

    public function test_expired_document_is_rejected(): void
    {
        $error = $this->policy->validate([
            ['nama' => 'PAJAK', 'tanggal_expire' => '2026-10-28'],
        ], 'SPBU', '2026-10-29');

        $this->assertSame('PAJAK sudah kedaluwarsa pada tanggal pemeriksaan.', $error);
    }

    public function test_expiry_on_inspection_date_is_valid(): void
    {
        $error = $this->policy->validate([
            ['nama' => 'STNK', 'tanggal_expire' => '2026-10-29'],
        ], 'SPBU', '2026-10-29');

        $this->assertNull($error);
    }

    public function test_simfit_is_not_required_for_spbu(): void
    {
        $error = $this->policy->validate([
            ['nama' => 'SIMFIT (Industri)', 'tanggal_expire' => ''],
        ], 'SPBU', '2026-10-29');

        $this->assertNull($error);
    }

    public function test_simfit_is_required_for_industry_vehicle(): void
    {
        $error = $this->policy->validate([
            ['nama' => 'SIMFIT (Industri)', 'tanggal_expire' => ''],
        ], 'INDUSTRI', '2026-10-29');

        $this->assertSame('Tanggal masa berlaku SIMFIT (Industri) wajib diisi.', $error);
    }

    public function test_non_expiry_item_does_not_require_date(): void
    {
        $this->assertNull($this->policy->validate([
            ['nama' => 'Kondisi Ban', 'tanggal_expire' => ''],
        ], 'SPBU', '2026-10-29'));
    }

    public function test_policy_stops_at_first_invalid_item(): void
    {
        $error = $this->policy->validate([
            ['nama' => 'STNK', 'tanggal_expire' => ''],
            ['nama' => 'PAJAK', 'tanggal_expire' => ''],
        ], 'SPBU', '2026-10-29');

        $this->assertSame('Tanggal masa berlaku STNK wajib diisi.', $error);
    }
}
