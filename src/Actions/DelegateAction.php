<?php

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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

        $this
            ->label(__('filament-action-approvals::approval.actions.delegate'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(function (): bool {
                $record = $this->getRecord();
                $approval = $record->currentApproval();

                if (! $approval) {
                    return false;
                }

                $stepInstance = $approval->currentStepInstance();

                if (! $stepInstance) {
                    return false;
                }

                return in_array(auth()->id(), $stepInstance->assigned_approver_ids);
            })
            ->schema([
                TranslatableSelect::apply(
                    Select::make('to_user_id')
                        ->label(__('filament-action-approvals::approval.actions.delegate_to'))
                        ->searchable()
                        ->options(fn () => $userModel::where('id', '!=', auth()->id())->pluck('name', 'id'))
                        ->required(),
                ),
                Textarea::make('reason')
                    ->label(__('filament-action-approvals::approval.actions.reason'))
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $stepInstance = $record->currentApproval()->currentStepInstance();

                app(ApprovalEngine::class)->delegate(
                    $stepInstance,
                    auth()->id(),
                    (int) $data['to_user_id'],
                    $data['reason'] ?? null,
                );

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.delegated_success'))
                    ->success()
                    ->send();
            })
            ->requiresConfirmation();
    }
}
