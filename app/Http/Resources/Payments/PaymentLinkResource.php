<?php

namespace App\Http\Resources\Payments;

use App\Domains\Payments\PaymentLink\Services\PaymentLinkService;
use App\Models\Payments\PaymentLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** For staff, right after sending: the link to copy or WhatsApp, and whether the email went out. @mixin PaymentLink */
class PaymentLinkResource extends JsonResource
{
    public function __construct(PaymentLink $resource, private bool $emailed)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'email' => $this->email,
            'emailed' => $this->emailed,
            'amount_ugx' => PaymentLinkService::payNow($this->resource),
            'expires_at' => $this->expires_at->toISOString(),
        ];
    }
}
