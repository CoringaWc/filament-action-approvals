<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use BackedEnum;
use CoringaWc\FilamentActionApprovals\Attributes\Approvable;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableActions;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
use CoringaWc\FilamentActionApprovals\Contracts\DefinesApprovalAction;
use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovalDefinition;
use CoringaWc\FilamentActionApprovals\Support\ApprovalModels;
use CoringaWc\FilamentActionApprovals\Support\ApprovalOperationPayload;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ReflectionClass;

trait HasApprovals
{
    /**
     * @return MorphMany<Approval, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(ApprovalModels::approval(), 'approvable');
    }

    /**
     * Eager-loadable "latest pending approval" relation.
     *
     * Exposes the same record as {@see static::currentApproval()} as a single
     * one-of-many relationship so callers can preload it with `with('pendingApproval')`
     * and avoid one approval lookup per record (e.g. one query per table row when
     * rendering approval row actions).
     *
     * @return MorphOne<Approval, $this>
     */
    public function pendingApproval(): MorphOne
    {
        return $this->morphOne(ApprovalModels::approval(), 'approvable')->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn (Builder $query): Builder => $query->where('status', ApprovalStatus::Pending),
        );
    }

    public function latestApproval(): ?Approval
    {
        /** @var ?Approval $approval */
        $approval = $this->approvals()->latest()->first();

        return $approval;
    }

    public function currentApproval(): ?Approval
    {
        // Prefer the eager-loaded one-of-many relation when present so callers
        // that preload `pendingApproval` (e.g. Filament tables) do not trigger
        // an additional query per record.
        if ($this->relationLoaded('pendingApproval')) {
            /** @var ?Approval $loaded */
            $loaded = $this->getRelation('pendingApproval');

            return $loaded;
        }

        /** @var ?Approval $approval */
        $approval = $this->approvals()
            ->where('status', ApprovalStatus::Pending)
            ->latest()
            ->first();

        return $approval;
    }

    public function approvalStatus(): ?ApprovalStatus
    {
        return $this->latestApproval()?->status;
    }

    public function submitForApproval(?ApprovalFlow $flow = null, int|string|null $submittedBy = null, ?string $actionKey = null, string|BackedEnum|null $action = null): Approval
    {
        return app(ApprovalEngine::class)->submit($this, $flow, $submittedBy, $actionKey, action: $action);
    }

    /**
     * @return array<string, string>
     */
    public static function approvableActions(): array
    {
        $approvableAttribute = static::resolveApprovableAttribute();

        if ($approvableAttribute instanceof Approvable) {
            return static::resolveApprovableActionsFromAttributes();
        }

        // #[ApprovableActions] attribute (enum class-string or array)
        $attribute = static::resolveApprovableActionsAttribute();

        if ($attribute !== null) {
            if (is_string($attribute->actions) && enum_exists($attribute->actions)) {
                return static::resolveApprovableActionsFromEnum($attribute->actions);
            }

            /** @var array<string, string> $actions */
            $actions = $attribute->actions;

            return $actions;
        }

        // Method override (from HasStateApprovals or custom)
        $resolvedActions = [];

        if (method_exists(static::class, 'resolveApprovableActions')) {
            /** @var array<string, string> $resolvedActions */
            $resolvedActions = static::resolveApprovableActions();
        }

        $attributeActions = static::resolveApprovableActionsFromAttributes();

        if ($resolvedActions !== [] || $attributeActions !== []) {
            return array_replace($resolvedActions, $attributeActions);
        }

        return [];
    }

    /**
     * Return the enum class-string for approvable actions, if configured
     * via #[ApprovableActions] attribute with an enum class.
     *
     * @return class-string<BackedEnum>|null
     */
    public static function approvableActionsEnumClass(): ?string
    {
        $approvableAttribute = static::resolveApprovableAttribute();

        if ($approvableAttribute instanceof Approvable) {
            return $approvableAttribute->actions;
        }

        $attribute = static::resolveApprovableActionsAttribute();

        if ($attribute !== null && is_string($attribute->actions) && enum_exists($attribute->actions)) {
            return $attribute->actions;
        }

        return static::resolveApprovableActionsEnumClassFromAttributes();
    }

    /**
     * Resolve the #[ApprovableActions] attribute from the model class.
     *
     * @var array<class-string, ?ApprovableActions>
     */
    private static array $approvableActionsAttributeCache = [];

    /**
     * @var array<class-string, ?Approvable>
     */
    private static array $approvableAttributeCache = [];

    protected static function resolveApprovableAttribute(): ?Approvable
    {
        if (array_key_exists(static::class, self::$approvableAttributeCache)) {
            return self::$approvableAttributeCache[static::class];
        }

        $ref = new ReflectionClass(static::class);
        $attrs = $ref->getAttributes(Approvable::class);

        return self::$approvableAttributeCache[static::class] = $attrs !== [] ? $attrs[0]->newInstance() : null;
    }

    protected static function resolveApprovableActionsAttribute(): ?ApprovableActions
    {
        if (array_key_exists(static::class, self::$approvableActionsAttributeCache)) {
            return self::$approvableActionsAttributeCache[static::class];
        }

        $ref = new ReflectionClass(static::class);
        $attrs = $ref->getAttributes(ApprovableActions::class);

        return self::$approvableActionsAttributeCache[static::class] = $attrs !== [] ? $attrs[0]->newInstance() : null;
    }

    /**
     * Resolve approvable actions from a backed enum implementing HasLabel.
     *
     * @param  class-string<BackedEnum>  $enumClass
     * @return array<string, string>
     */
    protected static function resolveApprovableActionsFromEnum(string $enumClass): array
    {
        if (! enum_exists($enumClass)) {
            return [];
        }

        $actions = [];

        /** @var BackedEnum[] $cases */
        $cases = $enumClass::cases();

        foreach ($cases as $case) {
            $key = (string) $case->value;
            $label = method_exists($case, 'getLabel') ? $case->getLabel() : $key;
            $actions[$key] = $label;
        }

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    protected static function resolveApprovableActionsFromAttributes(): array
    {
        $actions = [];

        foreach (static::approvalOperationAttributes() as $definition) {
            if (! $definition->enabled) {
                continue;
            }

            try {
                $key = $definition->normalizedActionKey(static::class);
            } catch (InvalidArgumentException) {
                continue;
            }

            if (blank($key)) {
                continue;
            }

            $actions[$key] = static::resolveApprovableActionLabelFromDefinition($definition, $key);
        }

        return $actions;
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    protected static function resolveApprovableActionsEnumClassFromAttributes(): ?string
    {
        $enumClass = null;
        $hasActionAttribute = false;

        foreach (static::approvalOperationAttributes() as $definition) {
            if (! $definition->enabled) {
                continue;
            }

            try {
                $definition->localActionKey();
            } catch (InvalidArgumentException) {
                continue;
            }

            $hasActionAttribute = true;
            $enumBackedAction = static::enumBackedApprovalAction($definition);

            if (! $enumBackedAction instanceof BackedEnum) {
                return null;
            }

            $actionEnumClass = $enumBackedAction::class;

            if ($enumClass !== null && $enumClass !== $actionEnumClass) {
                return null;
            }

            $enumClass = $actionEnumClass;
        }

        return $hasActionAttribute ? $enumClass : null;
    }

    protected static function resolveApprovableActionLabelFromDefinition(ApprovableOperation $definition, string $key): string
    {
        $enumBackedAction = static::enumBackedApprovalAction($definition);

        if ($enumBackedAction instanceof BackedEnum && method_exists($enumBackedAction, 'getLabel')) {
            $label = $enumBackedAction->getLabel();

            if (is_string($label) && filled($label)) {
                return $label;
            }
        }

        return Str::headline($key);
    }

    protected static function enumBackedApprovalAction(ApprovableOperation $definition): ?BackedEnum
    {
        if ($definition->action instanceof BackedEnum) {
            return $definition->action;
        }

        return $definition->actionKey instanceof BackedEnum ? $definition->actionKey : null;
    }

    public function isPendingApproval(): bool
    {
        return $this->currentApproval() !== null;
    }

    public function isApproved(): bool
    {
        return $this->latestApproval()?->status === ApprovalStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->latestApproval()?->status === ApprovalStatus::Rejected;
    }

    /**
     * Get the rejection reason from the latest rejected approval.
     *
     * Returns the comment from the rejection action (ActionType::Rejected)
     * in the most recent completed approval that was rejected.
     */
    public function latestRejectionReason(): ?string
    {
        $latestApproval = $this->latestApproval();

        if (! $latestApproval || $latestApproval->status !== ApprovalStatus::Rejected) {
            return null;
        }

        /** @var ?ApprovalAction $rejectionAction */
        $rejectionAction = $latestApproval->actions()
            ->where('type', ActionType::Rejected)
            ->latest()
            ->first();

        return $rejectionAction?->comment;
    }

    /**
     * @var array<class-string, list<ApprovableOperation>>
     */
    private static array $approvalOperationAttributeCache = [];

    public function approvalActionKeyForOperation(ApprovalOperation|string $operation): ?string
    {
        return $this->approvalOperationDefinition($operation)?->normalizedActionKey($this);
    }

    public function approvalActionForOperation(ApprovalOperation|string $operation): ?string
    {
        return $this->approvalActionKeyForOperation($operation);
    }

    /**
     * @return list<string>
     */
    public function approvalActionKeysForOperation(ApprovalOperation|string $operation): array
    {
        return array_values(collect($this->approvalOperationDefinitionsForOperation($operation))
            ->map(fn (ApprovableOperation $definition): string => $definition->normalizedActionKey($this))
            ->unique()
            ->values()
            ->all());
    }

    /**
     * @return list<string>
     */
    public function approvalFieldsForOperation(ApprovalOperation|string $operation): array
    {
        $definition = $this->approvalOperationDefinition($operation);

        if (! $definition instanceof ApprovableOperation) {
            return [];
        }

        return $definition->fields;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approvalOperationDefinitionForData(ApprovalOperation|string $operation, array $data): ?ApprovableOperation
    {
        $matches = collect($this->approvalOperationDefinitionsForOperation($operation))
            ->filter(fn (ApprovableOperation $definition): bool => $this->approvalOperationDefinitionHasChanges($definition, $operation, $data))
            ->values();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.ambiguous_approval_operation'),
            ]);
        }

        /** @var ?ApprovableOperation $definition */
        $definition = $matches->first();

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function approvalPayloadForOperation(ApprovalOperation|string $operation, array $data): array
    {
        if ($this->approvalOperationIsPayloadless($operation)) {
            return app(ApprovalOperationPayload::class)->deletePayload();
        }

        $definition = $this->approvalOperationDefinitionForData($operation, $data)
            ?? $this->approvalOperationDefinition($operation);

        if (! $definition instanceof ApprovableOperation) {
            return [];
        }

        return $this->approvalPayloadForOperationDefinition($definition, $operation, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function approvalPayloadForOperationDefinition(ApprovableOperation $definition, ApprovalOperation|string $operation, array $data): array
    {
        if ($this->approvalOperationIsPayloadless($operation)) {
            return app(ApprovalOperationPayload::class)->deletePayload();
        }

        return app(ApprovalOperationPayload::class)->editPayload(
            $this,
            $data,
            $this->approvalFieldsForDefinition($definition, $operation, $data),
            $definition->relationships,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitApproval(ApprovalOperation|string $operation, array $data = []): Approval
    {
        $definition = $data === []
            ? $this->approvalOperationDefinition($operation)
            : $this->approvalOperationDefinitionForData($operation, $data);

        if (! $definition instanceof ApprovableOperation) {
            throw ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.no_changes_to_approve'),
            ]);
        }

        return $this->submitApprovalForOperationDefinition($operation, $definition, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitApprovalForOperationDefinition(ApprovalOperation|string $operation, ApprovableOperation $definition, array $data = []): Approval
    {
        return app(ApprovalEngine::class)->submit(
            approvable: $this,
            submittedBy: CurrentPanelUser::id(),
            action: $definition->action,
            actionKey: $definition->action === null ? $definition->normalizedActionKey($this) : null,
            metadata: $this->approvalMetadataForOperationDefinition($operation, $definition, $data),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyApprovedOperation(ApprovalOperation|string $operation, array $payload): void
    {
        $approvalOperation = ApprovalOperation::fromOperation($operation);

        if ($approvalOperation === ApprovalOperation::Delete) {
            $this->delete();

            return;
        }

        if ($approvalOperation === ApprovalOperation::Restore) {
            if (! is_callable([$this, 'restore'])) {
                throw ValidationException::withMessages([
                    'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
                ]);
            }

            call_user_func([$this, 'restore']);

            return;
        }

        if ($approvalOperation === ApprovalOperation::ForceDelete) {
            $this->forceDelete();

            return;
        }

        if ($approvalOperation === ApprovalOperation::Update) {
            $fields = $this->approvalFieldsForOperation($operation);
            $data = $fields === [] ? $payload : Arr::only($payload, $fields);

            $this->fill($data);
            $this->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function approvalMetadataForOperation(ApprovalOperation|string $operation, array $data): array
    {
        $definition = $this->approvalOperationDefinitionForData($operation, $data)
            ?? $this->approvalOperationDefinition($operation);

        if (! $definition instanceof ApprovableOperation) {
            return [];
        }

        return $this->approvalMetadataForOperationDefinition($operation, $definition, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function approvalMetadataForOperationDefinition(ApprovalOperation|string $operation, ApprovableOperation $definition, array $data): array
    {
        $payloadData = $this->approvalOperationIsPayloadless($operation)
            ? [
                'payload' => app(ApprovalOperationPayload::class)->deletePayload(),
                'diff' => [],
                'fields' => [],
                'relationships' => [],
            ]
            : app(ApprovalOperationPayload::class)->editPayloadData(
                $this,
                $data,
                $this->approvalFieldsForDefinition($definition, $operation, $data),
                $definition->relationships,
            );

        return [
            'action' => $definition->normalizedActionKey($this),
            'payload' => $payloadData['payload'],
            'payload_diff' => $payloadData['diff'],
            'operation' => [
                'name' => ApprovalOperation::normalize($operation),
                'fields' => $payloadData['fields'],
                'relationships' => $payloadData['relationships'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function approvalChangedFieldsForOperation(ApprovalOperation|string $operation, array $data): array
    {
        if ($this->approvalOperationIsPayloadless($operation)) {
            return [];
        }

        $definition = $this->approvalOperationDefinitionForData($operation, $data)
            ?? $this->approvalOperationDefinition($operation);

        if (! $definition instanceof ApprovableOperation) {
            return [];
        }

        return app(ApprovalOperationPayload::class)->editFields(
            $this,
            $data,
            $this->approvalFieldsForDefinition($definition, $operation, $data),
            $definition->relationships,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function approvalPayloadDiffForOperation(ApprovalOperation|string $operation, array $data): array
    {
        if ($this->approvalOperationIsPayloadless($operation)) {
            return [];
        }

        $definition = $this->approvalOperationDefinitionForData($operation, $data)
            ?? $this->approvalOperationDefinition($operation);

        if (! $definition instanceof ApprovableOperation) {
            return [];
        }

        return app(ApprovalOperationPayload::class)->editDiff(
            $this,
            $data,
            $this->approvalFieldsForDefinition($definition, $operation, $data),
            $definition->relationships,
        );
    }

    protected function approvalOperationDefinition(ApprovalOperation|string $operation): ?ApprovableOperation
    {
        return $this->approvalOperationDefinitionsForOperation($operation)[0] ?? null;
    }

    /**
     * @return list<ApprovableOperation>
     */
    protected function approvalOperationDefinitionsForOperation(ApprovalOperation|string $operation): array
    {
        return array_values(collect(self::approvalOperationAttributes())
            ->filter(fn (ApprovableOperation $attribute): bool => $attribute->enabled && $attribute->matchesOperation($operation))
            ->values()
            ->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function approvalOperationDefinitionHasChanges(ApprovableOperation $definition, ApprovalOperation|string $operation, array $data): bool
    {
        if ($this->approvalOperationIsPayloadless($operation)) {
            return true;
        }

        $payloadData = app(ApprovalOperationPayload::class)->editPayloadData(
            $this,
            $data,
            $this->approvalFieldsForDefinition($definition, $operation, $data),
            $definition->relationships,
        );

        return $payloadData['payload'] !== [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function approvalFieldsForDefinition(ApprovableOperation $definition, ApprovalOperation|string $operation, array $data): array
    {
        if ($definition->fields !== [] || ApprovalOperation::fromOperation($operation) !== ApprovalOperation::Update) {
            return $definition->fields;
        }

        return $this->defaultApprovalFieldsForData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function defaultApprovalFieldsForData(array $data): array
    {
        $fillable = $this->getFillable();

        if ($fillable !== []) {
            return array_values(collect($fillable)
                ->filter(fn (string $field): bool => Arr::has($data, $field) && $this->isDefaultApprovableField($field, Arr::get($data, $field)))
                ->values()
                ->all());
        }

        if ($this->getGuarded() === ['*']) {
            return [];
        }

        return array_values(collect($data)
            ->filter(fn (mixed $value, string $field): bool => $this->isDefaultApprovableField($field, $value))
            ->keys()
            ->values()
            ->all());
    }

    private function isDefaultApprovableField(string $field, mixed $value): bool
    {
        if (is_array($value) || blank($field)) {
            return false;
        }

        $baseField = Str::before($field, '.');
        $blockedFields = array_filter([
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
            method_exists($this, 'getDeletedAtColumn') ? $this->getDeletedAtColumn() : null,
        ]);

        return ! in_array($baseField, $blockedFields, true);
    }

    protected function approvalOperationIsPayloadless(ApprovalOperation|string $operation): bool
    {
        return in_array(ApprovalOperation::fromOperation($operation), [
            ApprovalOperation::Delete,
            ApprovalOperation::Restore,
            ApprovalOperation::ForceDelete,
        ], true);
    }

    /**
     * @return list<ApprovableOperation>
     */
    protected static function approvalOperationAttributes(): array
    {
        if (array_key_exists(static::class, self::$approvalOperationAttributeCache)) {
            return self::$approvalOperationAttributeCache[static::class];
        }

        $reflection = new ReflectionClass(static::class);

        /** @var list<ApprovableOperation> $attributes */
        $attributes = collect($reflection->getAttributes(ApprovableOperation::class, \ReflectionAttribute::IS_INSTANCEOF))
            ->map(fn (\ReflectionAttribute $attribute): ApprovableOperation => $attribute->newInstance())
            ->values()
            ->all();

        $approvableAttribute = static::resolveApprovableAttribute();

        if ($approvableAttribute instanceof Approvable) {
            if ($attributes !== [] || static::resolveApprovableActionsAttribute() instanceof ApprovableActions) {
                throw new InvalidArgumentException('A model cannot mix #[Approvable] with legacy approval declaration attributes.');
            }

            $attributes = static::approvalOperationDefinitionsFromEnum($approvableAttribute->actions);
        }

        if ($attributes === []) {
            $attributes = static::defaultApprovalOperationDefinitions();
        }

        static::ensureApprovalOperationDefinitionsAreDeterministic($attributes);

        return self::$approvalOperationAttributeCache[static::class] = $attributes;
    }

    /**
     * @param  class-string<BackedEnum&DefinesApprovalAction>  $enumClass
     * @return list<ApprovableOperation>
     */
    protected static function approvalOperationDefinitionsFromEnum(string $enumClass): array
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException('The approvable actions class must be a backed enum.');
        }

        /** @var list<BackedEnum> $cases */
        $cases = $enumClass::cases();

        return array_values(collect($cases)
            ->map(function (BackedEnum $case): ApprovableOperation {
                if (! $case instanceof DefinesApprovalAction) {
                    throw new InvalidArgumentException('Every approvable action enum case must implement DefinesApprovalAction.');
                }

                return $case->approval()->forAction($case);
            })
            ->filter(fn (ApprovableOperation $definition): bool => $definition->enabled)
            ->values()
            ->all());
    }

    /**
     * @return list<ApprovableOperation>
     */
    protected static function defaultApprovalOperationDefinitions(): array
    {
        return [
            new ApprovalDefinition(operation: ApprovalOperation::Update, action: 'edit'),
            new ApprovalDefinition(operation: ApprovalOperation::Delete, action: 'delete'),
            new ApprovalDefinition(operation: ApprovalOperation::Restore, action: 'restore'),
            new ApprovalDefinition(operation: ApprovalOperation::ForceDelete, action: 'force-delete'),
        ];
    }

    /**
     * @param  list<ApprovableOperation>  $definitions
     */
    protected static function ensureApprovalOperationDefinitionsAreDeterministic(array $definitions): void
    {
        $actions = [];
        $updateFields = [];
        $updateRelationships = [];

        foreach ($definitions as $definition) {
            if (! $definition->enabled) {
                continue;
            }

            $action = $definition->normalizedActionKey(static::class);

            if (isset($actions[$action])) {
                throw new InvalidArgumentException("Duplicate approval action key [{$action}] for [".static::class.'].');
            }

            $actions[$action] = true;

            if (! $definition->matchesOperation(ApprovalOperation::Update)) {
                continue;
            }

            foreach ($definition->fields as $field) {
                if (isset($updateFields[$field])) {
                    throw new InvalidArgumentException("Ambiguous approval update field [{$field}] for [".static::class.'].');
                }

                $updateFields[$field] = true;
            }

            foreach ($definition->relationships as $relationshipName => $relationshipDefinition) {
                $fields = array_is_list($relationshipDefinition)
                    ? $relationshipDefinition
                    : ($relationshipDefinition['fields'] ?? []);

                foreach (is_array($fields) ? $fields : [] as $field) {
                    if (! is_string($field)) {
                        continue;
                    }

                    $path = $relationshipName.'.'.$field;

                    if (isset($updateRelationships[$path])) {
                        throw new InvalidArgumentException("Ambiguous approval relationship field [{$path}] for [".static::class.'].');
                    }

                    $updateRelationships[$path] = true;
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Submission policy
    //
    // Override these methods to control who can submit and whether
    // re-submission is allowed after a completed approval.
    // ──────────────────────────────────────────────────────────────

    /**
     * Whether re-submission is allowed after a completed approval.
     *
     * Override to return false for one-time-only approval models, or implement
     * a custom rule based on the latest approval status.
     * Default: approved and cancelled approvals cannot be resubmitted.
     */
    public function allowsApprovalResubmission(): bool
    {
        $latest = $this->latestApproval();

        return ! $latest || ! in_array($latest->status, [ApprovalStatus::Approved, ApprovalStatus::Cancelled], true);
    }

    /**
     * Whether the given user is authorized to submit this model for approval.
     *
     * Override to add custom authorization logic (e.g. only the creator,
     * only users with a specific role, or only for a specific approvable action).
     * Default: only users resolved as approvers in a matching submission flow can submit.
     */
    public function canSubmitForApproval(?string $actionKey = null, int|string|null $userId = null): bool
    {
        $resolvedUserId = $this->normalizeSubmissionPolicyUserId($userId ?? CurrentPanelUser::id());

        if ($resolvedUserId === null) {
            return false;
        }

        $flowModel = ApprovalModels::flow();

        return $flowModel::getSubmissionFlowsForModel($this, $actionKey)
            ->contains(fn (ApprovalFlow $flow): bool => $this->flowIncludesSubmissionApprover($flow, $resolvedUserId));
    }

    /**
     * Check if the submit action should be available for this record.
     * Combines pending check, resubmission policy, and authorization.
     */
    public function canBeSubmittedForApproval(?string $actionKey = null, int|string|null $userId = null): bool
    {
        $resolvedUserId = $this->normalizeSubmissionPolicyUserId($userId ?? CurrentPanelUser::id());

        // Already pending — can't submit again
        if ($this->isPendingApproval()) {
            return false;
        }

        // Check resubmission policy
        if (! $this->allowsApprovalResubmission()) {
            $latest = $this->latestApproval();

            // If there's a completed approval that the model does not allow to restart, block resubmission.
            if ($latest && in_array($latest->status, [ApprovalStatus::Approved, ApprovalStatus::Rejected, ApprovalStatus::Cancelled], true)) {
                return false;
            }
        }

        // Check user authorization
        return $this->canSubmitForApproval($actionKey, $resolvedUserId);
    }

    protected function flowIncludesSubmissionApprover(ApprovalFlow $flow, int|string $userId): bool
    {
        foreach ($flow->steps as $step) {
            $approverIds = array_map(
                fn (int|string $approverId): int|string => $this->normalizeSubmissionPolicyUserId($approverId) ?? $approverId,
                $step->resolveApproverIds($this),
            );

            if (in_array($userId, $approverIds, true)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeSubmissionPolicyUserId(int|string|null $userId): int|string|null
    {
        if ($userId === null) {
            return null;
        }

        if (is_string($userId) && ctype_digit($userId)) {
            return (int) $userId;
        }

        return $userId;
    }

    // ──────────────────────────────────────────────────────────────
    // Approval lifecycle callbacks
    //
    // Override these methods on your model to react to approval events.
    // They are called by the ApprovalEngine after the corresponding
    // action has been persisted.
    // ──────────────────────────────────────────────────────────────

    /**
     * Called when the model is submitted for approval.
     */
    public function onApprovalSubmitted(Approval $approval): void {}

    /**
     * Called when the full approval is completed (all steps approved).
     */
    public function onApprovalApproved(Approval $approval): void
    {
        if (method_exists($this, 'afterApprovalApproved')) {
            $this->afterApprovalApproved($approval);
        }
    }

    /**
     * Called when the approval is rejected at any step.
     */
    public function onApprovalRejected(Approval $approval): void {}

    /**
     * Called when the approval is cancelled.
     */
    public function onApprovalCancelled(Approval $approval): void {}

    /**
     * Called when a comment is added to the approval.
     */
    public function onApprovalCommented(ApprovalAction $action): void {}

    /**
     * Called when an approver delegates to another user.
     */
    public function onApprovalDelegated(ApprovalStepInstance $stepInstance, int|string $fromUserId, int|string $toUserId): void {}

    /**
     * Called when an individual step is completed (approved).
     */
    public function onApprovalStepCompleted(ApprovalStepInstance $stepInstance): void {}

    /**
     * Called when a step is escalated due to SLA breach.
     */
    public function onApprovalEscalated(ApprovalStepInstance $stepInstance): void {}
}
