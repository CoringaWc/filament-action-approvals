<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use CoringaWc\FilamentActionApprovals\Support\OperationalApprovalAuthorizer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class RejectAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reject';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-action-approvals::approval.actions.reject'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(function (self $action): bool {
                $userId = CurrentPanelUser::id();

                if ($userId === null) {
                    return false;
                }

                $approval = $action->resolveCurrentApproval();

                if (! $approval) {
                    return false;
                }

                return app(OperationalApprovalAuthorizer::class)->canReject($approval, $userId);
            })
            ->authorize(function (self $action): bool {
                $approval = $action->resolveCurrentApproval();

                return $approval instanceof Approval
                    && app(OperationalApprovalAuthorizer::class)->canReject($approval, CurrentPanelUser::id());
            })
            ->schema([
                Textarea::make('comment')
                    ->label(__('filament-action-approvals::approval.actions.rejection_reason'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (self $action, array $data): void {
                $approval = $action->resolveCurrentApproval();
                $userId = CurrentPanelUser::id();

                if (! $approval instanceof Approval) {
                    return;
                }

                app(OperationalApprovalAuthorizer::class)->ensureCanReject($approval, $userId);

                $stepInstance = $approval->currentStepInstance();

                if (! $stepInstance instanceof ApprovalStepInstance) {
                    return;
                }

                app(ApprovalEngine::class)->reject(
                    $stepInstance,
                    $userId,
                    $data['comment'],
                );

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.rejected_success'))
                    ->danger()
                    ->send();
            })
            ->after(function (self $action): void {
                $action->dispatchApprovalUpdated();
            })
            ->requiresConfirmation()
            ->modalHeading(__('filament-action-approvals::approval.actions.reject_heading'));
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
