<?php

namespace App\Http\Controllers\Surveillance;

use App\Actions\Surveillance\ImportLocalNight;
use App\Concerns\SurveillanceValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    use SurveillanceValidationRules;

    /**
     * Save a night recorded in the browser without an account. One request per
     * chunk of tracks; chunks with the same local_id merge into one session.
     * No policy check: the session is found or created under the requesting
     * user, so there is no other user's row to reach.
     */
    public function store(Request $request, ImportLocalNight $import): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $this->validatedInput($request, $this->importNightRules());

        $result = $import->handle($user, $validated);

        return response()->json([
            'session_id' => $result['session']->id,
            'created' => $result['created'],
            'accepted' => $result['accepted'],
            'duplicate' => $result['duplicate'],
            'report_url' => route('surveillance.report', $result['session']),
        ]);
    }
}
