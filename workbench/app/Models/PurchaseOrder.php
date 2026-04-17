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
    use HasApprovals;
    use HasFactory;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
