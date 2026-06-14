<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Contracts;

interface InterceptsApprovalCrudActions
{
    public const string OperationEdit = 'edit';

    public const string OperationDelete = 'delete';

    public function approvalCrudActionKey(string $operation): ?string;

    /**
     * @return list<string>
     */
    public function approvalCrudFields(string $operation): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyApprovedCrudAction(string $operation, array $payload): void;
}
