<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Workbench\App\Models\User;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'email' => User::query()->first()?->email,
            'password' => 'password',
            'remember' => true,
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return Select::make('email')
            ->label(__('filament-panels::auth/pages/login.form.email.label'))
            ->options(fn (): array => User::query()
                ->pluck('name', 'email')
                ->map(fn (string $name, string $email): string => "{$name} ({$email})")
                ->all())
            ->searchable()
            ->required()
            ->autofocus()
            ->live();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/login.form.password.label'))
            ->password()
            ->revealable()
            ->default('password')
            ->required();
    }
}
