<?php

declare(strict_types=1);

namespace Workbench\App\Providers\Filament;

use CoringaWc\FilamentAcl\FilamentAclPlugin;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Models\User;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->collapsibleNavigationGroups(false)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(
                in: __DIR__.'/../../Filament/Resources',
                for: 'Workbench\\App\\Filament\\Resources',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentAclPlugin::make()
                    ->permissionsResource()
                    ->permissionsResourceNavigationSort(1)
                    ->permissionsResourceNavigationLabel(__('workbench::workbench.resources.roles.navigation_label'))
                    ->permissionsResourceModelLabel(__('workbench::workbench.resources.roles.model_label'))
                    ->permissionsResourcePluralModelLabel(__('workbench::workbench.resources.roles.plural_model_label'))
                    ->permissionsResourceNavigationGroup(__('workbench::workbench.resources.roles.navigation_group')),
                FilamentActionApprovalsPlugin::make()
                    ->flowResource()
                    ->userModel(User::class)
                    ->navigationGroup(__('filament-action-approvals::approval.navigation_group')),
            ]);
    }
}
