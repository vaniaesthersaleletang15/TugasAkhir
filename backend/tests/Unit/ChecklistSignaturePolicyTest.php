<?php

namespace Tests\Unit;

use App\Domain\Checklist\ChecklistSignaturePolicy;
use PHPUnit\Framework\TestCase;

class ChecklistSignaturePolicyTest extends TestCase
{
    private ChecklistSignaturePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ChecklistSignaturePolicy;
    }

    public function test_admin_and_hsse_can_sign_as_hsse(): void
    {
        $this->assertTrue($this->policy->roleCanSign('admin', 'hsse'));
        $this->assertTrue($this->policy->roleCanSign('hsse', 'hsse'));
    }

    public function test_transportir_cannot_sign_as_hsse(): void
    {
        $this->assertFalse($this->policy->roleCanSign('pengurus', 'hsse'));
    }

    public function test_only_manager_can_sign_as_manager(): void
    {
        $this->assertTrue($this->policy->roleCanSign('manager_hsse', 'manajer'));
        $this->assertFalse($this->policy->roleCanSign('admin', 'manajer'));
    }

    public function test_hsse_signature_accepts_draft_and_pending_status(): void
    {
        $this->assertTrue($this->policy->statusAllowsSignature('hsse', 'draft'));
        $this->assertTrue($this->policy->statusAllowsSignature('hsse', 'pending_hsse'));
        $this->assertFalse($this->policy->statusAllowsSignature('hsse', 'signed_hsse'));
    }

    public function test_manager_signature_requires_hsse_signature(): void
    {
        $this->assertTrue($this->policy->statusAllowsSignature('manajer', 'signed_hsse'));
        $this->assertFalse($this->policy->statusAllowsSignature('manajer', 'pending_hsse'));
    }

    public function test_signer_name_comparison_ignores_case_and_outer_spaces(): void
    {
        $this->assertTrue($this->policy->signerNameMatches('  Vania Saleletang ', 'vania saleletang'));
    }

    public function test_different_signer_name_is_rejected(): void
    {
        $this->assertFalse($this->policy->signerNameMatches('Nama A', 'Nama B'));
    }

    public function test_bad_checklist_item_blocks_signature(): void
    {
        $this->assertSame(
            'Tanda tangan ditolak: terdapat item TIDAK BAIK — Ban; Lampu',
            $this->policy->contentError(['Ban', 'Lampu'], '2026-11-30', '2026-10-29'),
        );
    }

    public function test_expired_ekim_blocks_signature(): void
    {
        $this->assertSame(
            'Tanda tangan ditolak: EKIM sudah kedaluwarsa.',
            $this->policy->contentError([], '2026-10-28', '2026-10-29'),
        );
    }

    public function test_valid_content_can_be_signed(): void
    {
        $this->assertNull($this->policy->contentError([], '2026-10-29', '2026-10-29'));
        $this->assertNull($this->policy->contentError([], null, '2026-10-29'));
    }
}
