<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use CoringaWc\FilamentAcl\FilamentPermissionServiceProvider;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsServiceProvider;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\Livewire\Partials\DataStoreOverride;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\DataStore;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\Filament\AdminPanelProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithLaravelMigrations;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            static fn (string $modelName): string => 'Workbench\\Database\\Factories\\'.class_basename($modelName).'Factory',
        );

        $this->app->singleton(DataStore::class, DataStoreOverride::class);

        $this->app['session.store']->start();
        $this->app['view']->share('errors', new ViewErrorBag);

        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        /** @var array<int, class-string> $providers */
        $providers = [
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            PermissionServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            WorkbenchServiceProvider::class,
            AdminPanelProvider::class,
            FilamentPermissionServiceProvider::class,
            FilamentActionApprovalsServiceProvider::class,
        ];

        return array_values(array_filter(
            $providers,
            static fn (string $provider): bool => class_exists($provider),
        ));
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $app['config']->set('app.locale', 'pt_BR');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('app.faker_locale', 'pt_BR');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('session.driver', 'array');

        $app['config']->set('filament-action-approvals.user_model', User::class);
        $app['config']->set('filament-action-approvals.approver_resolvers', [
            UserResolver::class,
        ]);

        $app['config']->set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);
        $app['config']->set('permission.column_names', [
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
            'role_pivot_key' => 'role_id',
            'permission_pivot_key' => 'permission_id',
        ]);
        $app['config']->set('permission.models', [
            'permission' => Permission::class,
            'role' => Role::class,
        ]);
        $app['config']->set('permission.teams', false);
        $app['config']->set('permission.testing', false);
        $app['config']->set('permission.cache.store', 'array');
        $app['config']->set('permission.cache.key', 'spatie.permission.cache');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create a basic single-step approval flow for a given model class.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int>  $approverIds
     */
    protected function createSingleStepFlow(
        string $modelClass,
        array $approverIds,
        ?string $actionKey = null,
    ): ApprovalFlow {
        $flow = ApprovalFlow::create([
            'name' => 'Test Flow',
            'approvable_type' => (new $modelClass)->getMorphClass(),
            'action_key' => $actionKey,
            'is_active' => true,
        ]);

        $flow->steps()->create([
            'name' => 'Step 1',
            'order' => 1,
            'type' => StepType::Single,
            'approver_resolver' => UserResolver::class,
            'approver_config' => ['user_ids' => $approverIds],
            'required_approvals' => 1,
        ]);

        return $flow;
    }

    /**
     * Create a multi-step sequential approval flow.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<array{name: string, approver_ids: array<int>, required?: int, sla_hours?: int}>  $steps
     */
    protected function createMultiStepFlow(string $modelClass, array $steps): ApprovalFlow
    {
        $flow = ApprovalFlow::create([
            'name' => 'Multi-Step Test Flow',
            'approvable_type' => (new $modelClass)->getMorphClass(),
            'is_active' => true,
        ]);

        foreach ($steps as $index => $stepData) {
            $flow->steps()->create([
                'name' => $stepData['name'],
                'order' => $index + 1,
                'type' => StepType::Single,
                'approver_resolver' => UserResolver::class,
                'approver_config' => ['user_ids' => $stepData['approver_ids']],
                'required_approvals' => $stepData['required'] ?? 1,
                'sla_hours' => $stepData['sla_hours'] ?? null,
                'escalation_action' => isset($stepData['sla_hours']) ? EscalationAction::Notify : null,
            ]);
        }

        return $flow;
    }
}
