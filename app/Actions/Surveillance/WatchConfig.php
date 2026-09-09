<?php

namespace App\Actions\Surveillance;

use App\Http\Controllers\Surveillance\WatchController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Everything the watch scripts need, and nothing they do not: this is the only
 * source of these, so an unread key is dead weight in the markup. Shared by the
 * guest watch pages and the welcome page, which carries the same panel above
 * its own copy.
 */
class WatchConfig
{
    /**
     * @return array{mode: string, authenticated: bool, csrfToken: string, routes: array{report: string, watch: string, import: string, register: string}}
     */
    public function __invoke(): array
    {
        return [
            'mode' => 'local',
            'authenticated' => Auth::check(),
            'csrfToken' => Session::token(),
            'routes' => [
                'report' => route('watch.report', ['localId' => WatchController::LOCAL_ID_PLACEHOLDER]),
                'watch' => route('watch.capture'),
                'import' => route('surveillance.import'),
                'register' => route('register'),
            ],
        ];
    }
}
