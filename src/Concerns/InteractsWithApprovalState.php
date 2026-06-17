<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Livewire\Attributes\On;
use ReflectionObject;

// Public API for consuming Livewire/Filament pages; package source does not use it directly.
// @phpstan-ignore-next-line trait.unused
trait InteractsWithApprovalState
{
    public function bootedInteractsWithApprovalState(): void
    {
        $this->refreshApprovalState();
    }

    #[On('filament-action-approvals::approval-updated')]
    public function refreshApprovalState(): void
    {
        $record = $this->approvalStateRecord();

        if (! $record instanceof Model || ! method_exists($record, 'pendingApproval')) {
            return;
        }

        $record->unsetRelation('pendingApproval');
        $record->loadMissing([
            'pendingApproval' => fn (MorphOne $relation): MorphOne => $this->scopePendingApprovalForApprovalState($relation),
        ]);
    }

    protected function currentApprovalForApprovalState(): ?Approval
    {
        $record = $this->approvalStateRecord();

        if (! $record instanceof Model || ! method_exists($record, 'currentApproval')) {
            return null;
        }

        /** @var ?Approval $approval */
        $approval = $record->currentApproval();

        return $approval;
    }

    protected function approvalStateRecord(): ?Model
    {
        $record = data_get($this, 'record');

        if ($record instanceof Model) {
            return $record;
        }

        if ((new ReflectionObject($this))->hasMethod('getRecord')) {
            $record = $this->{'getRecord'}();

            if ($record instanceof Model) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  MorphOne<Approval, Model>  $relation
     * @return MorphOne<Approval, Model>
     */
    protected function scopePendingApprovalForApprovalState(MorphOne $relation): MorphOne
    {
        return $relation;
    }
}
