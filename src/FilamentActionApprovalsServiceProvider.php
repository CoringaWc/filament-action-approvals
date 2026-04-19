<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals;

use CoringaWc\FilamentActionApprovals\Commands\ProcessApprovalSlaCommand;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentActionApprovalsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-action-approvals';

    /**
     * @var list<string>
     */
    private const MIGRATION_FILE_NAMES = [
        'create_approval_flows_table',
        'create_approval_steps_table',
        'create_approvals_table',
        'create_approval_step_instances_table',
        'create_approval_actions_table',
        'create_approval_delegations_table',
    ];

    public function registeringPackage(): void
    {
        $translationsPath = dirname(__DIR__).'/resources/lang';

        // Filament may resolve resource labels before Package Tools boots translations.
        // Register the namespace early to avoid caching missing translation groups.
        $this->loadTranslationsFrom($translationsPath, static::$name);
        $this->loadJsonTranslationsFrom($translationsPath);
    }

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations(self::MIGRATION_FILE_NAMES)
            ->hasTranslations()
            ->hasCommand(ProcessApprovalSlaCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ApprovalEngine::class);
    }

    public function packageBooted(): void
    {
        $this->loadPackageMigrationsConditionally();

        if (config('filament-action-approvals.schedule_sla_command', true)) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('approval:process-sla')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }
    }

    private function loadPackageMigrationsConditionally(): void
    {
        foreach (self::MIGRATION_FILE_NAMES as $migrationFileName) {
            if ($this->hasPublishedMigration($migrationFileName)) {
                continue;
            }

            $this->loadMigrationsFrom(dirname(__DIR__)."/database/migrations/{$migrationFileName}.php");
        }
    }

    private function hasPublishedMigration(string $migrationFileName): bool
    {
        return glob(database_path("migrations/*_{$migrationFileName}.php")) !== [];
    }
}
