<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Models\Approval;

class ApprovalActionResult
{
    public function __construct(
        public readonly bool $executed,
        public readonly bool $pendingApproval,
        public readonly ?Approval $approval,
        public readonly ?string $actionKey,
    ) {}

    public static function executed(string $actionKey): self
    {
        return new self(
            executed: true,
            pendingApproval: false,
            approval: null,
            actionKey: $actionKey,
        );
    }

    public static function pending(Approval $approval, string $actionKey): self
    {
        return new self(
            executed: false,
            pendingApproval: true,
            approval: $approval,
            actionKey: $actionKey,
        );
    }
}
