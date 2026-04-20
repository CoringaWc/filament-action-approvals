<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableActions;
use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use ReflectionClass;

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

    public function submitForApproval(?ApprovalFlow $flow = null, int|string|null $submittedBy = null, ?string $actionKey = null): Approval
    {
        return app(ApprovalEngine::class)->submit($this, $flow, $submittedBy, $actionKey);
    }

    /**
     * @return array<string, string>
     */
    public static function approvableActions(): array
    {
        // #[ApprovableActions] attribute (enum class-string or array)
        $attribute = static::resolveApprovableActionsAttribute();

        if ($attribute !== null) {
            if (is_string($attribute->actions) && enum_exists($attribute->actions)) {
                return static::resolveApprovableActionsFromEnum($attribute->actions);
            }

            /** @var array<string, string> $actions */
            $actions = $attribute->actions;

            return $actions;
        }

        // Method override (from HasStateApprovals or custom)
        if (is_callable([static::class, 'resolveApprovableActions'])) {
            /** @var array<string, string> $actions */
            $actions = static::resolveApprovableActions();

            return $actions;
        }

        return [];
    }

    /**
     * Return the enum class-string for approvable actions, if configured
     * via #[ApprovableActions] attribute with an enum class.
     *
     * @return class-string<\BackedEnum>|null
     */
    public static function approvableActionsEnumClass(): ?string
    {
        $attribute = static::resolveApprovableActionsAttribute();

        if ($attribute !== null && is_string($attribute->actions) && enum_exists($attribute->actions)) {
            return $attribute->actions;
        }

        return null;
    }

    /**
     * Resolve the #[ApprovableActions] attribute from the model class.
     *
     * @var array<class-string, ?ApprovableActions>
     */
    private static array $approvableActionsAttributeCache = [];

    protected static function resolveApprovableActionsAttribute(): ?ApprovableActions
    {
        if (array_key_exists(static::class, self::$approvableActionsAttributeCache)) {
            return self::$approvableActionsAttributeCache[static::class];
        }

        $ref = new ReflectionClass(static::class);
        $attrs = $ref->getAttributes(ApprovableActions::class);

        return self::$approvableActionsAttributeCache[static::class] = $attrs !== [] ? $attrs[0]->newInstance() : null;
    }

    /**
     * Resolve approvable actions from a backed enum implementing HasLabel.
     *
     * @param  class-string<\BackedEnum>  $enumClass
     * @return array<string, string>
     */
    protected static function resolveApprovableActionsFromEnum(string $enumClass): array
    {
        if (! enum_exists($enumClass)) {
            return [];
        }

        $actions = [];

        /** @var \BackedEnum[] $cases */
        $cases = $enumClass::cases();

        foreach ($cases as $case) {
            $key = (string) $case->value;
            $label = method_exists($case, 'getLabel') ? $case->getLabel() : $key;
            $actions[$key] = $label;
        }

        return $actions;
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

    /**
     * Get the rejection reason from the latest rejected approval.
     *
     * Returns the comment from the rejection action (ActionType::Rejected)
     * in the most recent completed approval that was rejected.
     */
    public function latestRejectionReason(): ?string
    {
        $latestApproval = $this->latestApproval();

        if (! $latestApproval || $latestApproval->status !== ApprovalStatus::Rejected) {
            return null;
        }

        /** @var ?ApprovalAction $rejectionAction */
        $rejectionAction = $latestApproval->actions()
            ->where('type', ActionType::Rejected)
            ->latest()
            ->first();

        return $rejectionAction?->comment;
    }

    // ──────────────────────────────────────────────────────────────
    // Submission policy
    //
    // Override these methods to control who can submit and whether
    // re-submission is allowed after a completed approval.
    // ──────────────────────────────────────────────────────────────

    /**
     * Whether re-submission is allowed after a completed approval.
     *
     * Override to return false for one-time-only approval models, or implement
     * a custom rule based on the latest approval status.
     * Default: approved and cancelled approvals cannot be resubmitted.
     */
    public function allowsApprovalResubmission(): bool
    {
        $latest = $this->latestApproval();

        return ! $latest || ! in_array($latest->status, [ApprovalStatus::Approved, ApprovalStatus::Cancelled], true);
    }

    /**
     * Whether the given user is authorized to submit this model for approval.
     *
     * Override to add custom authorization logic (e.g. only the creator,
     * only users with a specific role, or only for a specific approvable action).
     * Default: only users resolved as approvers in a matching submission flow can submit.
     */
    public function canSubmitForApproval(?string $actionKey = null, int|string|null $userId = null): bool
    {
        $resolvedUserId = $this->normalizeSubmissionPolicyUserId($userId ?? auth()->id());

        if ($resolvedUserId === null) {
            return false;
        }

        return ApprovalFlow::getSubmissionFlowsForModel($this, $actionKey)
            ->contains(fn (ApprovalFlow $flow): bool => $this->flowIncludesSubmissionApprover($flow, $resolvedUserId));
    }

    /**
     * Check if the submit action should be available for this record.
     * Combines pending check, resubmission policy, and authorization.
     */
    public function canBeSubmittedForApproval(?string $actionKey = null, int|string|null $userId = null): bool
    {
        $resolvedUserId = $this->normalizeSubmissionPolicyUserId($userId ?? auth()->id());

        // Already pending — can't submit again
        if ($this->isPendingApproval()) {
            return false;
        }

        // Check resubmission policy
        if (! $this->allowsApprovalResubmission()) {
            $latest = $this->latestApproval();

            // If there's a completed approval that the model does not allow to restart, block resubmission.
            if ($latest && in_array($latest->status, [ApprovalStatus::Approved, ApprovalStatus::Rejected, ApprovalStatus::Cancelled], true)) {
                return false;
            }
        }

        // Check user authorization
        return $this->canSubmitForApproval($actionKey, $resolvedUserId);
    }

    protected function flowIncludesSubmissionApprover(ApprovalFlow $flow, int|string $userId): bool
    {
        foreach ($flow->steps as $step) {
            $approverIds = array_map(
                fn (int|string $approverId): int|string => $this->normalizeSubmissionPolicyUserId($approverId) ?? $approverId,
                $step->resolveApproverIds($this),
            );

            if (in_array($userId, $approverIds, true)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeSubmissionPolicyUserId(int|string|null $userId): int|string|null
    {
        if ($userId === null) {
            return null;
        }

        if (is_string($userId) && ctype_digit($userId)) {
            return (int) $userId;
        }

        return $userId;
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
    public function onApprovalDelegated(ApprovalStepInstance $stepInstance, int|string $fromUserId, int|string $toUserId): void {}

    /**
     * Called when an individual step is completed (approved).
     */
    public function onApprovalStepCompleted(ApprovalStepInstance $stepInstance): void {}

    /**
     * Called when a step is escalated due to SLA breach.
     */
    public function onApprovalEscalated(ApprovalStepInstance $stepInstance): void {}
}
