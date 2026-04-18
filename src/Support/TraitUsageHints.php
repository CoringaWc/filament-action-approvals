<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Concerns\HasApprovableActions;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;
use CoringaWc\FilamentActionApprovals\Concerns\HasStateApprovals;
use CoringaWc\FilamentActionApprovals\Concerns\ResolvesPreviousState;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @internal
 */
abstract class UsesHasApprovals extends Model
{
    use HasApprovals;
}

/**
 * @internal
 */
abstract class UsesHasApprovableActions extends Model
{
    use HasApprovableActions;
}

/**
 * @internal
 */
abstract class UsesHasStateApprovals extends Model
{
    use HasStateApprovals;

    public ?string $previous_status = null;

    protected $casts = [
        'status' => DummyState::class,
    ];
}

/**
 * @internal
 */
abstract class UsesResolvesPreviousState extends Model
{
    use ResolvesPreviousState;

    public ?string $previous_status = null;

    protected $casts = [
        'status' => DummyState::class,
    ];
}

/**
 * @internal
 */
abstract class UsesHasApprovalsResource
{
    use HasApprovalsResource;
}

/**
 * @extends State<UsesHasStateApprovals>
 *
 * @internal
 */
class DummyState extends State
{
    public static function config(): StateConfig
    {
        return parent::config();
    }
}
