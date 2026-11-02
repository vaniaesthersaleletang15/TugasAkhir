<?php

namespace Tests\Unit;

use App\Domain\Ekim\EkimSignatureService;
use PHPUnit\Framework\TestCase;

class EkimSignatureServiceTest extends TestCase
{
    private EkimSignatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EkimSignatureService;
    }

    public function test_same_document_produces_same_hash(): void
    {
        $form = ['id' => 1, 'nomor_polisi' => 'DB 1234 AA'];
        $items = [['item_name' => 'Ban', 'is_baik' => true]];

        $this->assertSame(
            $this->service->canonicalHash($form, $items),
            $this->service->canonicalHash($form, $items),
        );
    }

    public function test_volatile_signature_fields_do_not_change_hash(): void
    {
        $base = ['id' => 1, 'nomor_polisi' => 'DB 1234 AA'];
        $signed = $base + ['status_approval' => 'approved', 'ttd_hsse_nama' => 'Petugas', 'verification_uuid' => 'uuid'];

        $this->assertSame(
            $this->service->canonicalHash($base, []),
            $this->service->canonicalHash($signed, []),
        );
    }

    public function test_business_data_change_changes_hash(): void
    {
        $first = $this->service->canonicalHash(['id' => 1, 'nomor_polisi' => 'DB 1234 AA'], []);
        $second = $this->service->canonicalHash(['id' => 1, 'nomor_polisi' => 'DB 9999 ZZ'], []);

        $this->assertNotSame($first, $second);
    }

    public function test_checklist_item_change_changes_hash(): void
    {
        $form = ['id' => 1, 'nomor_polisi' => 'DB 1234 AA'];

        $this->assertNotSame(
            $this->service->canonicalHash($form, [['item_name' => 'Ban', 'is_baik' => true]]),
            $this->service->canonicalHash($form, [['item_name' => 'Ban', 'is_baik' => false]]),
        );
    }

    public function test_sha256_and_sha512_have_expected_lengths(): void
    {
        $this->assertSame(64, strlen($this->service->canonicalHash(['id' => 1], [], 'sha256')));
        $this->assertSame(128, strlen($this->service->canonicalHash(['id' => 1], [], 'sha512')));
    }

    public function test_unicode_and_slashes_are_hashed_deterministically(): void
    {
        $hash = $this->service->canonicalHash(['catatan' => 'Layak — dokumen/aman'], []);

        $this->assertSame($hash, $this->service->canonicalHash(['catatan' => 'Layak — dokumen/aman'], []));
    }

    public function test_signature_is_url_safe_and_has_algorithm_prefix(): void
    {
        $signature = $this->service->sign(str_repeat('a', 64), 'secret');

        $this->assertStringStartsWith('hmac-sha256:', $signature);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $signature);
    }

    public function test_signature_verification_accepts_correct_secret(): void
    {
        $hash = str_repeat('a', 64);
        $signature = $this->service->sign($hash, 'secret');

        $this->assertTrue($this->service->verify($hash, $signature, 'secret'));
    }

    public function test_signature_verification_rejects_changed_hash_or_secret(): void
    {
        $signature = $this->service->sign(str_repeat('a', 64), 'secret');

        $this->assertFalse($this->service->verify(str_repeat('b', 64), $signature, 'secret'));
        $this->assertFalse($this->service->verify(str_repeat('a', 64), $signature, 'other-secret'));
    }
}
