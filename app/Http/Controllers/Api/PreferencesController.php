<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Support\Regions;
use App\Support\UserPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saves the signed-in person's language and area so they follow them across
 * devices. Either field may be sent alone; null clears it.
 */
class PreferencesController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(SetLocale::SUPPORTED)],
            'region' => ['sometimes', 'nullable', 'string', Rule::in(Regions::ALL)],
        ]);

        $user = $request->user();

        if (array_key_exists('locale', $validated)) {
            $user->locale = $validated['locale'];
        }

        if (array_key_exists('region', $validated)) {
            $user->region = $validated['region'];
        }

        $user->save();

        if ($user->locale) {
            app()->setLocale($user->locale);
        }

        return response()->json([
            'message' => __('Preferences saved.'),
            'user' => UserPresenter::present($user),
        ]);
    }
}
