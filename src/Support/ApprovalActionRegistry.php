<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use BackedEnum;
use Closure;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ApprovalActionRegistry
{
    public const string OperationAction = 'action';

    public const string OperationAny = '*';

    /**
     * @var array<class-string<Model>, array<string, array<string, Closure(Model, Approval, array<string, mixed>, int|string|null): mixed>>>
     */
    private array $applyHandlers = [];

    /**
     * @var array<class-string<Model>, array<string, array<string, Closure(ApprovalOperationSubmissionContext): mixed>>>
     */
    private array $submitHandlers = [];

    /**
     * @param  class-string<Model>  $model
     * @param  callable(Model, Approval, array<string, mixed>, int|string|null): mixed  $handler
     */
    public function applyUsing(string $model, string $actionKey, string $operation, callable $handler): self
    {
        $this->applyHandlers[$model][$actionKey][$operation] = $handler instanceof Closure
            ? $handler
            : Closure::fromCallable($handler);

        return $this;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  callable(ApprovalOperationSubmissionContext): mixed  $handler
     */
    public function submitUsing(string $model, string|BackedEnum $actionKey, string|ApprovalOperation $operation, callable $handler): self
    {
        $this->submitHandlers[$model][ApprovalActionKey::raw($actionKey) ?? ''][ApprovalOperation::normalize($operation)] = $handler instanceof Closure
            ? $handler
            : Closure::fromCallable($handler);

        return $this;
    }

    /**
     * @return Closure(Model, Approval, array<string, mixed>, int|string|null): mixed|null
     */
    public function resolveApplyHandler(Approval $approval, ?Model $approvable, ?string $actionKey, string $operation): ?Closure
    {
        if ($actionKey === null || $actionKey === '') {
            return null;
        }

        $modelClass = $this->resolveModelClass($approval, $approvable);

        if ($modelClass === null) {
            return null;
        }

        foreach ($this->applyHandlers as $registeredModel => $handlersByActionKey) {
            if (! is_a($modelClass, $registeredModel, true)) {
                continue;
            }

            $handlersByOperation = $handlersByActionKey[$actionKey] ?? null;

            if ($handlersByOperation === null) {
                continue;
            }

            return $handlersByOperation[$operation]
                ?? $handlersByOperation[self::OperationAny]
                ?? null;
        }

        return null;
    }

    /**
     * @return Closure(ApprovalOperationSubmissionContext): mixed|null
     */
    public function resolveSubmitHandler(Approval $approval, ?Model $approvable, ?string $actionKey, string $operation): ?Closure
    {
        if ($actionKey === null || $actionKey === '') {
            return null;
        }

        $modelClass = $this->resolveModelClass($approval, $approvable);

        if ($modelClass === null) {
            return null;
        }

        foreach ($this->submitHandlers as $registeredModel => $handlersByActionKey) {
            if (! is_a($modelClass, $registeredModel, true)) {
                continue;
            }

            $handlersByOperation = $handlersByActionKey[$actionKey] ?? null;

            if ($handlersByOperation === null) {
                continue;
            }

            return $handlersByOperation[$operation]
                ?? $handlersByOperation[self::OperationAny]
                ?? null;
        }

        return null;
    }

    public function flush(): void
    {
        $this->applyHandlers = [];
        $this->submitHandlers = [];
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveModelClass(Approval $approval, ?Model $approvable): ?string
    {
        if ($approvable instanceof Model) {
            return $approvable::class;
        }

        $modelClass = Relation::getMorphedModel($approval->approvable_type) ?? $approval->approvable_type;

        if (! is_a($modelClass, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass;
    }
}
