<?php

namespace Tests\Unit;

use App\Domain\System\RetentionPolicy;
use PHPUnit\Framework\TestCase;

class RetentionPolicyTest extends TestCase
{
    private RetentionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RetentionPolicy;
    }

    public function test_audit_retention_accepts_value_inside_range(): void
    {
        $this->assertSame(365, $this->policy->auditDays(365));
    }

    public function test_audit_retention_has_minimum_ninety_days(): void
    {
        $this->assertSame(90, $this->policy->auditDays(1));
    }

    public function test_audit_retention_has_maximum_3650_days(): void
    {
        $this->assertSame(3650, $this->policy->auditDays(9999));
    }

    public function test_backup_retention_accepts_value_inside_range(): void
    {
        $this->assertSame(30, $this->policy->backupDays('30'));
    }

    public function test_backup_retention_has_minimum_seven_days(): void
    {
        $this->assertSame(7, $this->policy->backupDays(-1));
    }

    public function test_backup_retention_has_maximum_365_days(): void
    {
        $this->assertSame(365, $this->policy->backupDays(500));
    }
}
