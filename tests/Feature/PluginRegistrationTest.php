<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Workbench\App\Models\User;

it('registers approval engine in service container', function (): void {
    expect(app(ApprovalEngine::class))->toBeInstanceOf(ApprovalEngine::class);
});

it('registers approval engine as singleton', function (): void {
    $first = app(ApprovalEngine::class);
    $second = app(ApprovalEngine::class);

    expect($first)->toBe($second);
});

it('publishes config', function (): void {
    expect(config('filament-action-approvals'))
        ->not->toBeEmpty()
        ->and(config('filament-action-approvals.user_model'))->not->toBeNull()
        ->and(config('filament-action-approvals.approver_resolvers'))->toBeArray();
});

it('resolves user model from plugin', function (): void {
    expect(FilamentActionApprovalsPlugin::resolveUserModel())->toBe(User::class);
});
