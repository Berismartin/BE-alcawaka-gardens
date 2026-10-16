<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\LoginAction;
use App\Actions\Users\UpdatePreferencesAction;
use App\Domains\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePreferencesRequest;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Signed in.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respond(new UserResource($request->user()));
    }

    public function updatePreferences(UpdatePreferencesRequest $request, UpdatePreferencesAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->user(), $request->validated()), 'Preferences saved.');
    }

    public function logout(Request $request, AuthService $service): JsonResponse
    {
        $service->logout($request->user());

        return $this->respond(null, 'Signed out.');
    }
}
