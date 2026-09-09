{{-- The replay canvas and its controls, shared by the logged-in report and the
     local one so the data-report hooks report.js and localReport.js read cannot
     drift apart. --}}
<div class="overflow-hidden rounded-xl bg-black">
    <canvas data-report="canvas" class="w-full"></canvas>
</div>

<div class="mt-4 flex flex-wrap items-center gap-3">
    <flux:button size="sm" icon="play" data-report="play">{{ __('Replay') }}</flux:button>
    <flux:select size="sm" data-report="speed" class="max-w-32">
        <flux:select.option value="60">60×</flux:select.option>
        <flux:select.option value="1">1×</flux:select.option>
        <flux:select.option value="10">10×</flux:select.option>
        <flux:select.option value="600">600×</flux:select.option>
    </flux:select>
    <input type="range" data-report="scrub" min="0" max="1000" value="0" class="min-w-48 flex-1"/>
    <span class="text-sm tabular-nums text-zinc-500" data-report="clock">–</span>
    <label class="flex items-center gap-2 text-sm text-zinc-500">
        <input type="checkbox" data-report="trails" class="rounded" checked/>
        {{ __('Show all trails') }}
    </label>
</div>
