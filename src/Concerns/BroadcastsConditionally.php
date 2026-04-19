<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use Illuminate\Broadcasting\Channel;

trait BroadcastsConditionally
{
    abstract protected function broadcastConfigKey(): string;

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('approval-events'),
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
