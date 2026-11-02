<?php

namespace App\Domain\Ekim;

final class EkimVerificationEvaluator
{
    public function evaluate(
        string $approvalStatus,
        bool $documentUnchanged,
        bool $hsseValid,
        bool $managerValid,
        bool $finalProofValid,
        bool $notExpired,
    ): array {
        $proofsValid = $hsseValid && $managerValid && $finalProofValid;
        $isFinal = in_array($approvalStatus, ['approved', 'expired'], true);
        $authentic = $isFinal && $proofsValid && $notExpired;

        $state = match (true) {
            $authentic => 'valid',
            $isFinal && $proofsValid && ! $notExpired => 'expired',
            $approvalStatus === 'rejected' => 'invalid',
            default => 'pending',
        };

        $message = match ($state) {
            'valid' => 'Dokumen asli dan valid.',
            'expired' => 'Dokumen asli, tetapi EKIM sudah kedaluwarsa.',
            default => 'Dokumen belum valid.',
        };

        return [
            'message' => $message,
            'validation' => [
                'authentic' => $authentic,
                'document_unchanged' => $documentUnchanged,
                'hsse_valid' => $hsseValid,
                'manager_valid' => $managerValid,
                'final_proof_valid' => $finalProofValid,
                'certificate_not_expired' => $notExpired,
                'state' => $state,
                'algorithm' => 'HMAC-SHA256',
            ],
        ];
    }
}
