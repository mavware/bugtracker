@php
    $links = [
        'admin.index' => __('Overview'),
        'admin.users' => __('Users'),
        'admin.sessions' => __('Sessions'),
        'admin.rooms' => __('Rooms'),
        'admin.customers' => __('Customers'),
    ];
@endphp

<div class="flex flex-wrap items-center gap-2">
    @foreach ($links as $route => $label)
        <flux:button
            size="sm"
            :variant="request()->routeIs($route) ? 'filled' : 'subtle'"
            href="{{ route($route) }}"
        >{{ $label }}</flux:button>
    @endforeach
</div>
