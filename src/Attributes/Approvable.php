<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;
use BackedEnum;
use CoringaWc\FilamentActionApprovals\Contracts\DefinesApprovalAction;

#[Attribute(Attribute::TARGET_CLASS)]
final class Approvable
{
    /**
     * @param  class-string<BackedEnum&DefinesApprovalAction>  $actions
     */
    public function __construct(
        public string $actions,
        public ?string $namespace = null,
    ) {}
}
