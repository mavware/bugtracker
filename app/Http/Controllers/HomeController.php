<?php

namespace App\Http\Controllers;

use App\Actions\Surveillance\WatchConfig;
use Illuminate\Contracts\View\View;

/**
 * The welcome page leads with the watch panel itself rather than a picture of
 * one, so it needs the same config the guest watch pages hand their scripts.
 */
class HomeController extends Controller
{
    public function __invoke(WatchConfig $config): View
    {
        return view('welcome', ['config' => $config()]);
    }
}
