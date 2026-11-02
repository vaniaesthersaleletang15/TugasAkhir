<?php

namespace App\Domain\Checklist;

final class ChecklistSignaturePolicy
{
    public function roleCanSign(string $accountRole, string $signatureRole): bool
    {
        return match ($signatureRole) {
            'hsse' => in_array($accountRole, ['admin', 'hsse'], true),
            'manajer' => $accountRole === 'manager_hsse',
            default => false,
        };
    }

    public function statusAllowsSignature(string $signatureRole, string $status): bool
    {
        return match ($signatureRole) {
            'hsse' => in_array($status, ['draft', 'pending_hsse'], true),
            'manajer' => $status === 'signed_hsse',
            default => false,
        };
    }

    public function signerNameMatches(string $providedName, string $accountName): bool
    {
        return mb_strtolower(trim($providedName)) === mb_strtolower(trim($accountName));
    }

    public function contentError(array $badItems, ?string $validUntil, string $referenceDate): ?string
    {
        if ($badItems !== []) {
            return 'Tanda tangan ditolak: terdapat item TIDAK BAIK — '.implode('; ', $badItems);
        }
        if ($validUntil && $validUntil < $referenceDate) {
            return 'Tanda tangan ditolak: EKIM sudah kedaluwarsa.';
        }

        return null;
    }
}
