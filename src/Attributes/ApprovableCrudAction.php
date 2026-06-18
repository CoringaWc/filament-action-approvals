<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
/**
 * @deprecated Use ApprovableOperation.
 */
class ApprovableCrudAction extends ApprovableOperation {}
