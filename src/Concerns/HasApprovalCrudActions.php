<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableCrudAction;
use CoringaWc\FilamentActionApprovals\Contracts\InterceptsApprovalCrudActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use ReflectionClass;

/**
 * @mixin Model
 *
 * @phpstan-ignore trait.unused
 */
trait HasApprovalCrudActions
{
    /**
     * @var array<class-string, list<ApprovableCrudAction>>
     */
    private static array $approvalCrudActionAttributeCache = [];

    public function approvalCrudActionKey(string $operation): ?string
    {
        return $this->approvalCrudActionDefinition($operation)?->normalizedActionKey();
    }

    /**
     * @return list<string>
     */
    public function approvalCrudFields(string $operation): array
    {
        return $this->approvalCrudActionDefinition($operation)?->fields ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyApprovedCrudAction(string $operation, array $payload): void
    {
        if ($operation === InterceptsApprovalCrudActions::OperationDelete) {
            $this->delete();

            return;
        }

        if ($operation === InterceptsApprovalCrudActions::OperationEdit) {
            $fields = $this->approvalCrudFields($operation);
            $data = $fields === [] ? $payload : Arr::only($payload, $fields);

            $this->fill($data);
            $this->save();
        }
    }

    protected function approvalCrudActionDefinition(string $operation): ?ApprovableCrudAction
    {
        foreach (self::approvalCrudActionAttributes() as $attribute) {
            if ($attribute->enabled && $attribute->operation === $operation) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @return list<ApprovableCrudAction>
     */
    protected static function approvalCrudActionAttributes(): array
    {
        if (array_key_exists(static::class, self::$approvalCrudActionAttributeCache)) {
            return self::$approvalCrudActionAttributeCache[static::class];
        }

        $reflection = new ReflectionClass(static::class);

        /** @var list<ApprovableCrudAction> $attributes */
        $attributes = collect($reflection->getAttributes(ApprovableCrudAction::class))
            ->map(fn (\ReflectionAttribute $attribute): ApprovableCrudAction => $attribute->newInstance())
            ->values()
            ->all();

        return self::$approvalCrudActionAttributeCache[static::class] = $attributes;
    }
}
