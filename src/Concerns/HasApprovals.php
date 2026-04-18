<?php

namespace CoringaWc\FilamentActionApprovals\Concerns;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovals
{
    /**
     * @return MorphMany<Approval, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function latestApproval(): ?Approval
    {
        /** @var ?Approval $approval */
        $approval = $this->approvals()->latest()->first();

        return $approval;
    }

    public function currentApproval(): ?Approval
    {
        /** @var ?Approval $approval */
        $approval = $this->approvals()
            ->where('status', ApprovalStatus::Pending)
            ->latest()
            ->first();

        return $approval;
    }

    public function approvalStatus(): ?ApprovalStatus
    {
        return $this->latestApproval()?->status;
    }

    public function submitForApproval(?ApprovalFlow $flow = null, ?int $submittedBy = null, ?string $actionKey = null): Approval
    {
        return app(ApprovalEngine::class)->submit($this, $flow, $submittedBy, $actionKey);
    }

    /**
     * @return array<string, string>
     */
    public static function approvableActions(): array
    {
        if (is_callable([static::class, 'resolveApprovableActions'])) {
            /** @var array<string, string> $actions */
            $actions = static::resolveApprovableActions();

            return $actions;
        }

        return [];
    }

    public function isPendingApproval(): bool
    {
        return $this->currentApproval() !== null;
    }

    public function isApproved(): bool
    {
        return $this->latestApproval()?->status === ApprovalStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->latestApproval()?->status === ApprovalStatus::Rejected;
    }

    // ──────────────────────────────────────────────────────────────
    // Submission policy
    //
    // Override these methods to control who can submit and whether
    // re-submission is allowed after approval/rejection.
    // ──────────────────────────────────────────────────────────────

    /**
     * Whether re-submission is allowed after approval or rejection.
     *
     * Override to return false for one-time-only approval models.
     * Default: true (re-submission allowed).
     */
    public function allowsApprovalResubmission(): bool
    {
        return true;
    }

    /**
     * Whether the given user is authorized to submit this model for approval.
     *
     * Override to add custom authorization logic (e.g. only the creator,
     * only users with a specific role, etc.).
     * Default: any authenticated user can submit.
     */
    public function canSubmitForApproval(?int $userId = null): bool
    {
        return true;
    }

    /**
     * Check if the submit action should be available for this record.
     * Combines pending check, resubmission policy, and authorization.
     */
    public function canBeSubmittedForApproval(?int $userId = null): bool
    {
        $resolvedUserId = $userId ?? auth()->id();

        if (is_string($resolvedUserId) && ctype_digit($resolvedUserId)) {
            $resolvedUserId = (int) $resolvedUserId;
        }

        // Already pending — can't submit again
        if ($this->isPendingApproval()) {
            return false;
        }

        // Check resubmission policy
        if (! $this->allowsApprovalResubmission()) {
            $latest = $this->latestApproval();

            // If there's a completed approval (approved/rejected), block resubmission
            if ($latest && in_array($latest->status, [ApprovalStatus::Approved, ApprovalStatus::Rejected])) {
                return false;
            }
        }

        // Check user authorization
        return $this->canSubmitForApproval(is_int($resolvedUserId) ? $resolvedUserId : null);
    }

    // ──────────────────────────────────────────────────────────────
    // Approval lifecycle callbacks
    //
    // Override these methods on your model to react to approval events.
    // They are called by the ApprovalEngine after the corresponding
    // action has been persisted.
    // ──────────────────────────────────────────────────────────────

    /**
     * Called when the model is submitted for approval.
     */
    public function onApprovalSubmitted(Approval $approval): void {}

    /**
     * Called when the full approval is completed (all steps approved).
     */
    public function onApprovalApproved(Approval $approval): void
    {
        if (is_callable([$this, 'afterApprovalApproved'])) {
            $this->afterApprovalApproved($approval);
        }
    }

    /**
     * Called when the approval is rejected at any step.
     */
    public function onApprovalRejected(Approval $approval): void {}

    /**
     * Called when the approval is cancelled.
     */
    public function onApprovalCancelled(Approval $approval): void {}

    /**
     * Called when a comment is added to the approval.
     */
    public function onApprovalCommented(ApprovalAction $action): void {}

    /**
     * Called when an approver delegates to another user.
     */
    public function onApprovalDelegated(ApprovalStepInstance $stepInstance, int $fromUserId, int $toUserId): void {}

    /**
     * Called when an individual step is completed (approved).
     */
    public function onApprovalStepCompleted(ApprovalStepInstance $stepInstance): void {}

    /**
     * Called when a step is escalated due to SLA breach.
     */
    public function onApprovalEscalated(ApprovalStepInstance $stepInstance): void {}
}
