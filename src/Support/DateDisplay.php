<?php

namespace CoringaWc\FilamentActionApprovals\Support;

use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;

class DateDisplay
{
    public static function column(TextColumn $column): TextColumn
    {
        $column->dateTime(static::format());

        if (static::usesRelativeTime()) {
            $column
                ->since()
                ->dateTimeTooltip(static::format());
        }

        return $column;
    }

    public static function entry(TextEntry $entry): TextEntry
    {
        $entry->dateTime(static::format());

        if (static::usesRelativeTime()) {
            $entry
                ->since()
                ->dateTimeTooltip(static::format());
        }

        return $entry;
    }

    public static function format(): string
    {
        $format = config('filament-action-approvals.date.display_format', 'd/m/Y H:i');

        return is_string($format) ? $format : 'd/m/Y H:i';
    }

    public static function usesRelativeTime(): bool
    {
        return (bool) config('filament-action-approvals.date.use_since', true);
    }
}
