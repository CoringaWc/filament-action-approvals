<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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
            'filament-action-approvals.actions.approve' => true,
            'filament-action-approvals.actions.reject' => true,
            'filament-action-approvals.actions.comment' => true,
            'filament-action-approvals.actions.delegate' => true,
            'filament-action-approvals.approvals_resource.enabled' => true,
            'filament-action-approvals.approvals_resource.group_record_actions' => true,
        ]);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'workbench');

        App::setLocale(config('app.locale'));

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Factory::guessFactoryNamesUsing(
            /**
             * @param  class-string<Model>  $modelName
             * @return class-string<Factory<Model>>
             */
            static function (string $modelName): string {
                /** @var class-string<Factory<Model>> $factoryClass */
                $factoryClass = 'Workbench\\Database\\Factories\\'.class_basename($modelName).'Factory';

                return $factoryClass;
            },
        );
    }
}
