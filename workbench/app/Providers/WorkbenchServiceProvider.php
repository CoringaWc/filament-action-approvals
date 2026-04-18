<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'app.locale' => 'pt_BR',
            'app.fallback_locale' => 'en',
            'app.faker_locale' => 'pt_BR',
            'filament-action-approvals.user_model' => User::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'workbench');

        App::setLocale(config('app.locale'));

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Factory::guessFactoryNamesUsing(
            static fn (string $modelName): string => 'Workbench\\Database\\Factories\\'.class_basename($modelName).'Factory',
        );
    }
}
