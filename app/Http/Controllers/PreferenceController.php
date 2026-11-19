<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gameplay preferences saved on the player's account, so every device uses the same ones.
 */
class PreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'undo_seconds' => ['sometimes', 'integer', Rule::in(config('etoile.preferences.undo_seconds'))],
            'piece_set' => ['sometimes', 'string', Rule::in(config('etoile.preferences.piece_sets'))],
        ]);

        $user = $request->user();
        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], $data)])->save();

        return response()->json(['user' => new UserResource($user)]);
    }
}
