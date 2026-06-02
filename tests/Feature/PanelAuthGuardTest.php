<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Filament\Facades\Filament;
use Workbench\App\Models\User;

it('resolves the authenticated user id from the current panel guard', function (): void {
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    Filament::getPanel('admin')->authGuard('admin');

    $webUser = User::factory()->create();
    $adminUser = User::factory()->create();

    $this->actingAs($webUser, 'web');
    $this->actingAs($adminUser, 'admin');

    Filament::setCurrentPanel('admin');

    expect(FilamentActionApprovalsPlugin::resolveAuthGuard())->toBe('admin')
        ->and(FilamentActionApprovalsPlugin::resolveAuthenticatedUserId())->toBe($adminUser->getKey());
});
