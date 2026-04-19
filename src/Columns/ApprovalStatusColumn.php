<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Columns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class ApprovalStatusColumn extends TextColumn
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'approval_status')
            ->label(__('filament-action-approvals::approval.column.label'))
            ->getStateUsing(function (mixed $record): ?string {
                if (! $record instanceof Model || ! method_exists($record, 'latestApproval')) {
                    return null;
                }

                return $record->latestApproval()?->status?->value;
            })
            ->badge()
            ->color(fn (?string $state): string => match ($state) {
                'pending' => 'warning',
                'approved' => 'success',
                'rejected' => 'danger',
                'cancelled' => 'gray',
                default => 'gray',
            })
            ->formatStateUsing(fn (?string $state): string => $state
                ? __('filament-action-approvals::approval.status.'.$state)
                : __('filament-action-approvals::approval.column.no_approval')
            );
    }
}
