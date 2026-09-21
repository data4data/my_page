<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The zone the owner's weeks and months are measured in. It is a user setting
 * rather than a config value because it travels with the person, not with the
 * install — see the report's use of it in ReportController.
 */
class TimezoneController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'timezone' => $request->user()->timezone,
            // The same list the `timezone` rule accepts, so the picker cannot
            // offer a value the save would then reject.
            'options' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $request->user()->update($data);

        return response()->json(['timezone' => $data['timezone']]);
    }
}
