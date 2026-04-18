<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use Closure;
use CoringaWc\FilamentActionApprovals\Support\ApprovalActionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\ModelStates\State;

/**
 * @mixin Model
 */
trait HasStateApprovals
{
    use HasApprovableActions;

    public static function stateApprovalAttribute(): string
    {
        return 'status';
    }

    /**
     * @return array<string, string>
     */
    protected static function resolveApprovableActions(): array
    {
        $baseStateClass = static::resolveStateApprovalBaseStateClass();
        $stateConfig = $baseStateClass::config();

        $actions = [];

        foreach (array_keys($stateConfig->allowedTransitions) as $transitionKey) {
            [$fromStateClass, $toStateClass] = static::parseStateApprovalActionKey($transitionKey);

            $actions[$transitionKey] = __(':from -> :to', [
                'from' => static::resolveStateApprovalLabel($fromStateClass),
                'to' => static::resolveStateApprovalLabel($toStateClass),
            ]);
        }

        return $actions;
    }

    public function transitionWithApproval(
        string $stateAttribute,
        string $toStateClass,
        ?Closure $onExecute = null,
        int|string|null $submittedBy = null,
    ): ApprovalActionResult {
        $currentState = $this->resolveCurrentState($stateAttribute);
        $actionKey = static::stateApprovalActionKey($currentState::class, $toStateClass, $stateAttribute);

        return $this->executeWithApproval(
            actionKey: $actionKey,
            onExecute: $onExecute ?? fn (Model $_record, string $_actionKey): mixed => $currentState->transitionTo($toStateClass),
            submittedBy: $submittedBy,
        );
    }

    public function executeApprovedAction(string $actionKey): void
    {
        [, $toStateClass] = static::parseStateApprovalActionKey($actionKey);
        $stateAttribute = static::stateApprovalAttribute();
        $currentState = $this->resolveCurrentState($stateAttribute);

        if (! $currentState->canTransitionTo($toStateClass)) {
            return;
        }

        $currentState->transitionTo($toStateClass);
    }

    public static function stateApprovalActionKey(
        string $fromStateClass,
        string $toStateClass,
        ?string $stateAttribute = null,
    ): string {
        $baseStateClass = static::resolveStateApprovalBaseStateClass($stateAttribute);

        return sprintf(
            '%s->%s',
            $baseStateClass::resolveStateClass($fromStateClass)::getMorphClass(),
            $baseStateClass::resolveStateClass($toStateClass)::getMorphClass(),
        );
    }

    /**
     * @return array{0: class-string<State>, 1: class-string<State>}
     */
    protected static function parseStateApprovalActionKey(string $actionKey, ?string $stateAttribute = null): array
    {
        [$fromState, $toState] = explode('->', $actionKey, 2) + [null, null];

        if (! is_string($fromState) || ! is_string($toState)) {
            throw new InvalidArgumentException(sprintf('Invalid state approval action key [%s].', $actionKey));
        }

        $baseStateClass = static::resolveStateApprovalBaseStateClass($stateAttribute);

        $fromStateClass = $baseStateClass::resolveStateClass($fromState);
        $toStateClass = $baseStateClass::resolveStateClass($toState);

        if (! is_string($fromStateClass) || ! is_string($toStateClass)) {
            throw new InvalidArgumentException(sprintf('Unable to resolve states for action key [%s].', $actionKey));
        }

        return [$fromStateClass, $toStateClass];
    }

    /**
     * @return class-string<State>
     */
    protected static function resolveStateApprovalBaseStateClass(?string $stateAttribute = null): string
    {
        /** @var Model $model */
        $model = app(static::class);
        $attribute = $stateAttribute ?? static::stateApprovalAttribute();
        $stateClass = $model->getCasts()[$attribute] ?? null;

        if (! is_string($stateClass) || ! is_subclass_of($stateClass, State::class)) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] cast must be a Spatie state class on [%s].',
                $attribute,
                static::class,
            ));
        }

        return $stateClass;
    }

    protected static function resolveStateApprovalLabel(string $stateClass): string
    {
        /** @var Model $model */
        $model = app(static::class);

        /** @var State&object $state */
        $state = new $stateClass($model);

        if (method_exists($state, 'getLabel')) {
            $label = $state->getLabel();

            if (is_string($label) && filled($label)) {
                return $label;
            }
        }

        if (method_exists($state, 'toEnum')) {
            $enum = $state->toEnum();

            if (is_object($enum) && method_exists($enum, 'getLabel')) {
                $label = $enum->getLabel();

                if (is_string($label) && filled($label)) {
                    return $label;
                }
            }
        }

        return Str::headline(class_basename($stateClass));
    }

    protected function resolveCurrentState(string $stateAttribute): State
    {
        $state = $this->{$stateAttribute};

        if (! $state instanceof State) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] attribute must resolve to a Spatie state on [%s].',
                $stateAttribute,
                static::class,
            ));
        }

        return $state;
    }
}
