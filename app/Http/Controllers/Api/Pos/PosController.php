<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Pos\PinLoginAction;
use App\Actions\Pos\RegisterTerminalAction;
use App\Domains\Pos\Terminal\Services\PosService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PinLoginRequest;
use App\Http\Requests\Pos\RegisterTerminalRequest;
use App\Http\Requests\Pos\SetPinRequest;
use App\Models\Pos\PosTerminal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Touch-screen terminal: register devices, list who can sign in, PIN sign-in, set PIN. */
class PosController extends Controller
{
    /** The staff cards on the lock screen: names only, no emails. */
    public function staff(PosService $pos, Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('pos_terminal');

        return $this->respond([
            'terminal' => ['id' => $terminal->id, 'name' => $terminal->name],
            'staff' => $pos->staffList()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role->value])->values(),
        ]);
    }

    public function login(PinLoginRequest $request, PinLoginAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->attributes->get('pos_terminal'), $request->validated()), 'Signed in.');
    }

    public function setPin(SetPinRequest $request, PosService $pos): JsonResponse
    {
        $pos->setPin($request->user(), $request->validated('pin'), $request->validated('current_password'));

        return $this->respond(null, 'PIN saved. Use it on the POS terminal.');
    }

    public function clearPin(int $id, PosService $pos): JsonResponse
    {
        $pos->clearPin($id);

        return $this->respond(null, 'PIN removed. They can set a new one from their account menu.');
    }

    public function terminals(PosService $pos): JsonResponse
    {
        return $this->respond($pos->terminals()->map(fn (PosTerminal $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'last_used_at' => $t->last_used_at?->toISOString(),
            'created_at' => $t->created_at?->toISOString(),
        ]));
    }

    public function register(RegisterTerminalRequest $request, RegisterTerminalAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated('name')), 'Terminal registered.', Response::HTTP_CREATED);
    }

    public function revoke(string $id, PosService $pos): JsonResponse
    {
        $pos->revokeTerminal($id);

        return $this->respond(null, 'Terminal removed. Anyone signed in on it is signed out.');
    }
}
