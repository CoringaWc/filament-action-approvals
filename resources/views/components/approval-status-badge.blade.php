@props(['status'])

@php
    $colors = match ($status?->value ?? null) {
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        'approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$colors}"]) }}>
    {{ $status?->getLabel() ?? 'No Approval' }}
</span>
