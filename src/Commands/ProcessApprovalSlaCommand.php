<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Commands;

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Events\ApprovalEscalated;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalEscalatedNotification;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalRequestedNotification;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalSlaWarningNotification;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Console\Command;

class ProcessApprovalSlaCommand extends Command
{
    protected $signature = 'approval:process-sla';

    protected $description = 'Check for SLA warnings and breaches on pending approval steps';

    public function handle(ApprovalEngine $engine): int
    {
        $warningThreshold = config('filament-action-approvals.sla_warning_threshold', 0.75);

        $this->processWarnings($warningThreshold);
        $this->processBreaches($engine);

        return self::SUCCESS;
    }

    protected function processWarnings(float $warningThreshold): void
    {
        $candidates = ApprovalStepInstance::query()
            ->where('status', StepInstanceStatus::Waiting)
            ->whereNotNull('sla_deadline')
            ->where('sla_warning_sent', false)
            ->where('sla_breached', false)
            ->get();

        foreach ($candidates as $instance) {
            if (! $instance->activated_at || ! $instance->sla_deadline) {
                continue;
            }

            $totalDuration = $instance->activated_at->diffInSeconds($instance->sla_deadline);
            $elapsed = $instance->activated_at->diffInSeconds(now());

            if ($elapsed >= $totalDuration * $warningThreshold) {
                $instance->update(['sla_warning_sent' => true]);

                foreach ($instance->assigned_approver_ids as $userId) {
                    ApprovalSlaWarningNotification::send($instance, $userId);
                }
            }
        }
    }

    protected function processBreaches(ApprovalEngine $engine): void
    {
        $breached = ApprovalStepInstance::query()
            ->where('status', StepInstanceStatus::Waiting)
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<=', now())
            ->where('sla_breached', false)
            ->with(['step', 'approval'])
            ->get();

        foreach ($breached as $instance) {
            $instance->update(['sla_breached' => true]);
            $this->handleEscalation($instance, $engine);
        }
    }

    protected function handleEscalation(ApprovalStepInstance $instance, ApprovalEngine $engine): void
    {
        $step = $instance->step;

        if (! $step) {
            return;
        }

        $action = $step->escalation_action;

        if ($action === null) {
            return;
        }

        $instance->approval->actions()->create([
            'approval_step_instance_id' => $instance->getKey(),
            'type' => ActionType::Escalated,
            'metadata' => ['escalation_action' => $action->value],
        ]);

        match ($action) {
            EscalationAction::Notify => $this->sendEscalationNotification($instance),
            EscalationAction::AutoApprove => $engine->approve($instance, 0, __('filament-action-approvals::approval.sla.auto_approved')),
            EscalationAction::Reject => $engine->reject($instance, 0, __('filament-action-approvals::approval.sla.auto_rejected')),
            EscalationAction::Reassign => $this->reassign($instance),
        };

        event(new ApprovalEscalated($instance));

        $approvable = $instance->approval->approvable;

        if (is_object($approvable) && method_exists($approvable, 'onApprovalEscalated')) {
            $approvable->onApprovalEscalated($instance);
        }
    }

    protected function reassign(ApprovalStepInstance $instance): void
    {
        $step = $instance->step;
        $approvable = $instance->approval->approvable;

        if (! $step || ! $approvable) {
            return;
        }

        $config = $step->escalation_config ?? [];
        $resolverClass = $config['reassign_to_resolver'] ?? null;
        $resolverConfig = $config['reassign_config'] ?? [];

        if (! $resolverClass || ! class_exists($resolverClass)) {
            return;
        }

        $resolver = app($resolverClass);
        $newApproverIds = $resolver->resolve($resolverConfig, $approvable);

        $instance->update([
            'assigned_approver_ids' => $newApproverIds,
            'sla_breached' => false,
            'sla_warning_sent' => false,
            'sla_deadline' => $step->sla_hours
                ? now()->addHours($step->sla_hours)
                : null,
        ]);

        foreach ($newApproverIds as $userId) {
            ApprovalRequestedNotification::send($instance, $userId);
        }
    }

    protected function sendEscalationNotification(ApprovalStepInstance $instance): void
    {
        foreach ($instance->assigned_approver_ids as $userId) {
            ApprovalEscalatedNotification::send($instance, $userId);
        }
    }
}
