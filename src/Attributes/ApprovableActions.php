<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApprovableActions
{
    /**
     * @param  class-string<\BackedEnum>|array<string, string>  $actions
     */
    public function __construct(
        public string|array $actions,
    ) {}
}
