<?php

namespace App\Actions\Payments\Online;

use App\Domains\Payments\PaymentLink\Services\PaymentLinkService;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Http\Resources\Payments\PublicPaymentLinkResource;

class ShowPaymentLinkAction
{
    public function __construct(private PaymentLinkService $links, private SettingsService $settings) {}

    public function execute(string $token): PublicPaymentLinkResource
    {
        return new PublicPaymentLinkResource($this->links->show($token), $this->settings->all());
    }
}
