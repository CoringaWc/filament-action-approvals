<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

trait BroadcastsConditionally
{
    abstract protected function broadcastConfigKey(): string;

    /**
     * @return array<int, Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channel = (string) config('filament-action-approvals.broadcasting.channel', 'approval-events');

        return [
            (bool) config('filament-action-approvals.broadcasting.private', true)
                ? new PrivateChannel($channel)
                : new Channel($channel),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $approval = property_exists($this, 'approval') ? $this->approval : null;
        $approval ??= property_exists($this, 'stepInstance') ? $this->stepInstance?->approval : null;
        $approval ??= property_exists($this, 'action') ? $this->action?->approval : null;

        if (! $approval instanceof Approval) {
            return [];
        }

        return [
            'approval_id' => $approval->getKey(),
            'status' => $approval->status->value,
            'action_key' => $approval->submittedActionKey(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('filament-action-approvals.broadcasting.events.'.$this->broadcastConfigKey(), false);
    }

    public function broadcastQueue(): ?string
    {
        /** @var ?string $queue */
        $queue = config('filament-action-approvals.broadcasting.queue');

        return $queue;
    }
}
