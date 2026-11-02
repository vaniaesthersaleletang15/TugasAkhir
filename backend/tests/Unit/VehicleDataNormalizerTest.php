<?php

namespace Tests\Unit;

use App\Domain\Vehicle\VehicleDataNormalizer;
use PHPUnit\Framework\TestCase;

class VehicleDataNormalizerTest extends TestCase
{
    private VehicleDataNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new VehicleDataNormalizer;
    }

    public function test_plate_is_trimmed_and_uppercased(): void
    {
        $this->assertSame('DB 1234 AA', $this->normalizer->plate(' db 1234 aa '));
    }

    public function test_empty_plate_becomes_empty_string(): void
    {
        $this->assertSame('', $this->normalizer->plate(null));
    }

    public function test_email_is_trimmed_and_lowercased(): void
    {
        $this->assertSame('owner@example.test', $this->normalizer->email(' OWNER@EXAMPLE.TEST '));
    }

    public function test_empty_email_becomes_empty_string(): void
    {
        $this->assertSame('', $this->normalizer->email(null));
    }

    public function test_transportir_email_prefers_new_field(): void
    {
        $this->assertSame(
            'new@example.test',
            $this->normalizer->transportirEmail(' NEW@EXAMPLE.TEST ', 'legacy@example.test'),
        );
    }

    public function test_transportir_email_falls_back_to_legacy_field(): void
    {
        $this->assertSame(
            'legacy@example.test',
            $this->normalizer->transportirEmail(null, ' LEGACY@EXAMPLE.TEST '),
        );
    }
}
