<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canSubmitForApproval(?string $actionKey = null, int|string|null $userId = null): bool
    {
        $resolvedUserId = $this->normalizeUserId($userId ?? auth()->id());

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
