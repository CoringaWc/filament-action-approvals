@props(['record'])

@php
    $approval = $record->latestApproval();
@endphp

@if ($approval)
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Approval History</h3>
            <x-filament-action-approvals::components.approval-status-badge :status="$approval->status" />
        </div>

        <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            <p>Flow: {{ $approval->flow->name }}</p>
            <p>Submitted: {{ $approval->submitted_at?->diffForHumans() }}</p>
            @if ($approval->completed_at)
                <p>Completed: {{ $approval->completed_at->diffForHumans() }}</p>
            @endif
        </div>

        <x-filament-action-approvals::components.approval-timeline
            :actions="$approval->actions()->with('user')->orderBy('created_at')->get()"
        />
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No approval history.</p>
@endif
