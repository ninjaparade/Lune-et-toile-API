<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Friends are the people you can invite to games. Adding someone by username
 * makes you friends both ways straight away (no request/approval step).
 */
class FriendController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PlayerResource::collection($request->user()->friends()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string']]);

        $friend = User::where('username', $data['username'])->first();

        if (! $friend || $friend->is($request->user())) {
            throw ValidationException::withMessages(['username' => 'No player with that username.']);
        }

        $request->user()->befriend($friend);

        return (new PlayerResource($friend))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, User $friend): Response
    {
        $request->user()->unfriend($friend);

        return response()->noContent();
    }
}
