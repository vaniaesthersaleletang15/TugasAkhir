<?php

namespace Tests\Unit;

use App\Domain\System\SystemAvailabilityPolicy;
use PHPUnit\Framework\TestCase;

class SystemAvailabilityPolicyTest extends TestCase
{
    private SystemAvailabilityPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new SystemAvailabilityPolicy;
    }

    public function test_only_active_account_is_accepted(): void
    {
        $this->assertTrue($this->policy->accountIsActive('active'));
        $this->assertFalse($this->policy->accountIsActive('inactive'));
        $this->assertFalse($this->policy->accountIsActive(null));
    }

    public function test_maintenance_blocks_non_admin_only(): void
    {
        $this->assertTrue($this->policy->maintenanceBlocks(true, 'hsse'));
        $this->assertTrue($this->policy->maintenanceBlocks(true, null));
        $this->assertFalse($this->policy->maintenanceBlocks(true, 'admin'));
        $this->assertFalse($this->policy->maintenanceBlocks(false, 'hsse'));
    }

    public function test_maintenance_message_uses_configured_text(): void
    {
        $this->assertSame('Pemeliharaan terjadwal.', $this->policy->maintenanceMessage(' Pemeliharaan terjadwal. '));
    }

    public function test_blank_maintenance_message_uses_default(): void
    {
        $this->assertSame('Sistem sedang dalam pemeliharaan.', $this->policy->maintenanceMessage('   '));
        $this->assertSame('Sistem sedang dalam pemeliharaan.', $this->policy->maintenanceMessage(null));
    }
}
