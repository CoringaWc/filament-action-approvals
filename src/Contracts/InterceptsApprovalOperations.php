<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Contracts;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Models\Approval;

interface InterceptsApprovalOperations
{
    public function approvalActionKeyForOperation(ApprovalOperation|string $operation): ?string;

    /**
     * @return list<string>
     */
    public function approvalFieldsForOperation(ApprovalOperation|string $operation): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function approvalPayloadForOperation(ApprovalOperation|string $operation, array $data): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitApproval(ApprovalOperation|string $operation, array $data = []): Approval;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyApprovedOperation(ApprovalOperation|string $operation, array $payload): void;
}
