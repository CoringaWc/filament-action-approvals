<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableAction;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableActions;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableDelete;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableForceDelete;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableRestore;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableUpdate;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\PurchaseOrder;

it('normalizes local action enum values to model-prefixed stored action keys', function (): void {
    $model = new ActionCentricAgencyModel;

    expect($model->approvalActionKeyForOperation(ApprovalOperation::Update))
        ->toBe('agency.profile.update')
        ->and((new ApprovableOperation(action: 'agency.profile.update', fields: ['name']))->normalizedActionKey($model))
        ->toBe('agency.profile.update')
        ->and((new ApprovableOperation(operation: ApprovalOperation::Update, actionKey: 'legacy.edit'))->normalizedActionKey($model))
        ->toBe('legacy.edit')
        ->and((new ActionCentricAgencyUserModel)->approvalActionKeyForOperation(ApprovalOperation::Update))
        ->toBe('agency.user.profile.sensitive_update');
});

it('uses operation-specific attributes without requiring an operation argument', function (): void {
    $model = new Agency;

    expect((new ApprovableUpdate(action: 'profile.update', fields: ['name']))->normalizedActionKey($model))
        ->toBe('agency.profile.update')
        ->and($model->approvalActionKeyForOperation(ApprovalOperation::Update))
        ->toBe('agency.profile.update')
        ->and($model->approvalActionKeyForOperation(ApprovalOperation::Delete))
        ->toBe('agency.delete')
        ->and((new ApprovableDelete(AgencyActionApprovalEnum::Delete))->matchesOperation(ApprovalOperation::Delete))
        ->toBeTrue()
        ->and((new ApprovableRestore('restore'))->matchesOperation(ApprovalOperation::Restore))
        ->toBeTrue()
        ->and((new ApprovableForceDelete('force-delete'))->matchesOperation(ApprovalOperation::ForceDelete))
        ->toBeTrue();
});

it('derives approvable action catalog from action attributes when explicit catalog is absent', function (): void {
    $actions = Agency::approvableActions();

    expect($actions)
        ->toHaveKey('agency.profile.update')
        ->toHaveKey('agency.delete')
        ->toHaveKey('agency.fiscal-data.update')
        ->toHaveKey('agency.contacts.update')
        ->toHaveKey('agency.status.change')
        ->and($actions['agency.profile.update'])->toBe('Atualizar perfil')
        ->and($actions['agency.delete'])->toBe('Excluir agência')
        ->and(ApprovableActionLabel::resolve(Agency::class, 'agency.profile.update'))
        ->toBe('Atualizar perfil')
        ->and(ApprovableActionLabel::resolveEnum(Agency::class, 'agency.profile.update'))
        ->toBe(AgencyActionApprovalEnum::ProfileUpdate)
        ->and(ApprovableActionLabel::enumClassFor(Agency::class))
        ->toBe(AgencyActionApprovalEnum::class);
});

it('keeps explicit ApprovableActions catalog precedence over derived attributes', function (): void {
    expect(ExplicitActionsAgency::approvableActions())
        ->toBe(['explicit.update' => 'Explicit update'])
        ->and(ApprovableActionLabel::enumClassFor(MixedActionsAgency::class))
        ->toBeNull();
});

it('keeps legacy ApprovableOperation actionKey attributes compatible', function (): void {
    $definition = new ApprovableOperation(operation: ApprovalOperation::Delete, actionKey: 'legacy.delete');

    expect($definition->matchesOperation(ApprovalOperation::Delete))
        ->toBeTrue()
        ->and($definition->normalizedActionKey(new Agency))
        ->toBe('legacy.delete');
});

it('fails explicitly when action and actionKey disagree after normalization', function (): void {
    expect(fn (): string => (new ApprovableOperation(
        actionKey: 'agency.other.update',
        action: ActionCentricApprovalAction::ProfileUpdate,
        fields: ['name'],
    ))->normalizedActionKey(new ActionCentricAgencyModel))
        ->toThrow(InvalidArgumentException::class);
});

it('selects action declarations by dirty governed fields and fails closed on ambiguity', function (): void {
    $model = (new ActionCentricAgencyModel)->forceFill(['name' => 'Original']);

    expect($model->approvalOperationDefinitionForData(ApprovalOperation::Update, ['description' => 'Ignored']))
        ->toBeNull()
        ->and($model->approvalOperationDefinitionForData(ApprovalOperation::Update, ['name' => 'Changed'])?->normalizedActionKey($model))
        ->toBe('agency.profile.update');

    $ambiguous = (new ActionCentricAmbiguousAgencyModel)->forceFill(['name' => 'Original']);

    expect(fn () => $ambiguous->approvalOperationDefinitionForData(ApprovalOperation::Update, ['name' => 'Changed']))
        ->toThrow(ValidationException::class);
});

