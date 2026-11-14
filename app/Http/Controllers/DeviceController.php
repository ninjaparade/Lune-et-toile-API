<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The app registers its APNs token after sign-in and removes it on sign-out. A token belongs
 * to whoever registered it last, so a shared phone only gets the signed-in player's pushes.
 */
class DeviceController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/\A[0-9a-f]{64,200}\z/i'],
            'environment' => ['required', Rule::in(DeviceToken::ENVIRONMENTS)],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => strtolower($data['token'])],
            ['user_id' => $request->user()->id, 'environment' => $data['environment']],
        );

        return response()->noContent();
    }

    public function destroy(Request $request, string $token): Response
    {
        $request->user()->deviceTokens()->where('token', strtolower($token))->delete();

        return response()->noContent();
    }
}
