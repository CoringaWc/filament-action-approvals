<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableActions;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
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
