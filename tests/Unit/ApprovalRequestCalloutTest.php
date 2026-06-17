<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Schemas\Components\ApprovalRequestCallout;
use Filament\Schemas\Components\Callout;

it('builds a reusable schema callout for approval requests', function (): void {
    expect(ApprovalRequestCallout::make('Review required', 'The record waits for review.'))
        ->toBeInstanceOf(Callout::class)
        ->and(FilamentActionApprovalsPlugin::approvalRequestCallout())
        ->toBeInstanceOf(Callout::class);
});
