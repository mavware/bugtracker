<?php

namespace App\Http\Controllers\Surveillance;

use App\Actions\Surveillance\WatchConfig;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The guest capture and report pages. Nothing here touches the database: a
 * night watched from these pages lives in the visitor's browser, and the pages
 * are plain Blade shells that resources/js/surveillance fills in. The only
 * server involvement is handing the scripts their routes, which WatchConfig
 * builds — the welcome page carries the same panel and asks it for the same
 * config.
 */
class WatchController extends Controller
{
    /**
     * The uuid the report route is generated with, for the script to swap out.
     * A local night's id is minted in the browser, so the page cannot know it.
     */
    public const string LOCAL_ID_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

    public function capture(WatchConfig $config): View
    {
        return view('pages::watch.capture', ['config' => $config()]);
    }

    public function report(string $localId, WatchConfig $config): View
    {
        return view('pages::watch.report', ['localId' => $localId, 'config' => $config()]);
    }
}
