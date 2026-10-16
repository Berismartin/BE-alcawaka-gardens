<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\AcceptInviteAction;
use App\Actions\Auth\LoginAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Actions\Auth\SendResetLinkAction;
use App\Actions\Users\UpdatePreferencesAction;
use App\Domains\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SetPasswordRequest;
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

    public function acceptInvite(SetPasswordRequest $request, AcceptInviteAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Password set. Welcome aboard.');
    }

    public function forgotPassword(ForgotPasswordRequest $request, SendResetLinkAction $action): JsonResponse
    {
        $action->execute($request->validated('email'));

        return $this->respond(null, 'If that email belongs to a staff account, a reset link is on its way.');
    }

    public function resetPassword(SetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $action->execute($request->validated());

        return $this->respond(null, 'Password changed. Sign in with your new password.');
    }
}
