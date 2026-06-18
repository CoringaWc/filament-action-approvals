<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Contracts;

/**
 * @deprecated Use InterceptsApprovalOperations.
 */
interface InterceptsApprovalCrudActions extends InterceptsApprovalOperations
{
    public const string OperationUpdate = 'update';

    public const string OperationEdit = 'edit';

    public const string OperationDelete = 'delete';
}
