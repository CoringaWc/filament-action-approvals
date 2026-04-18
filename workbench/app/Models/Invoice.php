<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Concerns\HasStateApprovals;
use CoringaWc\FilamentActionApprovals\Concerns\ResolvesPreviousState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;
use Workbench\App\States\Invoice\AwaitingPayment;
use Workbench\App\States\Invoice\Cancelled;
use Workbench\App\States\Invoice\InvoiceState;
use Workbench\App\States\Invoice\Issuing;
use Workbench\App\States\Invoice\Paid;
use Workbench\App\States\Invoice\Sent;

class Invoice extends Model
{
    use HasApprovals;
    use HasFactory;
    use HasStateApprovals;
    use HasStates;
    use ResolvesPreviousState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => InvoiceState::class,
            'sent_at' => 'date',
            'paid_at' => 'date',
            'cancelled_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isIssuing(): bool
    {
        return $this->status instanceof Issuing;
    }

    public function isSent(): bool
    {
        return $this->status instanceof Sent;
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status instanceof AwaitingPayment;
    }

    public function isPaid(): bool
    {
        return $this->status instanceof Paid;
    }

    public function isCancelled(): bool
    {
        return $this->status instanceof Cancelled;
    }
}
