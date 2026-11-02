<?php

namespace App\Domain\Checklist;

final class ChecklistWorkflowPolicy
{
    public function canSubmit(string $role, string $status): bool
    {
        return in_array($role, ['admin', 'hsse'], true) && $status === 'draft';
    }

    public function canReject(string $role, string $status): bool
    {
        if (! in_array($status, ['pending_hsse', 'signed_hsse'], true)) {
            return false;
        }

        return $role === 'admin'
            || ($role === 'hsse' && $status === 'pending_hsse')
            || ($role === 'manager_hsse' && $status === 'signed_hsse');
    }

    public function canResetDraft(string $role, string $status): bool
    {
        return $role === 'admin' && $status !== 'approved';
    }

    public function canDelete(string $role, string $status): bool
    {
        if (! in_array($role, ['admin', 'hsse'], true)) {
            return false;
        }

        return $status === 'draft' || $role === 'admin';
    }
}
