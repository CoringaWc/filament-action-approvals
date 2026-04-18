<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use Closure;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovalActionResult;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait HasApprovableActions
{
    abstract public function executeApprovedAction(string $actionKey): void;

    public function executeWithApproval(
        string $actionKey,
        ?Closure $onExecute = null,
        ?int $submittedBy = null,
    ): ApprovalActionResult {
        $flow = $this->resolveApprovalFlow($actionKey);

        if (! $flow instanceof ApprovalFlow) {
            $onExecute
                ? $onExecute($this, $actionKey)
                : $this->executeApprovedAction($actionKey);

            return ApprovalActionResult::executed($actionKey);
        }

        $approval = app(ApprovalEngine::class)->submit(
            approvable: $this,
            flow: $flow,
            submittedBy: $submittedBy,
            actionKey: $actionKey,
        );

        return ApprovalActionResult::pending($approval, $actionKey);
    }

    public function hasPendingApprovalForAction(string $actionKey): bool
    {
        /** @phpstan-ignore method.notFound */
        return $this->approvals()
            ->where('status', ApprovalStatus::Pending)
            ->whereHas('flow', fn ($query) => $query->where('action_key', $actionKey))
            ->exists();
    }

    public function resolveApprovalFlow(string $actionKey): ?ApprovalFlow
    {
        return ApprovalFlow::forAction($this, $actionKey)->first();
    }

    protected function afterApprovalApproved(Approval $approval): void
    {
        $actionKey = $approval->flow?->action_key;

        if (blank($actionKey)) {
            return;
        }

        $this->executeApprovedAction($actionKey);
    }
}
