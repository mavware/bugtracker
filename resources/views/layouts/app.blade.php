<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div>
            @if(!empty($heading))
                <flux:heading size="xl">{{ $heading }}</flux:heading>
            @endif
            @if(!empty($subHeading))
                <flux:text class="mt-2">{{ $subHeading }}</flux:text>
            @endif
        </div>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
