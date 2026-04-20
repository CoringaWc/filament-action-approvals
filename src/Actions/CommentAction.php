<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class CommentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approvalComment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-action-approvals::approval.actions.comment'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->visible(function (self $action): bool {
                $userId = auth()->id();

                if ($userId === null) {
                    return false;
                }

                $approval = $action->resolveCurrentApproval();

                if (! $approval) {
                    return false;
                }

                return $approval->canReceiveCommentsFrom($userId);
            })
            ->schema([
                Textarea::make('comment')
                    ->label(__('filament-action-approvals::approval.actions.comment'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (self $action, array $data): void {
                $approval = $action->resolveCurrentApproval();
                $stepInstance = $approval?->currentStepInstance();
                $userId = auth()->id();

                if (! $approval || $userId === null) {
                    return;
                }

                app(ApprovalEngine::class)->comment(
                    $approval,
                    $userId,
                    $data['comment'],
                    $stepInstance,
                );

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.comment_success'))
                    ->success()
                    ->send();
            })
            ->after(function (self $action): void {
                $action->dispatchApprovalUpdated();
            });
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
