<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Contracts;

use CoringaWc\FilamentActionApprovals\Support\ApprovalDefinition;

interface DefinesApprovalAction
{
    public function approval(): ApprovalDefinition;
}
