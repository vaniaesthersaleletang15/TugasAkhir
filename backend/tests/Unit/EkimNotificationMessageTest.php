<?php

namespace Tests\Unit;

use App\Domain\Ekim\EkimNotificationMessage;
use PHPUnit\Framework\TestCase;

class EkimNotificationMessageTest extends TestCase
{
    private EkimNotificationMessage $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->message = new EkimNotificationMessage;
    }

    public function test_future_expiry_mentions_remaining_days(): void
    {
        $result = $this->message->build('DB 1234 AA', '2026-11-03', 5);

        $this->assertSame('Notifikasi Masa Berlaku EKIM DB 1234 AA', $result['subject']);
        $this->assertStringContainsString('akan kedaluwarsa dalam 5 hari', $result['body']);
    }

    public function test_expiry_today_mentions_zero_days(): void
    {
        $result = $this->message->build('DB 1234 AA', '2026-10-29', 0);

        $this->assertStringContainsString('akan kedaluwarsa dalam 0 hari', $result['body']);
    }

    public function test_past_expiry_is_reported_as_expired(): void
    {
        $result = $this->message->build('DB 1234 AA', '2026-10-28', -1);

        $this->assertStringContainsString('telah kedaluwarsa', $result['body']);
        $this->assertStringContainsString('2026-10-28', $result['body']);
    }
}
