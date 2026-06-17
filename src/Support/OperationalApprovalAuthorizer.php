<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use Illuminate\Auth\Access\AuthorizationException;

final class OperationalApprovalAuthorizer
{
    public const OperationApprove = 'approve';

    public const OperationReject = 'reject';

    public function canApprove(Approval $approval, int|string|null $userId = null): bool
    {
        return $this->canResolve($approval, $userId, self::OperationApprove);
    }

    public function canReject(Approval $approval, int|string|null $userId = null): bool
    {
        return $this->canResolve($approval, $userId, self::OperationReject);
    }

    /**
     * @throws AuthorizationException
     */
    public function ensureCanApprove(Approval $approval, int|string|null $userId = null): void
    {
        $this->ensureCanResolve($approval, $userId, self::OperationApprove);
    }

    /**
     * @throws AuthorizationException
     */
    public function ensureCanReject(Approval $approval, int|string|null $userId = null): void
    {
        $this->ensureCanResolve($approval, $userId, self::OperationReject);
    }

    public function canResolve(Approval $approval, int|string|null $userId = null, string $operation = self::OperationApprove): bool
    {
        $userId = UserModelKey::normalize($userId ?? CurrentPanelUser::id());

        if (! is_int($userId) && ! is_string($userId)) {
            return false;
        }

        if (! FilamentActionApprovalsPlugin::canRunOperationalApprovalAction($approval)) {
            return false;
        }

        $stepInstance = $approval->currentStepInstance();

        if (! $stepInstance instanceof ApprovalStepInstance || $stepInstance->status !== StepInstanceStatus::Waiting) {
            return false;
        }

        return match ($operation) {
            self::OperationReject => $approval->canBeRejectedBy($userId),
            default => $approval->canBeApprovedBy($userId),
        };
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureCanResolve(Approval $approval, int|string|null $userId, string $operation): void
    {
        if (! $this->canResolve($approval, $userId, $operation)) {
            throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
        }
    }
}
