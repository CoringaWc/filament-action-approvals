<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Feature;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Workbench\App\Models\User;

class PluginRegistrationTest extends TestCase
{
    public function test_service_provider_registers_approval_engine(): void
    {
        $this->assertInstanceOf(
            ApprovalEngine::class,
            $this->app->make(ApprovalEngine::class),
        );
    }

    public function test_approval_engine_is_singleton(): void
    {
        $first = $this->app->make(ApprovalEngine::class);
        $second = $this->app->make(ApprovalEngine::class);

        $this->assertSame($first, $second);
    }

    public function test_config_is_published(): void
    {
        $this->assertNotEmpty(config('filament-action-approvals'));
        $this->assertNotNull(config('filament-action-approvals.user_model'));
        $this->assertIsArray(config('filament-action-approvals.approver_resolvers'));
    }

    public function test_plugin_resolves_user_model(): void
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        $this->assertSame(User::class, $userModel);
    }
}
