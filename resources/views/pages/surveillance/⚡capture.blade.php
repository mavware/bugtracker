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

            <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                {{ __('Detection runs entirely in this browser. Only bug paths and tiny snapshots are uploaded — no video is stored.') }}
            </div>
        </div>
    </div>

    @vite('resources/js/surveillance/capture.js')
</section>
