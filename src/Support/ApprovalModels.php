<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalDelegation;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStep;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ApprovalModels
{
    public const Approval = 'approval';

    public const ApprovalFlow = 'approval_flow';

    public const ApprovalStep = 'approval_step';

    public const ApprovalStepInstance = 'approval_step_instance';

    public const ApprovalAction = 'approval_action';

    public const ApprovalDelegation = 'approval_delegation';

    /**
     * @return class-string<Approval>
     */
    public static function approval(): string
    {
        return self::resolve(self::Approval, Approval::class);
    }

    /**
     * @return class-string<ApprovalFlow>
     */
    public static function flow(): string
    {
        return self::resolve(self::ApprovalFlow, ApprovalFlow::class);
    }

    /**
     * @return class-string<ApprovalStep>
     */
    public static function step(): string
    {
        return self::resolve(self::ApprovalStep, ApprovalStep::class);
    }

    /**
     * @return class-string<ApprovalStepInstance>
     */
    public static function stepInstance(): string
    {
        return self::resolve(self::ApprovalStepInstance, ApprovalStepInstance::class);
    }

    /**
     * @return class-string<ApprovalAction>
     */
    public static function action(): string
    {
        return self::resolve(self::ApprovalAction, ApprovalAction::class);
    }

    /**
     * @return class-string<ApprovalDelegation>
     */
    public static function delegation(): string
    {
        return self::resolve(self::ApprovalDelegation, ApprovalDelegation::class);
    }

    /**
     * @return Builder<Approval>
     */
    public static function approvalQuery(): Builder
    {
        $model = static::approval();

        return $model::query();
    }

    /**
     * @return Builder<ApprovalFlow>
     */
    public static function flowQuery(): Builder
    {
        $model = static::flow();

        return $model::query();
    }

    /**
     * @return Builder<ApprovalStepInstance>
     */
    public static function stepInstanceQuery(): Builder
    {
        $model = static::stepInstance();

        return $model::query();
    }

    /**
     * @return array<string, class-string<Model>>
     */
    public static function defaults(): array
    {
        return [
            static::Approval => Approval::class,
            static::ApprovalFlow => ApprovalFlow::class,
            static::ApprovalStep => ApprovalStep::class,
            static::ApprovalStepInstance => ApprovalStepInstance::class,
            static::ApprovalAction => ApprovalAction::class,
            static::ApprovalDelegation => ApprovalDelegation::class,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $default
     * @return class-string<TModel>
     */
    private static function resolve(string $key, string $default): string
    {
        $configured = self::configuredModel($key);

        if ($configured === null) {
            return $default;
        }

        if (! is_string($configured) || ! is_a($configured, $default, true)) {
            throw new InvalidArgumentException("Model configured at [filament-action-approvals.models.{$key}] must extend [{$default}].");
        }

        /** @var class-string<TModel> $configured */
        return $configured;
    }

    private static function configuredModel(string $key): mixed
    {
        $panelOverrides = FilamentActionApprovalsPlugin::current()?->getModelOverrides() ?? [];

        if (array_key_exists($key, $panelOverrides)) {
            return $panelOverrides[$key];
        }

        $configuredModels = config('filament-action-approvals.models', []);

        if (! is_array($configuredModels)) {
            return null;
        }

        return $configuredModels[$key] ?? null;
    }
}
