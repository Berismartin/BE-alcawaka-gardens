<?php

namespace App\Actions\Pos;

use App\Domains\Pos\Terminal\Services\PosService;

class RegisterTerminalAction
{
    public function __construct(private PosService $service) {}

    /** @return array{id: string, name: string, key: string} */
    public function execute(string $name): array
    {
        $result = $this->service->registerTerminal($name);

        return ['id' => $result['terminal']->id, 'name' => $result['terminal']->name, 'key' => $result['key']];
    }
}
