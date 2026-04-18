<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;

/**
 * @mixin Model
 */
trait ResolvesPreviousState
{
    public function getPreviousStatusEnum(): ?BackedEnum
    {
        if ($this->previous_status === null) {
            return null;
        }

        /** @var class-string<State> $baseStateClass */
        $baseStateClass = $this->getCasts()['status'];

        /** @var class-string<State>|null $stateClass */
        $stateClass = $baseStateClass::resolveStateClass($this->previous_status);

        if ($stateClass === null) {
            return null;
        }

        /** @phpstan-ignore method.notFound */
        return (new $stateClass($this))->toEnum();
    }
}
