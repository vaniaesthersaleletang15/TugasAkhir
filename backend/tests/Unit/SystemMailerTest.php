<?php

namespace Tests\Unit;

use App\Services\SystemMailer;
use PHPUnit\Framework\TestCase;

class SystemMailerTest extends TestCase
{
    private SystemMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new SystemMailer;
    }

    public function test_configuration_is_ready_with_required_values(): void
    {
        $this->assertTrue($this->mailer->isReady([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
            'smtp_from_email' => 'prima@example.test',
        ]));
    }

    public function test_configuration_is_not_ready_without_required_value(): void
    {
        $this->assertFalse($this->mailer->isReady([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
        ]));
    }

    public function test_configuration_is_not_ready_with_invalid_sender_email(): void
    {
        $this->assertFalse($this->mailer->isReady([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
            'smtp_from_email' => 'bukan-email',
        ]));
    }

    public function test_authenticated_smtp_requires_password(): void
    {
        $this->assertFalse($this->mailer->isReady([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
            'smtp_from_email' => 'prima@example.test',
            'smtp_username' => 'mailer@example.test',
            'smtp_password' => '',
        ]));
    }

    public function test_authenticated_smtp_is_ready_with_password(): void
    {
        $this->assertTrue($this->mailer->isReady([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
            'smtp_from_email' => 'prima@example.test',
            'smtp_username' => 'mailer@example.test',
            'smtp_password' => 'encrypted-secret',
        ]));
    }

    public function test_empty_username_does_not_require_password(): void
    {
        $this->assertTrue($this->mailer->isReady([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
            'smtp_from_email' => 'prima@example.test',
            'smtp_username' => '',
            'smtp_password' => '',
        ]));
    }

    public function test_collection_configuration_is_supported(): void
    {
        $this->assertTrue($this->mailer->isReady(collect([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
            'smtp_from_email' => 'prima@example.test',
        ])));
    }

    public function test_blank_host_is_not_ready(): void
    {
        $this->assertFalse($this->mailer->isReady([
            'smtp_host' => '   ',
            'smtp_port' => '587',
            'smtp_from_email' => 'prima@example.test',
        ]));
    }
}
