<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Enums;

use Filament\Support\Contracts\HasLabel;

enum EscalationAction: string implements HasLabel
{
    case Notify = 'notify';
    case AutoApprove = 'auto_approve';
    case Reassign = 'reassign';
    case Reject = 'reject';

    public function getLabel(): string
    {
        return __('filament-action-approvals::approval.escalation.'.$this->value);
    }
}
