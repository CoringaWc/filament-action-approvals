<?php

namespace CoringaWc\FilamentActionApprovals;

use CoringaWc\FilamentActionApprovals\Commands\ProcessApprovalSlaCommand;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentActionApprovalsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-action-approvals';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_approval_flows_table',
                'create_approval_steps_table',
                'create_approvals_table',
                'create_approval_step_instances_table',
                'create_approval_actions_table',
                'create_approval_delegations_table',
            ])
            ->runsMigrations()
            ->hasTranslations()
            ->hasCommand(ProcessApprovalSlaCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ApprovalEngine::class);
    }

    public function packageBooted(): void
    {
        if (config('filament-action-approvals.schedule_sla_command', true)) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('approval:process-sla')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }
    }
}
