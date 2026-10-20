<?php

namespace App\Domains\Payments\Gateway;

use App\Domains\Settings\Setting\Services\SettingsService;

/**
 * Pesapal's fee on an online payment and who carries it (a manager setting).
 * - hotel: the guest pays exactly what is due; Pesapal's fee comes out of what the hotel receives.
 * - guest: the fee is added on top, so after Pesapal takes its cut the hotel receives exactly what is due.
 * Pesapal does not report its fee per payment, so this uses the rate from the hotel's Pesapal contract.
 */
final readonly class GatewayFee
{
    public const PAYERS = ['hotel', 'guest'];

    public function __construct(public string $payer, public float $percent) {}

    public static function fromSettings(SettingsService $settings): self
    {
        return new self(
            $settings->get('gateway_fee_payer') === 'guest' ? 'guest' : 'hotel',
            max(0.0, min(10.0, (float) $settings->get('gateway_fee_percent'))),
        );
    }

    /** @return array{charge_ugx: int, fee_ugx: int, payer: string} what the gateway is asked for, and the fee in it */
    public function quote(int $amountUgx): array
    {
        if ($this->payer === 'guest') {
            $charge = (int) ceil(round($amountUgx / (1 - $this->percent / 100), 6)); // charge less fee >= amount

            return ['charge_ugx' => $charge, 'fee_ugx' => $charge - $amountUgx, 'payer' => 'guest'];
        }

        return ['charge_ugx' => $amountUgx, 'fee_ugx' => (int) round($amountUgx * $this->percent / 100), 'payer' => 'hotel'];
    }
}
