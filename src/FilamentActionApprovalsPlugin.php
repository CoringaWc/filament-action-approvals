<?php

namespace CoringaWc\FilamentActionApprovals;

use App\Models\User;
use Filament\Contracts\Plugin;
use Filament\Panel;
use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalAnalyticsWidget;
use CoringaWc\FilamentActionApprovals\Widgets\PendingApprovalsWidget;

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

    public function getApproverResolvers(): array
    {
        return $this->approverResolvers ?? config('filament-action-approvals.approver_resolvers', []);
    }

    public function getUserModel(): string
    {
        return $this->userModel ?? config('filament-action-approvals.user_model', User::class);
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? config('filament-action-approvals.navigation_group', 'Approvals');
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
            return filament()->getCurrentOrDefaultPanel()->getPlugin('filament-action-approvals');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the user model, preferring plugin override then config.
     */
    public static function resolveUserModel(): string
    {
        return static::current()?->getUserModel()
            ?? config('filament-action-approvals.user_model', User::class);
    }

    /**
     * Resolve the approver resolvers, preferring plugin override then config.
     */
    public static function resolveApproverResolvers(): array
    {
        return static::current()?->getApproverResolvers()
            ?? config('filament-action-approvals.approver_resolvers', []);
    }

    /**
     * Resolve the navigation group, preferring plugin override then config.
     */
    public static function resolveNavigationGroup(): ?string
    {
        return static::current()?->getNavigationGroup()
            ?? config('filament-action-approvals.navigation_group', 'Approvals');
    }
}
