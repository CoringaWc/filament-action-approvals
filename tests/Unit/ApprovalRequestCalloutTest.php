<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Schemas\Components\ApprovalRequestCallout;
use Filament\Schemas\Components\Callout;

it('builds a reusable schema callout for approval requests', function (): void {
    $callout = ApprovalRequestCallout::make('Review required', 'The record waits for review.');

    expect($callout)
        ->toBeInstanceOf(Callout::class)
        ->and($callout->getKey(isAbsolute: false))->toBe('approval-request-callout')
        ->and($callout->getHeading())->toBe('Review required')
        ->and($callout->getDescription())->toBe('The record waits for review.')
        ->and($callout->getStatus())->toBe('warning')
        ->and($callout->getColumnSpan())->toBe(['default' => 'full'])
        ->and(FilamentActionApprovalsPlugin::approvalRequestCallout())
        ->toBeInstanceOf(Callout::class);
});
