<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class DelegateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'delegate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        /** @var Model $userPrototype */
        $userPrototype = app($userModel);
        $userKeyName = $userPrototype->getKeyName();

        $this
            ->label(__('filament-action-approvals::approval.actions.delegate'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(function (self $action): bool {
                $userId = auth()->id();

                if ($userId === null) {
                    return false;
                }

                $approval = $action->resolveCurrentApproval();

                if (! $approval) {
                    return false;
                }

                return $approval->canBeDelegatedBy($userId);
            })
            ->schema([
                TranslatableSelect::apply(
                    Select::make('to_user_id')
                        ->label(__('filament-action-approvals::approval.actions.delegate_to'))
                        ->searchable()
                        ->options(function () use ($userKeyName, $userModel): array {
                            $users = $userModel::query();
                            $currentUserId = auth()->id();

                            if (is_int($currentUserId) || is_string($currentUserId)) {
                                $users->where($userKeyName, '!=', $currentUserId);
                            }

                            // Exclude super admin users
                            $excludedIds = FilamentActionApprovalsPlugin::superAdminUserIds();

                            if ($excludedIds !== []) {
                                $users->whereNotIn($userKeyName, $excludedIds);
                            }

                            // Exclude users with super admin roles
                            $excludedRoles = FilamentActionApprovalsPlugin::superAdminRoles();

                            if ($excludedRoles !== [] && method_exists($userModel, 'role')) {
                                $users->whereDoesntHave('roles', function ($q) use ($excludedRoles): void {
                                    $q->whereIn('name', $excludedRoles);
                                });
                            }

                            return $users
                                ->get()
                                ->mapWithKeys(fn ($user) => [$user->getKey() => UserDisplayName::resolve($user)])
                                ->all();
                        })
                        ->required(),
                ),
                Textarea::make('reason')
                    ->label(__('filament-action-approvals::approval.actions.reason'))
                    ->rows(2),
            ])
            ->action(function (self $action, array $data): void {
                $stepInstance = $action->resolveCurrentStepInstance();
                $userId = auth()->id();
                $delegateToUserId = $data['to_user_id'] ?? null;

                // Cast numeric string to int for integer primary key users
                if (is_string($delegateToUserId) && ctype_digit($delegateToUserId)) {
                    $delegateToUserId = (int) $delegateToUserId;
                }

                if (! $stepInstance || $userId === null || $delegateToUserId === null) {
                    return;
                }

                app(ApprovalEngine::class)->delegate(
                    $stepInstance,
                    $userId,
                    $delegateToUserId,
                    $data['reason'] ?? null,
                );

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.delegated_success'))
                    ->success()
                    ->send();
            })
            ->after(function (self $action): void {
                $action->dispatchApprovalUpdated();
            })
            ->requiresConfirmation();
    }

    protected function resolveCurrentApproval(): ?Approval
    {
        $record = $this->getRecord();

        if ($record instanceof Approval) {
            return $record;
        }

        if (! $record instanceof Model || ! method_exists($record, 'currentApproval')) {
            return null;
        }

        /** @var ?Approval $approval */
        $approval = $record->currentApproval();

        return $approval;
    }

    protected function resolveCurrentStepInstance(): ?ApprovalStepInstance
    {
        return $this->resolveCurrentApproval()?->currentStepInstance();
    }

    protected function dispatchApprovalUpdated(): void
    {
        $livewire = $this->getLivewire();

        if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('filament-action-approvals::approval-updated');
        }
    }
}
