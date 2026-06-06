<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\Pages\ApprovalsDashboard;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\ApprovalFlowResource;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\ApprovalResource;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalAnalyticsWidget;
use CoringaWc\FilamentActionApprovals\Widgets\PendingApprovalsWidget;
use Filament\Clusters\Cluster;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

class FilamentActionApprovalsPlugin implements Plugin
{
    protected ?bool $hasFlowResource = null;

    protected ?bool $hasApprovalResource = null;

    protected ?bool $hasDashboardPage = null;

    protected ?bool $hasWidgets = null;

    /** @var array<class-string>|null */
    protected ?array $approverResolvers = null;

    /** @var class-string|null */
    protected ?string $userModel = null;

    protected ?string $navigationGroup = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-action-approvals';
    }

    public function flowResource(bool $enabled = true): static
    {
        $this->hasFlowResource = $enabled;

        return $this;
    }

    public function approvalResource(bool $enabled = true): static
    {
        $this->hasApprovalResource = $enabled;

        return $this;
    }

    public function dashboard(bool $enabled = true): static
    {
        $this->hasDashboardPage = $enabled;

        return $this;
    }

    public function widgets(bool $enabled = true): static
    {
        $this->hasWidgets = $enabled;

        return $this;
    }

    /**
     * Override the approver resolvers for this panel.
     *
     * @param  array<class-string<ApproverResolver>>  $resolvers
     */
    public function resolvers(array $resolvers): static
    {
        $this->approverResolvers = $resolvers;

        return $this;
    }

    /**
     * Override the user model for this panel.
     *
     * @param  class-string  $model
     */
    public function userModel(string $model): static
    {
        $this->userModel = $model;

        return $this;
    }

    /**
     * Override the navigation group for this panel.
     */
    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    /**
     * @return array<class-string<ApproverResolver>>
     */
    public function getApproverResolvers(): array
    {
        /** @var array<class-string<ApproverResolver>> $resolvers */
        $resolvers = $this->approverResolvers ?? config('filament-action-approvals.approver_resolvers', []);

        return $resolvers;
    }

    public function getUserModel(): string
    {
        /** @var class-string $model */
        $model = $this->userModel
            ?? config('filament-action-approvals.user_model')
            ?? config('auth.providers.users.model');

        return $model;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup
            ?? config('filament-action-approvals.navigation_group')
            ?? __('filament-action-approvals::approval.navigation_group');
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if ($this->hasFlowResource()) {
            $resources[] = ApprovalFlowResource::class;
        }

        if ($this->hasApprovalResource()) {
            $resources[] = ApprovalResource::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }

        if ($this->hasDashboardPage()) {
            $panel->pages([
                ApprovalsDashboard::class,
            ]);
        }

        if ($this->hasWidgets()) {
            $panel->widgets([
                PendingApprovalsWidget::class,
                ApprovalAnalyticsWidget::class,
            ]);
        }
    }

    public function boot(Panel $panel): void {}

    protected function hasFlowResource(): bool
    {
        return $this->hasFlowResource
            ?? (bool) config('filament-action-approvals.resource.enabled', true);
    }

    protected function hasApprovalResource(): bool
    {
        return $this->hasApprovalResource
            ?? (bool) config('filament-action-approvals.approvals_resource.enabled', true);
    }

    protected function hasDashboardPage(): bool
    {
        return $this->hasDashboardPage
            ?? (bool) config('filament-action-approvals.dashboard.enabled', false);
    }

    protected function hasWidgets(): bool
    {
        if ($this->hasWidgets !== null) {
            return $this->hasWidgets;
        }

        $configuredWidgets = config('filament-action-approvals.widgets.enabled');

        if (is_bool($configuredWidgets)) {
            return $configuredWidgets;
        }

        return ! $this->hasDashboardPage();
    }

    /**
     * Get the current plugin instance from the active Filament panel.
     */
    public static function current(): ?static
    {
        try {
            $panel = filament()->getCurrentOrDefaultPanel();

            if (! $panel) {
                return null;
            }

            $plugin = $panel->getPlugin('filament-action-approvals');

            return $plugin instanceof static ? $plugin : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the user model, preferring plugin override then config.
     */
    public static function resolveUserModel(): string
    {
        /** @var class-string $model */
        $model = static::current()?->getUserModel()
            ?? config('filament-action-approvals.user_model')
            ?? config('auth.providers.users.model');

        return $model;
    }

    /**
     * Resolve the approver resolvers, preferring plugin override then config.
     */
    /**
     * @return array<class-string<ApproverResolver>>
     */
    public static function resolveApproverResolvers(): array
    {
        /** @var array<class-string<ApproverResolver>> $resolvers */
        $resolvers = static::current()?->getApproverResolvers()
            ?? config('filament-action-approvals.approver_resolvers', []);

        return $resolvers;
    }

    /**
     * Resolve the navigation group, preferring plugin override then config.
     */
    public static function resolveNavigationGroup(): ?string
    {
        return static::current()?->getNavigationGroup()
            ?? config('filament-action-approvals.navigation_group')
            ?? __('filament-action-approvals::approval.navigation_group');
    }

    /**
     * Resolve the privileged configuration, merging the deprecated
     * `super_admin` alias block into the canonical `privileged` block.
     *
     * Resolution rules: `enabled` via OR, `roles` and `user_ids` via union,
     * `hide_from_selects` via AND. `apply_directly` only reads `privileged`.
     *
     * @return array{enabled: bool, roles: list<string>, user_ids: list<int|string>, hide_from_selects: bool, apply_directly: bool}
     */
    protected static function privilegedConfig(): array
    {
        $prefix = 'filament-action-approvals.';

        $enabled = (bool) config($prefix.'privileged.enabled', false)
            || (bool) config($prefix.'super_admin.enabled', false);

        /** @var list<string> $roles */
        $roles = array_values(array_unique(array_merge(
            (array) config($prefix.'privileged.roles', []),
            (array) config($prefix.'super_admin.roles', []),
        )));

        /** @var list<int|string> $userIds */
        $userIds = array_values(array_unique(array_merge(
            (array) config($prefix.'privileged.user_ids', []),
            (array) config($prefix.'super_admin.user_ids', []),
        ), SORT_REGULAR));

        $hideFromSelects = (bool) config($prefix.'privileged.hide_from_selects', true)
            && (bool) config($prefix.'super_admin.hide_from_selects', true);

        return [
            'enabled' => $enabled,
            'roles' => $roles,
            'user_ids' => $userIds,
            'hide_from_selects' => $hideFromSelects,
            'apply_directly' => (bool) config($prefix.'privileged.bypass.apply_directly', false),
        ];
    }

    /**
     * Check if the given user is a privileged user for approval purposes.
     *
     * Privileged users can see and act on all approval actions regardless
     * of being an assigned approver. Controlled via the `privileged` config
     * (with the deprecated `super_admin` block merged in as an alias).
     */
    public static function isSuperAdmin(int|string|null $userId = null): bool
    {
        $config = static::privilegedConfig();

        if (! $config['enabled']) {
            return false;
        }

        $userId ??= auth()->id();

        if ($userId === null) {
            return false;
        }

        if (in_array($userId, $config['user_ids'], true)) {
            return true;
        }

        if ($config['roles'] === []) {
            return false;
        }

        $userModel = static::resolveUserModel();

        /** @var Model|null $user */
        $user = $userModel::query()->find($userId);

        if (! $user) {
            return false;
        }

        // Support spatie/laravel-permission if available
        if (method_exists($user, 'hasAnyRole')) {
            /** @var bool $hasRole */
            $hasRole = $user->hasAnyRole($config['roles']);

            return $hasRole;
        }

        return false;
    }

    /**
     * Determine whether the given user may apply an approvable action directly,
     * bypassing the regular approval flow.
     *
     * Direct application is gated by the `privileged.bypass.apply_directly`
     * toggle and, by default, delegates the identity check to
     * {@see isSuperAdmin()}. Override this method to customize authorization.
     */
    public static function canApplyDirectly(int|string|null $userId = null): bool
    {
        if (! static::privilegedConfig()['apply_directly']) {
            return false;
        }

        return static::isSuperAdmin($userId);
    }

    /**
     * Whether privileged users/roles should be hidden from resolver selects.
     *
     * Only applies when privileged access is enabled AND hide_from_selects is true.
     */
    public static function shouldHideSuperAdminsFromSelects(): bool
    {
        $config = static::privilegedConfig();

        return $config['enabled'] && $config['hide_from_selects'];
    }

    /**
     * Get the user IDs configured as privileged (for filtering from selects).
     *
     * @return list<int|string>
     */
    public static function superAdminUserIds(): array
    {
        if (! static::shouldHideSuperAdminsFromSelects()) {
            return [];
        }

        return static::privilegedConfig()['user_ids'];
    }

    /**
     * Get the role names configured as privileged (for filtering from selects).
     *
     * @return list<string>
     */
    public static function superAdminRoles(): array
    {
        if (! static::shouldHideSuperAdminsFromSelects()) {
            return [];
        }

        return static::privilegedConfig()['roles'];
    }

    /**
     * Resolve the resource cluster class from config.
     *
     * @return class-string<Cluster>|null
     */
    public static function resolveResourceCluster(): ?string
    {
        /** @var class-string<Cluster>|null $cluster */
        $cluster = config('filament-action-approvals.resource.cluster');

        return $cluster;
    }

    /**
     * Resolve the resource navigation sort order from config.
     */
    public static function resolveResourceNavigationSort(): ?int
    {
        /** @var int|null $sort */
        $sort = config('filament-action-approvals.resource.navigation_sort');

        return $sort;
    }

    /**
     * Resolve the resource navigation icon from config.
     */
    public static function resolveResourceNavigationIcon(): ?string
    {
        /** @var string|null $icon */
        $icon = config('filament-action-approvals.resource.navigation_icon');

        return $icon;
    }

    /**
     * Check if widgets should be displayed on the resource.
     */
    public static function shouldShowResourceWidgets(): bool
    {
        return (bool) config('filament-action-approvals.resource.show_widgets', true);
    }

    /**
     * Resolve the approval resource navigation sort order from config.
     */
    public static function resolveApprovalResourceNavigationSort(): ?int
    {
        /** @var int|null $sort */
        $sort = config('filament-action-approvals.approvals_resource.navigation_sort');

        return $sort;
    }

    /**
     * Resolve the approval resource navigation icon from config.
     */
    public static function resolveApprovalResourceNavigationIcon(): ?string
    {
        /** @var string|null $icon */
        $icon = config('filament-action-approvals.approvals_resource.navigation_icon');

        return $icon;
    }

    public static function shouldGroupApprovalResourceRecordActions(): bool
    {
        return (bool) config('filament-action-approvals.approvals_resource.group_record_actions', true);
    }

    public static function isOperationalActionEnabled(string $action, bool $default = true): bool
    {
        return (bool) config("filament-action-approvals.actions.{$action}", $default);
    }

    public static function resolveDashboardNavigationSort(): ?int
    {
        /** @var int|null $sort */
        $sort = config('filament-action-approvals.dashboard.navigation_sort');

        return $sort;
    }

    public static function resolveDashboardNavigationIcon(): ?string
    {
        /** @var string|null $icon */
        $icon = config('filament-action-approvals.dashboard.navigation_icon');

        return $icon;
    }

    public static function resolveDashboardRoutePath(): string
    {
        /** @var string|null $routePath */
        $routePath = config('filament-action-approvals.dashboard.route_path');

        return filled($routePath) ? $routePath : 'approvals-dashboard';
    }
}
