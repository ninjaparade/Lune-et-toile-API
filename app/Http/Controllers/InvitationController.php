<?php

namespace App\Http\Controllers;

use App\Mail\InviteMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    /**
     * Invite someone by email. An existing player is added as a friend straight away; anyone
     * else gets an invitation that turns into a friendship when they sign up with that address.
     * The response is the same either way so this can't be used to probe who has an account.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $email = $request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email'];

        $me = $request->user();
        if ($email === $me->email) {
            throw ValidationException::withMessages(['email' => "That's your own email."]);
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $me->befriend($existing);
        } else {
            Invitation::firstOrCreate(['inviter_id' => $me->id, 'email' => $email])->touch();
        }

        Mail::to($email)->send(new InviteMail($me, hasAccount: (bool) $existing));

        return response()->json(['message' => "Invite sent to {$email}."], 202);
    }
}
