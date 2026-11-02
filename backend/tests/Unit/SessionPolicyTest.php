<?php

namespace Tests\Unit;

use App\Domain\System\SessionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SessionPolicyTest extends TestCase
{
    #[DataProvider('timeoutProvider')]
    public function test_timeout_is_normalized(mixed $value, int $expected): void
    {
        $this->assertSame($expected, (new SessionPolicy)->timeoutMinutes($value));
    }

    public static function timeoutProvider(): array
    {
        return [
            'null uses default' => [null, 60],
            'numeric string' => ['90', 90],
            'minimum boundary' => [15, 15],
            'below minimum' => [1, 15],
            'maximum boundary' => [480, 480],
            'above maximum' => [999, 480],
            'non numeric value' => ['invalid', 15],
        ];
    }
}
