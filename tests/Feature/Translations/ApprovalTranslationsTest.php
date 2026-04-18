<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Feature\Translations;

use CoringaWc\FilamentActionApprovals\Tests\TestCase;

class ApprovalTranslationsTest extends TestCase
{
    public function test_it_resolves_package_translation_keys_before_filament_boot_uses_them(): void
    {
        self::assertTrue(app('translator')->has('filament-action-approvals::approval.flow_resource_plural'));
        self::assertSame(
            'Fluxos de Aprovação',
            __('filament-action-approvals::approval.flow_resource_plural'),
        );
        self::assertSame(
            'Aprovações',
            __('filament-action-approvals::approval.navigation_group'),
        );
    }
}
