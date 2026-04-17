<?php

namespace CoringaWc\FilamentActionApprovals\Columns;

use Filament\Tables\Columns\TextColumn;

class ApprovalStatusColumn extends TextColumn
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'approval_status')
            ->label(__('filament-action-approvals::approval.column.label'))
            ->getStateUsing(function ($record): ?string {
                if (! method_exists($record, 'approvals')) {
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
