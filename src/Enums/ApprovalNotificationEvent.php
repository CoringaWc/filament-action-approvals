<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Enums;

enum ApprovalNotificationEvent: string
{
    case Requested = 'requested';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case Cancelled = 'cancelled';

    case Escalated = 'escalated';

    case SlaWarning = 'sla_warning';
}
