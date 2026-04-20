<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Columns;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class ApprovalStatusColumn extends TextColumn
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'approval_status')
            ->label(__('filament-action-approvals::approval.column.label'))
            ->getStateUsing(function (mixed $record): ?ApprovalStatus {
                if (! $record instanceof Model || ! method_exists($record, 'latestApproval')) {
                    return null;
                }

                return $record->latestApproval()?->status;
            })
            ->badge()
            ->placeholder(__('filament-action-approvals::approval.column.no_approval'));
    }
}
