<?php

namespace App\Http\Controllers\Surveillance;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * The guest capture and report pages. Nothing here touches the database: a
 * night watched from these pages lives in the visitor's browser, and the pages
 * are plain Blade shells that resources/js/surveillance fills in. The only
 * server involvement is handing the scripts their routes.
 */
class WatchController extends Controller
{
    /**
     * The uuid the report route is generated with, for the script to swap out.
     * A local night's id is minted in the browser, so the page cannot know it.
     */
    public const string LOCAL_ID_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

    public function capture(): View
    {
        return view('pages::watch.capture', ['config' => $this->watchConfig()]);
    }

    public function report(string $localId): View
    {
        return view('pages::watch.report', ['localId' => $localId, 'config' => $this->watchConfig()]);
    }

    /**
     * Everything the watch scripts need, and nothing they do not: this is the
     * only source of these, so an unread key is dead weight in the markup.
     *
     * @return array{mode: string, authenticated: bool, csrfToken: string, routes: array{report: string, watch: string, import: string, register: string}}
     */
    public function watchConfig(): array
    {
        return [
            'mode' => 'local',
            'authenticated' => Auth::check(),
            'csrfToken' => Session::token(),
            'routes' => [
                'report' => route('watch.report', ['localId' => self::LOCAL_ID_PLACEHOLDER]),
                'watch' => route('watch.capture'),
                'import' => route('surveillance.import'),
                'register' => route('register'),
            ],
        ];
    }
}
