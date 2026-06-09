<div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-800 dark:bg-warning-950 dark:text-warning-100">
    <div class="font-medium">
        {{ $heading ?? __('filament-action-approvals::approval.modal.approval_request_callout.heading') }}
    </div>

    <div class="mt-1 text-warning-800 dark:text-warning-200">
        {{ $description ?? __('filament-action-approvals::approval.modal.approval_request_callout.description') }}
    </div>
</div>
