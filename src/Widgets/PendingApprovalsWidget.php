<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;

class PendingApprovalsWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('filament-action-approvals::approval.widgets.pending_heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ApprovalStepInstance::query()
                    ->waiting()
                    ->assignedTo(auth()->id())
                    ->with(['approval.approvable', 'step'])
                    ->latest('activated_at')
            )
            ->columns([
                TextColumn::make('step.name')
                    ->label(__('filament-action-approvals::approval.widgets.step')),
                TextColumn::make('approval.approvable_type')
                    ->label(__('filament-action-approvals::approval.widgets.record'))
                    ->formatStateUsing(fn ($record): string => ApprovableModelLabel::resolveWithKey($record->approval->approvable_type, $record->approval->approvable_id)),
                DateDisplay::column(
                    TextColumn::make('activated_at')
                        ->label(__('filament-action-approvals::approval.widgets.since')),
                ),
                DateDisplay::column(
                    TextColumn::make('sla_deadline')
                        ->label(__('filament-action-approvals::approval.widgets.due')),
                )
                    ->placeholder(__('filament-action-approvals::approval.widgets.no_sla'))
                    ->color(fn ($record): string => match (true) {
                        $record->sla_deadline?->isPast() => 'danger',
                        (bool) $record->sla_warning_sent => 'warning',
                        default => 'gray',
                    }),
            ])
            ->recordUrl(function (ApprovalStepInstance $record): ?string {
                /** @var Approval $approval */
                $approval = $record->approval;
                $approvable = $approval->approvable;

                if (! $approvable) {
                    return null;
                }

                $resource = static::getResourceForModel($approvable::class);

                if (! $resource) {
                    return null;
                }

                if ($resource::hasPage('view')) {
                    return $resource::getUrl('view', ['record' => $approvable]);
                }

                if ($resource::hasPage('edit')) {
                    return $resource::getUrl('edit', ['record' => $approvable]);
                }

                return null;
            })
            ->paginated([5, 10, 25]);
    }

    protected static function getResourceForModel(string $modelClass): ?string
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if (! $panel) {
            return null;
        }

        foreach ($panel->getResources() as $resource) {
            if ($resource::getModel() === $modelClass) {
                return $resource;
            }
        }

        return null;
    }
}
