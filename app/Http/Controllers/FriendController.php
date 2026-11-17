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
 * Friends are the people you can invite to games. Adding someone by username or UID
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
        $data = $request->validate([
            'username' => ['required_without:uid', 'nullable', 'string'],
            'uid' => ['required_without:username', 'nullable', 'string'],
        ]);

        $field = isset($data['uid']) ? 'uid' : 'username';
        $value = $field === 'uid' ? strtolower(trim($data['uid'])) : $data['username'];
        $friend = User::where($field, $value)->first();

        if (! $friend || $friend->is($request->user())) {
            throw ValidationException::withMessages([$field => $field === 'uid' ? 'No player with that UID.' : 'No player with that username.']);
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
