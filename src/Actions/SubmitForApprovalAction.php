<?php

namespace CoringaWc\FilamentActionApprovals\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;

class SubmitForApprovalAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'submitForApproval';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-action-approvals::approval.actions.submit'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(function (): bool {
                $record = $this->getRecord();

                if (! method_exists($record, 'canBeSubmittedForApproval')) {
                    return false;
                }

                return $record->canBeSubmittedForApproval();
            })
            ->schema(function (): array {
                $record = $this->getRecord();
                $flows = ApprovalFlow::forModel($record)->get();

                if ($flows->count() <= 1) {
                    return [];
                }

                return [
                    Select::make('approval_flow_id')
                        ->label(__('filament-action-approvals::approval.actions.approval_flow'))
                        ->options($flows->pluck('name', 'id'))
                        ->required(),
                ];
            })
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $flow = isset($data['approval_flow_id'])
                    ? ApprovalFlow::find($data['approval_flow_id'])
                    : null;

                app(ApprovalEngine::class)->submit($record, $flow);

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.submitted_success'))
                    ->success()
                    ->send();
            })
            ->requiresConfirmation();
    }
}
