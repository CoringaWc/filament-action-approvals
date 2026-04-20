<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\Approvals\Tables;

use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Schemas\ApprovalInfolist;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApprovalsTable
{
    /**
     * @return array<int, Action|ActionGroup>
     */
    protected static function recordActions(): array
    {
        $groupRecordActions = FilamentActionApprovalsPlugin::shouldGroupApprovalResourceRecordActions();
        $actions = static::buildRecordActionItems($groupRecordActions);

        if (! $groupRecordActions) {
            return $actions;
        }

        return [
            ActionGroup::make($actions)
                ->icon(Heroicon::EllipsisVertical)
                ->tooltip(__('filament-action-approvals::approval.approvals')),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    protected static function columns(): array
    {
        return [
            TextColumn::make('status')
                ->label(__('filament-action-approvals::approval.fields.status'))
                ->badge()
                ->sortable(),
            TextColumn::make('approvable_record')
                ->label(__('filament-action-approvals::approval.approval_table.record'))
                ->state(fn (Approval $record): string => ApprovableModelLabel::resolveWithKey(
                    $record->approvable_type,
                    $record->approvable_id,
                ))
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function (Builder $subQuery) use ($search): void {
                        $subQuery
                            ->where('approvable_type', 'like', "%{$search}%")
                            ->orWhere('approvable_id', 'like', "%{$search}%");
                    });
                }),
            TextColumn::make('flow.name')
                ->label(__('filament-action-approvals::approval.approval_table.flow'))
                ->searchable()
                ->sortable(),
            TextColumn::make('action_key')
                ->label(__('filament-action-approvals::approval.approval_table.action'))
                ->state(fn (Approval $record): string => ApprovableActionLabel::resolve(
                    $record->approvable_type,
                    $record->flow?->action_key,
                )),
            TextColumn::make('current_step')
                ->label(__('filament-action-approvals::approval.approval_table.current_step'))
                ->state(function (Approval $record): string {
                    $stepInstance = $record->currentStepInstance();

                    if ($stepInstance === null || $stepInstance->step === null) {
                        return __('filament-action-approvals::approval.approval_table.no_current_step');
                    }

                    return $stepInstance->step->name;
                }),
            TextColumn::make('submitted_by_display')
                ->label(__('filament-action-approvals::approval.approval_table.submitted_by'))
                ->state(fn (Approval $record): ?string => UserDisplayName::resolve($record->submitter)),
            DateDisplay::column(
                TextColumn::make('submitted_at')
                    ->label(__('filament-action-approvals::approval.fields.submitted_at')),
            )
                ->sortable(),
            DateDisplay::column(
                TextColumn::make('completed_at')
                    ->label(__('filament-action-approvals::approval.fields.completed_at')),
            )
                ->placeholder(__('filament-action-approvals::approval.relation_manager.in_progress'))
                ->sortable(),
        ];
    }

    /**
     * @return array<int, SelectFilter|Filter>
     */
    protected static function filters(): array
    {
        return [
            SelectFilter::make('approvable_type')
                ->label(__('filament-action-approvals::approval.approval_filters.model'))
                ->options(fn (): array => Approval::query()
                    ->distinct()
                    ->pluck('approvable_type')
                    ->filter()
                    ->mapWithKeys(fn (string $type): array => [$type => ApprovableModelLabel::resolve($type)])
                    ->all()),
            SelectFilter::make('approval_flow_id')
                ->label(__('filament-action-approvals::approval.approval_filters.flow'))
                ->relationship('flow', 'name')
                ->searchable()
                ->preload(),
            SelectFilter::make('submitted_by')
                ->label(__('filament-action-approvals::approval.approval_filters.submitted_by'))
                ->options(function (): array {
                    /** @var class-string<Model> $userModel */
                    $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

                    return $userModel::query()
                        ->whereIn('id', Approval::query()->distinct()->pluck('submitted_by')->filter()->all())
                        ->get()
                        ->mapWithKeys(fn ($user): array => [$user->getKey() => UserDisplayName::resolve($user) ?? (string) $user->getKey()])
                        ->all();
                })
                ->searchable(),
            Filter::make('submitted_between')
                ->label(__('filament-action-approvals::approval.approval_filters.submitted_between'))
                ->schema([
                    DatePicker::make('submitted_from')
                        ->label(__('filament-action-approvals::approval.approval_filters.submitted_from')),
                    DatePicker::make('submitted_until')
                        ->label(__('filament-action-approvals::approval.approval_filters.submitted_until')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['submitted_from'] ?? null),
                            fn (Builder $builder): Builder => $builder->whereDate('submitted_at', '>=', $data['submitted_from']),
                        )
                        ->when(
                            filled($data['submitted_until'] ?? null),
                            fn (Builder $builder): Builder => $builder->whereDate('submitted_at', '<=', $data['submitted_until']),
                        );
                }),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function buildRecordActionItems(bool $grouped): array
    {
        return array_values(array_filter([
            ApprovalInfolist::configureViewAction(ViewAction::make()),
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('approve')
                ? static::configureRecordAction(
                    ApproveAction::make(),
                    __('filament-action-approvals::approval.actions.approve'),
                    $grouped,
                )
                : null,
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('reject')
                ? static::configureRecordAction(
                    RejectAction::make(),
                    __('filament-action-approvals::approval.actions.reject'),
                    $grouped,
                )
                : null,
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('comment')
                ? static::configureRecordAction(
                    CommentAction::make(),
                    __('filament-action-approvals::approval.actions.comment'),
                    $grouped,
                )
                : null,
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('delegate')
                ? static::configureRecordAction(
                    DelegateAction::make(),
                    __('filament-action-approvals::approval.actions.delegate'),
                    $grouped,
                )
                : null,
        ]));
    }

    protected static function configureRecordAction(Action $action, string $tooltip, bool $grouped): Action
    {
        if ($grouped) {
            return $action;
        }

        return $action
            ->iconButton()
            ->tooltip($tooltip);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'approvable',
                'flow',
                'submitter',
                'stepInstances.step',
                'actions.user',
            ]))
            ->columns(static::columns())
            ->filters(static::filters(), layout: FiltersLayout::Modal)
            ->recordActions(static::recordActions())
            ->defaultSort('submitted_at', 'desc');
    }
}
