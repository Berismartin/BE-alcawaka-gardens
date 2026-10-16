<?php

namespace App\Actions\Pos;

use App\Domains\Pos\Terminal\Services\PosService;
use App\Http\Resources\Users\UserResource;
use App\Models\Pos\PosTerminal;

class PinLoginAction
{
    public function __construct(private PosService $service) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{user: UserResource, token: string}
     */
    public function execute(PosTerminal $terminal, array $input): array
    {
        $result = $this->service->pinLogin($terminal, (int) $input['user_id'], $input['pin']);

        return ['user' => new UserResource($result['user']), 'token' => $result['token']];
    }
}
