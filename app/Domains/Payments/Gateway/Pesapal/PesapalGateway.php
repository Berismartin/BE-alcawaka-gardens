<?php

namespace App\Domains\Payments\Gateway\Pesapal;

use App\Domains\Payments\Gateway\DTOs\CheckoutRequest;
use App\Domains\Payments\Gateway\DTOs\CheckoutSession;
use App\Domains\Payments\Gateway\DTOs\GatewayRefundRequest;
use App\Domains\Payments\Gateway\DTOs\GatewayStatus;
use App\Domains\Payments\Gateway\Enums\GatewayState;
use App\Domains\Payments\Gateway\PaymentGateway;
use App\Exceptions\GatewayException;
use Illuminate\Support\Str;

/**
 * Pesapal API 3.0 (https://developer.pesapal.com): hosted checkout for MTN MoMo, Airtel Money and cards.
 * Maps Pesapal's fields to our gateway contract; HTTP and tokens live in PesapalClient.
 */
class PesapalGateway implements PaymentGateway
{
    public function __construct(
        private PesapalClient $client,
        private ?string $ipnId,
        private string $currency = 'UGX',
        private string $country = 'UG',
    ) {}

    public function name(): string
    {
        return 'pesapal';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        if (! $this->ipnId) {
            throw new GatewayException('Online payments are not set up yet. Please pay on arrival or call us.');
        }
        $json = $this->client->post('api/Transactions/SubmitOrderRequest', [
            'id' => $request->merchantReference,
            'currency' => $this->currency,
            'amount' => $request->amountUgx,
            'description' => Str::limit($request->description, 100, ''),
            'callback_url' => $request->callbackUrl,
            'cancellation_url' => $request->cancellationUrl,
            'notification_id' => $this->ipnId,
            'redirect_mode' => 'TOP_WINDOW',
            'billing_address' => array_filter([
                'email_address' => $request->email,
                'phone_number' => $request->phone,
                'country_code' => $this->country,
                'first_name' => $request->firstName,
                'last_name' => $request->lastName,
            ]),
        ]);
        if (empty($json['order_tracking_id']) || empty($json['redirect_url'])) {
            throw new GatewayException('Pesapal could not start the payment. Please try again.');
        }

        return new CheckoutSession($json['order_tracking_id'], $json['redirect_url']);
    }

    public function fetchStatus(string $trackingId): GatewayStatus
    {
        // An order the guest hasn't paid yet answers with error "payment_details_not_found" ("Pending Payment").
        $json = $this->client->get('api/Transactions/GetTransactionStatus', ['orderTrackingId' => $trackingId], ['payment_details_not_found']);
        $state = match (true) {
            ($json['error']['code'] ?? null) === 'payment_details_not_found' => GatewayState::Pending,
            default => match ((int) ($json['status_code'] ?? -1)) {
                1 => GatewayState::Completed,
                2 => GatewayState::Failed,
                3 => GatewayState::Reversed,
                0 => GatewayState::Invalid, // callers keep waiting: only FAILED fails a payment
                default => GatewayState::Pending,
            },
        };

        return new GatewayStatus(
            state: $state,
            amountUgx: isset($json['amount']) ? (int) round((float) $json['amount']) : null,
            currency: $json['currency'] ?? null,
            confirmationCode: $json['confirmation_code'] ?? null ?: null,
            paymentMethod: $json['payment_method'] ?? null ?: null,
            account: $json['payment_account'] ?? null ?: null,
            merchantReference: $json['merchant_reference'] ?? null,
            description: $json['description'] ?? $json['payment_status_description'] ?? null,
        );
    }

    public function requestRefund(GatewayRefundRequest $request): void
    {
        $this->client->post('api/Transactions/RefundRequest', [
            'confirmation_code' => $request->confirmationCode,
            'amount' => $request->amountUgx,
            'username' => Str::limit($request->requestedBy, 100, ''),
            'remarks' => Str::limit($request->reason, 255, ''),
        ]);
    }

    public function cancel(string $trackingId): void
    {
        $this->client->post('api/Transactions/CancelOrder', ['order_tracking_id' => $trackingId]);
    }

    /** One-off setup: tell Pesapal where to send payment notifications. Returns the ipn_id for PESAPAL_IPN_ID. */
    public function registerIpn(string $url): string
    {
        $json = $this->client->post('api/URLSetup/RegisterIPN', ['url' => $url, 'ipn_notification_type' => 'GET']);

        return $json['ipn_id'] ?? throw new GatewayException('Pesapal did not return an IPN id.');
    }
}
