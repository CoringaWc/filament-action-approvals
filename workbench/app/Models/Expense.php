<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableActions;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\ExpenseApprovableAction;

#[ApprovableActions(ExpenseApprovableAction::class)]
class Expense extends Model
{
    use HasApprovals;
    use HasFactory;

    protected $guarded = [];

    /**
     * Custom approval rules for the Expense model.
     *
     * Each key maps to a closure that receives the model and returns user IDs.
     *
     * @return array<string, \Closure(self): list<int>>
     */
    public static function approvalCustomRules(): array
    {
        return [
            'expense_manager' => function (Expense $expense): array {
                $managerId = $expense->user?->getAttribute('manager_id');

                return $managerId ? [(int) $managerId] : [];
            },
            'expense_department_heads' => function (Expense $expense): array {
                $department = $expense->user?->getAttribute('department');

                if (! $department) {
                    return [];
                }

                return User::query()
                    ->where('department', $department)
                    ->where('is_department_head', true)
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();
            },
        ];
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function onApprovalApproved(Approval $approval): void
    {
        $this->update(['status' => 'approved']);
    }

    public function onApprovalRejected(Approval $approval): void
    {
        $this->update(['status' => 'rejected']);
    }

    /**
     * Only allow resubmission if previously rejected (not if approved).
     */
    public function allowsApprovalResubmission(): bool
    {
        $latest = $this->latestApproval();

        return ! $latest || $latest->status !== ApprovalStatus::Approved;
    }
}
