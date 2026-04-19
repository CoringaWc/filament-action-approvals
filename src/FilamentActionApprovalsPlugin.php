<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalAnalyticsWidget;
use CoringaWc\FilamentActionApprovals\Widgets\PendingApprovalsWidget;
use Filament\Clusters\Cluster;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

class FilamentActionApprovalsPlugin implements Plugin
{
    protected bool $hasFlowResource = true;

    protected bool $hasWidgets = true;

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
        if ($this->hasFlowResource) {
            $panel->resources([
                ApprovalFlowResource::class,
            ]);
        }

        if ($this->hasWidgets) {
            $panel->widgets([
                PendingApprovalsWidget::class,
                ApprovalAnalyticsWidget::class,
            ]);
        }
    }

    public function boot(Panel $panel): void {}

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
     * Check if the given user is a super admin for approval purposes.
     *
     * Super admins can see and act on all approval actions regardless
     * of being an assigned approver. Controlled via config.
     */
    public static function isSuperAdmin(?int $userId = null): bool
    {
        if (! config('filament-action-approvals.super_admin.enabled', false)) {
            return false;
        }

        $userId ??= auth()->id();

        if (! is_int($userId)) {
            return false;
        }

        /** @var list<int> $superAdminIds */
        $superAdminIds = config('filament-action-approvals.super_admin.user_ids', []);

        if (in_array($userId, $superAdminIds, true)) {
            return true;
        }

        /** @var list<string> $superAdminRoles */
        $superAdminRoles = config('filament-action-approvals.super_admin.roles', []);

        if ($superAdminRoles === []) {
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
            $hasRole = $user->hasAnyRole($superAdminRoles);

            return $hasRole;
        }

        return false;
    }

    /**
     * Whether super admin users/roles should be hidden from resolver selects.
     *
     * Only applies when super_admin is enabled AND hide_from_selects is true.
     */
    public static function shouldHideSuperAdminsFromSelects(): bool
    {
        return config('filament-action-approvals.super_admin.enabled', false)
            && config('filament-action-approvals.super_admin.hide_from_selects', true);
    }

    /**
     * Get the user IDs configured as super admins (for filtering from selects).
     *
     * @return list<int>
     */
    public static function superAdminUserIds(): array
    {
        if (! static::shouldHideSuperAdminsFromSelects()) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = config('filament-action-approvals.super_admin.user_ids', []);

        return $ids;
    }

    /**
     * Get the role names configured as super admins (for filtering from selects).
     *
     * @return list<string>
     */
    public static function superAdminRoles(): array
    {
        if (! static::shouldHideSuperAdminsFromSelects()) {
            return [];
        }

        /** @var list<string> $roles */
        $roles = config('filament-action-approvals.super_admin.roles', []);

        return $roles;
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
}
