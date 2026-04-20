<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ApprovalDashboardFilters
{
    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{start: Carbon|null, end: Carbon|null, period: string}
     */
    public static function resolve(?array $filters): array
    {
        $period = (string) ($filters['period'] ?? '30d');
        $start = static::parseDate($filters['startDate'] ?? null, false);
        $end = static::parseDate($filters['endDate'] ?? null, true);

        if ($start !== null || $end !== null) {
            return [
                'start' => $start,
                'end' => $end,
                'period' => 'custom',
            ];
        }

        return match ($period) {
            '5d' => [
                'start' => now()->subDays(5)->startOfDay(),
                'end' => now()->endOfDay(),
                'period' => '5d',
            ],
            '15d' => [
                'start' => now()->subDays(15)->startOfDay(),
                'end' => now()->endOfDay(),
                'period' => '15d',
            ],
            'all' => [
                'start' => null,
                'end' => null,
                'period' => 'all',
            ],
            default => [
                'start' => now()->subDays(30)->startOfDay(),
                'end' => now()->endOfDay(),
                'period' => '30d',
            ],
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>|null  $filters
     * @return Builder<TModel>
     */
    public static function applyToQuery(Builder $query, ?array $filters, string $column): Builder
    {
        $range = static::resolve($filters);

        if ($range['start'] !== null) {
            $query->where($column, '>=', $range['start']);
        }

        if ($range['end'] !== null) {
            $query->where($column, '<=', $range['end']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function label(?array $filters): string
    {
        return match (static::resolve($filters)['period']) {
            '5d' => __('filament-action-approvals::approval.dashboard.filters.last_5_days'),
            '15d' => __('filament-action-approvals::approval.dashboard.filters.last_15_days'),
            'all' => __('filament-action-approvals::approval.dashboard.filters.all_time'),
            'custom' => __('filament-action-approvals::approval.dashboard.filters.custom_range'),
            default => __('filament-action-approvals::approval.dashboard.filters.last_30_days'),
        };
    }

    protected static function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $date = $value instanceof Carbon
            ? $value->copy()
            : Carbon::parse((string) $value);

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
