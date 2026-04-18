<?php

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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
            ->visible(function (): bool {
                $record = $this->getRecord();

                if (! method_exists($record, 'currentApproval')) {
                    return false;
                }

                $approval = $record->currentApproval();

                if (! $approval) {
                    return false;
                }

                $stepInstance = $approval->currentStepInstance();

                return $stepInstance && $stepInstance->canUserAct(auth()->id());
            })
            ->schema([
                Textarea::make('comment')
                    ->label(__('filament-action-approvals::approval.actions.comment'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $approval = $record->currentApproval();
                $stepInstance = $approval->currentStepInstance();

                app(ApprovalEngine::class)->comment(
                    $approval,
                    auth()->id(),
                    $data['comment'],
                    $stepInstance,
                );

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.comment_success'))
                    ->success()
                    ->send();
            })
            ->after(function (): void {
                $this->getLivewire()->dispatch('filament-action-approvals::approval-updated');
            });
    }
}
