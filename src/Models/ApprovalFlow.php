<?php

namespace CoringaWc\FilamentActionApprovals\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property string $name
 * @property string|null $description
 * @property class-string<Model>|null $approvable_type
 * @property string|null $action_key
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalStep> $steps
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Approval> $approvals
 */
class ApprovalFlow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'approvable_type',
        'action_key',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<ApprovalStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('order');
    }

    /**
     * @return HasMany<Approval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForModel(Builder $query, Model $model): Builder
    {
        $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($model) {
                $q->where('approvable_type', $model->getMorphClass())
                    ->orWhereNull('approvable_type');
            });

        if (config('filament-action-approvals.multi_tenancy.enabled', false)) {
            $column = config('filament-action-approvals.multi_tenancy.column', 'company_id');
            $query->when($model->{$column} ?? null, fn ($q, $id) => $q->where($column, $id));
        }

        return $query;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     *
     * Scope to find an active flow for a specific model + action_key.
     */
    public function scopeForAction(Builder $query, Model $model, string $actionKey): Builder
    {
        return $this->scopeForModel($query, $model)
            ->where('action_key', $actionKey);
    }

    /**
     * @param  Collection<int, self>  $flows
     * @return Collection<int, self>
     */
    public static function filterSubmissionFlows(Collection $flows, ?string $actionKey = null): Collection
    {
        if (filled($actionKey)) {
            $exactMatches = $flows
                ->where('action_key', $actionKey)
                ->values();

            if ($exactMatches->isNotEmpty()) {
                return $exactMatches;
            }
        }

        return $flows
            ->whereNull('action_key')
            ->values();
    }

    /**
     * @return Collection<int, self>
     */
    public static function getSubmissionFlowsForModel(Model $model, ?string $actionKey = null): Collection
    {
        return static::filterSubmissionFlows(static::forModel($model)->get(), $actionKey);
    }

    public static function findSubmissionFlowForModel(Model $model, ?string $actionKey = null): ?self
    {
        return static::getSubmissionFlowsForModel($model, $actionKey)->first();
    }
}
