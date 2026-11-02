<?php

namespace Tests\Unit;

use App\Domain\Ekim\EkimVerificationEvaluator;
use PHPUnit\Framework\TestCase;

class EkimVerificationEvaluatorTest extends TestCase
{
    private EkimVerificationEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new EkimVerificationEvaluator;
    }

    public function test_approved_document_with_valid_proofs_is_authentic(): void
    {
        $result = $this->evaluate('approved');

        $this->assertTrue($result['validation']['authentic']);
        $this->assertSame('valid', $result['validation']['state']);
        $this->assertSame('Dokumen asli dan valid.', $result['message']);
    }

    public function test_valid_document_with_expired_certificate_is_expired(): void
    {
        $result = $this->evaluate('approved', notExpired: false);

        $this->assertFalse($result['validation']['authentic']);
        $this->assertSame('expired', $result['validation']['state']);
        $this->assertSame('Dokumen asli, tetapi EKIM sudah kedaluwarsa.', $result['message']);
    }

    public function test_rejected_document_is_invalid(): void
    {
        $result = $this->evaluate('rejected', hsseValid: false, managerValid: false, finalProofValid: false);

        $this->assertSame('invalid', $result['validation']['state']);
        $this->assertFalse($result['validation']['authentic']);
    }

    public function test_draft_document_is_pending(): void
    {
        $result = $this->evaluate('draft', hsseValid: false, managerValid: false, finalProofValid: false);

        $this->assertSame('pending', $result['validation']['state']);
    }

    public function test_approved_document_with_invalid_hsse_proof_is_pending(): void
    {
        $result = $this->evaluate('approved', hsseValid: false);

        $this->assertSame('pending', $result['validation']['state']);
        $this->assertFalse($result['validation']['authentic']);
    }

    public function test_validation_details_preserve_each_input(): void
    {
        $result = $this->evaluator->evaluate('approved', false, false, true, false, true);

        $this->assertFalse($result['validation']['document_unchanged']);
        $this->assertFalse($result['validation']['hsse_valid']);
        $this->assertTrue($result['validation']['manager_valid']);
        $this->assertFalse($result['validation']['final_proof_valid']);
        $this->assertTrue($result['validation']['certificate_not_expired']);
        $this->assertSame('HMAC-SHA256', $result['validation']['algorithm']);
    }

    private function evaluate(
        string $status,
        bool $unchanged = true,
        bool $hsseValid = true,
        bool $managerValid = true,
        bool $finalProofValid = true,
        bool $notExpired = true,
    ): array {
        return $this->evaluator->evaluate(
            $status,
            $unchanged,
            $hsseValid,
            $managerValid,
            $finalProofValid,
            $notExpired,
        );
    }
}
