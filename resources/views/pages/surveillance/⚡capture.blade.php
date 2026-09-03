<?php

use App\Models\SurveillanceSession;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Capture')] class extends Component {
    public SurveillanceSession $session;

    public function mount(SurveillanceSession $session): void
    {
        Gate::authorize('update', $session);

        if ($session->status->isFinished()) {
            $this->redirectRoute('surveillance.report', $session);

            return;
        }

        $this->session = $session;
    }

    /**
     * Everything the capture script needs, and nothing it does not: the page is
     * the only source of these, so an unread key is dead weight in the markup.
     * After ending the night the script follows the report_url the server returns
     * rather than a route handed over up front.
     *
     * @return array{csrfToken: string, routes: array{reference: string, tracks: string, heartbeat: string, end: string}}
     */
    public function captureConfig(): array
    {
        return [
            'csrfToken' => csrf_token(),
            'routes' => [
                'reference' => route('surveillance.reference.store', $this->session),
                'tracks' => route('surveillance.tracks.store', $this->session),
                'heartbeat' => route('surveillance.heartbeat', $this->session),
                'end' => route('surveillance.end', $this->session),
            ],
        ];
    }
}; ?>

<section class="w-full" id="capture-app" data-config="{{ json_encode($this->captureConfig()) }}">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $session->name }}</flux:heading>
            <flux:text class="mt-2">{{ __('Keep the device plugged in, the screen on, and this tab visible all night.') }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="subtle" icon="camera" data-capture="check" data-test="check-camera-button">
                <span data-capture="check-label">{{ __('Check camera') }}</span>
            </flux:button>
            <flux:button variant="primary" icon="play" data-capture="start" data-test="start-capture-button">
                {{ __('Start watching') }}
            </flux:button>
            <flux:button variant="danger" icon="stop" data-capture="end" data-test="end-session-button" class="hidden">
                {{ __('End night') }}
            </flux:button>
            <flux:button variant="subtle" icon="x-circle" data-capture="abort" data-test="abort-session-button" class="hidden">
                {{ __('Discard night') }}
            </flux:button>
        </div>
    </div>

    <div data-capture="banner" class="mt-4 hidden rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm text-amber-600 dark:text-amber-400"></div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="relative overflow-hidden rounded-xl bg-black">
                <video data-capture="video" class="w-full" autoplay muted playsinline></video>
                <canvas data-capture="overlay" class="absolute inset-0 h-full w-full"></canvas>
            </div>

            <label class="mt-3 flex items-center gap-2 text-sm text-zinc-500">
                <input type="checkbox" data-capture="debug-toggle" class="rounded" checked />
                {{ __('Show detection boxes') }}
            </label>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Status') }}</flux:heading>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('State') }}</dt>
                        <dd data-capture="state">{{ __('Idle') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Elapsed') }}</dt>
                        <dd data-capture="elapsed">–</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Bugs tracked') }}</dt>
                        <dd data-capture="track-count">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Live tracks') }}</dt>
                        <dd data-capture="live-count">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Upload queue') }}</dt>
                        <dd data-capture="queue-depth">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Scene brightness') }}</dt>
                        <dd data-capture="brightness">–</dd>
                    </div>
                </dl>
            </div>

            {{-- Setup advice, not night-time reading: hidden once watching starts. --}}
            <div data-capture="setup-help" class="space-y-4">
                <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Checking on it later') }}</flux:heading>
                    <p class="mt-2">
                        {{ __('Once this device is recording, leave it be — opening anything else on it ends the night. To see how it is going, open your dashboard on a different phone or computer:') }}
                    </p>
                    {{-- Deliberately not a link: following it here would end the recording. --}}
                    <p class="mt-2 font-mono text-xs break-all text-zinc-700 dark:text-zinc-300">{{ route('dashboard') }}</p>
                    <p class="mt-2">
                        {{ __('It shows sightings so far, when the last one was, and warns you if this device stops checking in.') }}
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('If the screen keeps sleeping') }}</flux:heading>
                    <p class="mt-2">
                        {{ __('This page asks the device to stay awake on its own, so usually there is nothing to do. If it sleeps anyway, the camera stops and the rest of the night is lost — set it manually once:') }}
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4">
                        <li>{{ __('iPhone or iPad: Auto-Lock to Never, and Low Power Mode off — it blocks the screen lock on its own.') }}</li>
                        <li>{{ __('Android: Screen timeout to its longest option.') }}</li>
                        <li>{{ __('Laptop: turn off screen sleep in your system power settings.') }}</li>
                        <li>{{ __('Keep it plugged in either way — a screen held on all night will flatten a battery.') }}</li>
                    </ul>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                {{ __('Detection runs entirely in this browser. Only bug paths and tiny snapshots are uploaded — no video is stored.') }}
            </div>
        </div>
    </div>

    @vite('resources/js/surveillance/capture.js')
</section>
