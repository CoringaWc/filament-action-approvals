@props(['actions'])

<div class="space-y-4">
    @foreach ($actions as $action)
        <div class="flex items-start gap-3">
            <div @class([
                'mt-1 h-2.5 w-2.5 rounded-full shrink-0',
                'bg-blue-500' => $action->type->value === 'submitted',
                'bg-green-500' => $action->type->value === 'approved',
                'bg-red-500' => in_array($action->type->value, ['rejected', 'escalated']),
                'bg-gray-400' => $action->type->value === 'commented',
                'bg-amber-500' => in_array($action->type->value, ['delegated', 'returned']),
            ])></div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $action->type->getLabel() }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $action->created_at->diffForHumans() }}
                    </span>
                </div>

                @if ($action->user)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        by {{ $action->user->name }}
                    </p>
                @endif

                @if ($action->comment)
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        {{ $action->comment }}
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>
