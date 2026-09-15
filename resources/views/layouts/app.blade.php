<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <div class="mb-5">
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
