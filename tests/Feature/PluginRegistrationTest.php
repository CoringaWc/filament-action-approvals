<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\ApprovalResource;
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

it('publishes config and applies workbench overrides', function (): void {
    expect(config('filament-action-approvals'))
        ->not->toBeEmpty()
        ->and(config('filament-action-approvals.user_model'))->not->toBeNull()
        ->and(config('filament-action-approvals.approver_resolvers'))->toBeArray()
        ->and(config('filament-action-approvals.approvals_resource.enabled'))->toBeTrue()
        ->and(config('filament-action-approvals.actions.approve'))->toBeTrue()
        ->and(config('filament-action-approvals.actions.comment'))->toBeTrue()
        ->and(config('filament-action-approvals.actions.delegate'))->toBeTrue()
        ->and(config('filament-action-approvals.approvals_resource.group_record_actions'))->toBeTrue()
        ->and(config('filament-action-approvals.dashboard.enabled'))->toBeFalse();
});

it('keeps comment and delegate disabled in the package default config', function (): void {
    /** @var array<string, mixed> $config */
    $config = require __DIR__.'/../../config/filament-action-approvals.php';

    expect($config['actions']['comment'])->toBeFalse()
        ->and($config['actions']['delegate'])->toBeFalse()
        ->and($config['widgets']['enabled'])->toBeNull();
});

it('keeps global panel widgets disabled by default when the dedicated dashboard is enabled', function (): void {
    config()->set('filament-action-approvals.widgets.enabled', null);

    $plugin = (new FilamentActionApprovalsPlugin)->dashboard();

    $method = new ReflectionMethod($plugin, 'hasWidgets');

    expect($method->invoke($plugin))->toBeFalse();
});

it('still allows explicitly enabling global panel widgets alongside the dedicated dashboard', function (): void {
    $plugin = (new FilamentActionApprovalsPlugin)
        ->dashboard()
        ->widgets();

    $method = new ReflectionMethod($plugin, 'hasWidgets');

    expect($method->invoke($plugin))->toBeTrue();
});

it('resolves user model from plugin', function (): void {
    expect(FilamentActionApprovalsPlugin::resolveUserModel())->toBe(User::class);
});

it('keeps the approval resource without a dedicated view page', function (): void {
    expect(ApprovalResource::hasPage('view'))->toBeFalse();
});
