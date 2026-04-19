<?php

declare(strict_types=1);

it('resolves package translation keys before filament boot uses them', function (): void {
    expect(app('translator')->has('filament-action-approvals::approval.flow_resource_plural'))->toBeTrue()
        ->and(__('filament-action-approvals::approval.flow_resource_plural'))->toBe('Fluxos de Aprovação')
        ->and(__('filament-action-approvals::approval.navigation_group'))->toBe('Aprovações');
});
