<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalNotificationEvent;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Support\ApprovalNotificationAction;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ApprovableOperation(
    action: 'edit',
    fields: ['user_id', 'title', 'description', 'amount'],
    relationships: [
        'detail' => ['type' => 'has_one', 'fields' => ['vendor_name', 'reference']],
    ],
    directPayload: [
        'lines' => ['type' => 'has_many', 'operations' => ['replace'], 'fields' => ['id', 'sku', 'quantity']],
    ],
)]
#[ApprovableOperation(operation: ApprovalOperation::Delete, actionKey: 'purchase-order.delete')]
class PurchaseOrder extends Model
{
    use HasApprovals {
        canSubmitForApproval as protected canSubmitForApprovalThroughFlow;
    }
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    public static function approvableActions(): array
    {
        return [
            'submit' => __('workbench::workbench.approval_actions.purchase_orders.submit'),
            'cancel' => __('workbench::workbench.approval_actions.purchase_orders.cancel'),
            'purchase-order.edit' => __('workbench::workbench.approval_actions.purchase_orders.edit'),
            'purchase-order.delete' => __('workbench::workbench.approval_actions.purchase_orders.delete'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(PurchaseOrderDetail::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function canSubmitForApproval(?string $actionKey = null, int|string|null $userId = null): bool
    {
        $resolvedUserId = $this->normalizeUserId($userId ?? CurrentPanelUser::id());

        if ($resolvedUserId === null) {
            return false;
        }

        if ($this->normalizeUserId($this->user_id) === $resolvedUserId) {
            return true;
        }

        return $this->canSubmitForApprovalThroughFlow($actionKey, $resolvedUserId);
    }

    /**
     * Called when the full approval chain completes successfully.
     */
    public function onApprovalApproved(Approval $approval): void
    {
        $this->update(['status' => 'approved']);
    }

    /**
     * Called when the approval is rejected at any step.
     */
    public function onApprovalRejected(Approval $approval): void
    {
        $this->update(['status' => 'rejected']);
    }

    public function getApprovalNotificationRecordLabel(Approval $approval, ApprovalNotificationEvent $event): ?string
    {
        return sprintf('PO-%s', (string) $this->getKey());
    }

    public function getApprovalFieldLabel(string $field): ?string
    {
        return match ($field) {
            'title' => __('workbench::workbench.resources.purchase_orders.fields.title'),
            'description' => __('workbench::workbench.resources.purchase_orders.fields.description'),
            'amount' => __('workbench::workbench.resources.purchase_orders.fields.amount'),
            'user_id' => __('workbench::workbench.resources.purchase_orders.fields.requester'),
            default => null,
        };
    }

    public function getApprovalNotificationAction(Approval $approval, ApprovalNotificationEvent $event): ?ApprovalNotificationAction
    {
        if ($event !== ApprovalNotificationEvent::Rejected) {
            return null;
        }

        return new ApprovalNotificationAction(
            url: sprintf('/admin/purchase-orders/%s', (string) $this->getKey()),
            label: 'View purchase order',
        );
    }

    protected function normalizeUserId(int|string|null $userId): int|string|null
    {
        if ($userId === null) {
            return null;
        }

        if (is_string($userId) && ctype_digit($userId)) {
            return (int) $userId;
        }

        return $userId;
    }
}
