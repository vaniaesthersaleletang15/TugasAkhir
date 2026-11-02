<?php

namespace Tests\Unit;

use App\Domain\Ekim\EkimStatusCalculator;
use PHPUnit\Framework\TestCase;

class EkimStatusCalculatorTest extends TestCase
{
    private EkimStatusCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new EkimStatusCalculator;
    }

    public function test_status_is_unavailable_when_expiry_date_is_missing(): void
    {
        $result = $this->calculator->evaluate(null, '2026-10-29');

        $this->assertSame('unavailable', $result['status']);
        $this->assertNull($result['days_remaining']);
    }

    public function test_status_is_expired_when_expiry_date_has_passed(): void
    {
        $result = $this->calculator->evaluate('2026-10-28', '2026-10-29');

        $this->assertSame('expired', $result['status']);
        $this->assertSame(-1, $result['days_remaining']);
    }

    public function test_status_is_expiring_on_expiry_date(): void
    {
        $result = $this->calculator->evaluate('2026-10-29', '2026-10-29');

        $this->assertSame('expiring', $result['status']);
        $this->assertSame(0, $result['days_remaining']);
    }

    public function test_status_is_expiring_on_warning_boundary(): void
    {
        $result = $this->calculator->evaluate('2026-11-28', '2026-10-29', 30);

        $this->assertSame('expiring', $result['status']);
        $this->assertSame(30, $result['days_remaining']);
    }

    public function test_status_is_active_after_warning_boundary(): void
    {
        $result = $this->calculator->evaluate('2026-11-29', '2026-10-29', 30);

        $this->assertSame('active', $result['status']);
        $this->assertSame(31, $result['days_remaining']);
    }

    public function test_negative_warning_period_is_treated_as_zero(): void
    {
        $result = $this->calculator->evaluate('2026-10-30', '2026-10-29', -10);

        $this->assertSame('active', $result['status']);
        $this->assertSame(1, $result['days_remaining']);
    }
}
