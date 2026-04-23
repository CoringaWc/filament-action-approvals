<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

class ApprovalNotificationAction
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $label = null,
        public readonly bool $shouldOpenInNewTab = false,
    ) {}
}
