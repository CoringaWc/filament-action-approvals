<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Widgets\PendingApprovalsTable;
use CoringaWc\FilamentActionApprovals\Widgets\PendingApprovalsWidget;
use Illuminate\Contracts\Support\Htmlable;

it('uses the translated table heading for the dashboard widget', function (): void {
    $widget = new class extends PendingApprovalsWidget
    {
        public function heading(): string|Htmlable|null
        {
            return $this->getTableHeading();
        }
    };

    expect($widget->heading())
        ->toBe(__('filament-action-approvals::approval.widgets.pending_heading'));
});

it('hides the duplicated table heading when embedded in the action modal', function (): void {
    $widget = new class extends PendingApprovalsTable
    {
        public function heading(): string|Htmlable|null
        {
            return $this->getTableHeading();
        }
    };

    expect($widget->heading())->toBe('');
});
