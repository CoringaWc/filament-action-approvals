<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Database\Eloquent\Model;

final readonly class ApprovalOperationSubmissionContext
{
    /**
     * @param  array<string, mixed>  $governedPayload
     * @param  array<string, mixed>  $directPayload
     * @param  list<string>  $ignoredPayloadKeys
     */
    public function __construct(
        public Approval $approval,
        public Model $approvable,
        public ApprovableOperation $definition,
        public string $actionKey,
        public string $operation,
        public array $governedPayload,
        public array $directPayload,
        public array $ignoredPayloadKeys,
        public Model|int|string|null $submittedBy,
    ) {}
}
