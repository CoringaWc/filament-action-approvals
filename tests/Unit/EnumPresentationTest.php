<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use Filament\Support\Icons\Heroicon;

it('exposes approval status presentation metadata for Filament badges', function (): void {
    expect(ApprovalStatus::Pending->getLabel())
        ->toBe(__('filament-action-approvals::approval.status.pending'))
        ->and(ApprovalStatus::Pending->getColor())->toBe('warning')
        ->and(ApprovalStatus::Pending->getIcon())->toBe(Heroicon::OutlinedClock)
        ->and(ApprovalStatus::Approved->getIcon())->toBe(Heroicon::OutlinedCheckCircle)
        ->and(ApprovalStatus::Rejected->getIcon())->toBe(Heroicon::OutlinedXCircle)
        ->and(ApprovalStatus::Cancelled->getIcon())->toBe(Heroicon::OutlinedMinusCircle);
});

it('exposes action type presentation metadata for Filament badges', function (): void {
    expect(ActionType::Submitted->getLabel())
        ->toBe(__('filament-action-approvals::approval.action_type.submitted'))
        ->and(ActionType::Submitted->getColor())->toBe('info')
        ->and(ActionType::Submitted->getIcon())->toBe(Heroicon::OutlinedPaperAirplane)
        ->and(ActionType::Commented->getIcon())->toBe(Heroicon::OutlinedChatBubbleLeftRight)
        ->and(ActionType::Delegated->getIcon())->toBe(Heroicon::OutlinedArrowRightCircle)
        ->and(ActionType::Escalated->getIcon())->toBe(Heroicon::OutlinedExclamationTriangle)
        ->and(ActionType::Returned->getIcon())->toBe(Heroicon::OutlinedArrowUturnLeft);
});

it('exposes step status presentation metadata for Filament badges', function (): void {
    expect(StepInstanceStatus::Waiting->getLabel())
        ->toBe(__('filament-action-approvals::approval.step_status.waiting'))
        ->and(StepInstanceStatus::Waiting->getColor())->toBe('warning')
        ->and(StepInstanceStatus::Waiting->getIcon())->toBe(Heroicon::OutlinedClock)
        ->and(StepInstanceStatus::Approved->getIcon())->toBe(Heroicon::OutlinedCheckCircle)
        ->and(StepInstanceStatus::Rejected->getIcon())->toBe(Heroicon::OutlinedXCircle)
        ->and(StepInstanceStatus::Skipped->getIcon())->toBe(Heroicon::OutlinedArrowUturnLeft);
});
