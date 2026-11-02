<?php

namespace Tests\Unit;

use App\Domain\Checklist\ChecklistWorkflowPolicy;
use PHPUnit\Framework\TestCase;

class ChecklistWorkflowPolicyTest extends TestCase
{
    private ChecklistWorkflowPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ChecklistWorkflowPolicy;
    }

    public function test_admin_and_hsse_can_submit_draft(): void
    {
        foreach (['admin', 'hsse'] as $role) {
            $this->assertTrue($this->policy->canSubmit($role, 'draft'));
        }
    }

    public function test_manager_cannot_submit_draft(): void
    {
        $this->assertFalse($this->policy->canSubmit('manager_hsse', 'draft'));
    }

    public function test_non_draft_cannot_be_submitted(): void
    {
        $this->assertFalse($this->policy->canSubmit('admin', 'pending_hsse'));
    }

    public function test_hsse_can_reject_pending_hsse(): void
    {
        $this->assertTrue($this->policy->canReject('hsse', 'pending_hsse'));
    }

    public function test_hsse_cannot_reject_manager_stage(): void
    {
        $this->assertFalse($this->policy->canReject('hsse', 'signed_hsse'));
    }

    public function test_manager_can_reject_signed_hsse(): void
    {
        $this->assertTrue($this->policy->canReject('manager_hsse', 'signed_hsse'));
    }

    public function test_admin_can_reject_both_review_stages(): void
    {
        $this->assertTrue($this->policy->canReject('admin', 'pending_hsse'));
        $this->assertTrue($this->policy->canReject('admin', 'signed_hsse'));
    }

    public function test_approved_checklist_cannot_be_rejected_or_reset(): void
    {
        $this->assertFalse($this->policy->canReject('admin', 'approved'));
        $this->assertFalse($this->policy->canResetDraft('admin', 'approved'));
    }

    public function test_only_admin_can_reset_non_approved_checklist(): void
    {
        $this->assertTrue($this->policy->canResetDraft('admin', 'rejected'));
        $this->assertFalse($this->policy->canResetDraft('hsse', 'rejected'));
    }

    public function test_only_admin_can_delete_non_draft_checklist(): void
    {
        $this->assertTrue($this->policy->canDelete('admin', 'rejected'));
        $this->assertFalse($this->policy->canDelete('hsse', 'rejected'));
        $this->assertTrue($this->policy->canDelete('hsse', 'draft'));
        $this->assertFalse($this->policy->canDelete('manager_hsse', 'draft'));
    }
}
