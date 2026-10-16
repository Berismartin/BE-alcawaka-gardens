<?php

namespace App\Http\Controllers\Api\Settings;

use App\Actions\Settings\UpdateSettingsAction;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /** Any staff member may read the hotel profile (it heads receipts and the portal). */
    public function show(SettingsService $settings): JsonResponse
    {
        return $this->respond($settings->all());
    }

    public function update(UpdateSettingsRequest $request, UpdateSettingsAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Settings saved.');
    }
}