it('accepts both new action and legacy actionKey engine submissions', function (): void {
    $submitter = $this->createUser();
    $approver = $this->createUser();

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();

    $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.edit');
    $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'cancel');

    $actionApproval = app(ApprovalEngine::class)->submit($order, submittedBy: $submitter, action: 'edit');

    expect($actionApproval->submittedActionKey())->toBe('purchase-order.edit');

    $cancelOrder = PurchaseOrder::factory()->for($submitter, 'user')->create();
    $legacyApproval = app(ApprovalEngine::class)->submit($cancelOrder, submittedBy: $submitter, actionKey: 'cancel');

    expect($legacyApproval->submittedActionKey())->toBe('cancel')
        ->and($legacyApproval->submittedAction())->toBe('cancel');
});

it('uses normalized actions for duplicate pending checks', function (): void {
    $submitter = $this->createUser();
    $approver = $this->createUser();
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();

    $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.edit');

    app(ApprovalEngine::class)->submit($order, submittedBy: $submitter, action: 'edit');

    expect(fn () => app(ApprovalEngine::class)->submit($order, submittedBy: $submitter, action: 'purchase-order.edit'))
        ->toThrow(ValidationException::class);
});

it('resolves enum labels from local values when storage keeps a full action key', function (): void {
    expect(ApprovableActionLabel::resolve(ActionCentricAgencyModel::class, 'agency.profile.update'))
        ->toBe('Atualizar perfil')
        ->and(ApprovableActionLabel::resolveEnum(ActionCentricAgencyModel::class, 'agency.profile.update'))
        ->toBe(ActionCentricApprovalAction::ProfileUpdate);
});

enum ActionCentricApprovalAction: string implements HasLabel
{
    case ProfileUpdate = 'profile.update';

    public function getLabel(): string
    {
        return 'Atualizar perfil';
    }
}

enum AgencyActionApprovalEnum: string implements HasLabel
{
    case ProfileUpdate = 'profile.update';
    case FiscalDataUpdate = 'fiscal-data.update';
    case ContactsUpdate = 'contacts.update';
    case StatusChange = 'status.change';
    case Delete = 'delete';

    public function getLabel(): string
    {
        return match ($this) {
            self::ProfileUpdate => 'Atualizar perfil',
            self::FiscalDataUpdate => 'Atualizar dados fiscais',
            self::ContactsUpdate => 'Atualizar contatos',
            self::StatusChange => 'Alterar status',
            self::Delete => 'Excluir agência',
        };
    }
}

#[ApprovableUpdate(action: AgencyActionApprovalEnum::ProfileUpdate, fields: ['name'])]
#[ApprovableDelete(AgencyActionApprovalEnum::Delete)]
#[ApprovableAction(AgencyActionApprovalEnum::FiscalDataUpdate)]
#[ApprovableAction(AgencyActionApprovalEnum::ContactsUpdate)]
#[ApprovableAction(AgencyActionApprovalEnum::StatusChange)]
class Agency extends Model
{
    use HasApprovals;

    protected $table = 'purchase_orders';

    protected $guarded = [];
}

#[ApprovableActions(['explicit.update' => 'Explicit update'])]
#[ApprovableUpdate(action: AgencyActionApprovalEnum::ProfileUpdate, fields: ['name'])]
class ExplicitActionsAgency extends Model
{
    use HasApprovals;

    protected $table = 'purchase_orders';

    protected $guarded = [];
}

#[ApprovableUpdate(action: AgencyActionApprovalEnum::ProfileUpdate, fields: ['name'])]
#[ApprovableDelete('delete')]
class MixedActionsAgency extends Model
{
    use HasApprovals;

    protected $table = 'purchase_orders';

    protected $guarded = [];
}

#[ApprovableActions(ActionCentricApprovalAction::class)]
#[ApprovableOperation(action: ActionCentricApprovalAction::ProfileUpdate, fields: ['name'])]
class ActionCentricAgencyModel extends Model
{
    use HasApprovals;

    protected $table = 'purchase_orders';

    protected $guarded = [];

    public function approvalActionNamespace(): string
    {
        return 'agency';
    }
}

#[ApprovableOperation(action: 'profile.sensitive_update', fields: ['name'])]
class ActionCentricAgencyUserModel extends Model
{
    use HasApprovals;

    protected $table = 'users';

    protected $guarded = [];

    public function approvalActionNamespace(): string
    {
        return 'agency.user';
    }
}

#[ApprovableOperation(action: 'profile.update', fields: ['name'])]
#[ApprovableOperation(action: 'legal.update', fields: ['name'])]
class ActionCentricAmbiguousAgencyModel extends Model
{
    use HasApprovals;

    protected $table = 'purchase_orders';

    protected $guarded = [];

    public function approvalActionNamespace(): string
    {
        return 'agency';
    }
}
