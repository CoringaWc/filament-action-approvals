<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Tables\ApprovalsTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ContextualApprovalsTable extends TableWidget
{
    protected static bool $isLazy = false;

    public ?string $approvableType = null;

    public ?string $approvableId = null;

    protected int|string|array $columnSpan = 'full';

    public function mount(?string $approvableType = null, ?string $approvableId = null): void
    {
        $this->approvableType = filled($approvableType) ? $approvableType : null;
        $this->approvableId = filled($approvableId) ? $approvableId : null;
    }

    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }

    public function table(Table $table): Table
    {
        return ApprovalsTable::configure($table)
            ->query($this->getTableQuery())
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament-action-approvals::approval.fields.status'))
                    ->options(ApprovalStatus::class)
                    ->default(ApprovalStatus::Pending->value),
            ])
            ->paginated([10, 25]);
    }

    /**
     * @return Builder<Approval>
     */
    protected function getTableQuery(): Builder
    {
        return Approval::query()
            ->withOperationalRelations()
            ->when(
                filled($this->approvableType),
                fn (Builder $query): Builder => $query->where('approvable_type', $this->approvableType),
            )
            ->when(
                filled($this->approvableId),
                fn (Builder $query): Builder => $query->where('approvable_id', $this->approvableId),
            );
    }
}
