<?php

namespace App\Domains\Payments\Gateway\Pesapal;

use App\Exceptions\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks HTTP to Pesapal API 3.0: gets and caches the bearer token, sends JSON, turns every failure into
 * a GatewayException with a guest-safe message. Knows nothing about bookings or our payments.
 */
class PesapalClient
{
    /** Pesapal tokens live 5 minutes; refresh a little early. */
    private const TOKEN_SECONDS = 240;

    public function __construct(
        private string $baseUrl,
        private ?string $consumerKey,
        private ?string $consumerSecret,
        private int $timeout = 20,
    ) {}

    /**
     * @param  list<string>  $expectedErrors  Pesapal error codes that are a normal answer for this call, not a failure
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], array $expectedErrors = []): array
    {
        return $this->send('GET', $path, $query, $expectedErrors);
    }

    /** @return array<string, mixed> */
    public function post(string $path, array $body): array
    {
        return $this->send('POST', $path, $body);
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $data, array $expectedErrors = [], bool $retried = false): array
    {
        try {
            $request = Http::baseUrl($this->baseUrl)->acceptJson()->asJson()->timeout($this->timeout)->withToken($this->token());
            $response = $method === 'GET' ? $request->get($path, $data) : $request->post($path, $data);
        } catch (ConnectionException $e) {
            Log::warning('Pesapal unreachable', ['path' => $path, 'error' => $e->getMessage()]);
            throw new GatewayException('We couldn’t reach Pesapal. Please try again in a moment.');
        }
        if ($response->status() === 401 && ! $retried) {
            Cache::forget($this->tokenKey()); // token revoked or expired early: get a new one once

            return $this->send($method, $path, $data, $expectedErrors, true);
        }

        return $this->decode($response, $path, $expectedErrors);
    }

    private function token(): string
    {
        if (! $this->consumerKey || ! $this->consumerSecret) {
            throw new GatewayException('Online payments are not set up yet. Please pay on arrival or call us.');
        }
        $cached = Cache::get($this->tokenKey());
        if (is_string($cached)) {
            return $cached;
        }
        try {
            $response = Http::baseUrl($this->baseUrl)->acceptJson()->asJson()->timeout($this->timeout)
                ->post('api/Auth/RequestToken', ['consumer_key' => $this->consumerKey, 'consumer_secret' => $this->consumerSecret]);
        } catch (ConnectionException $e) {
            Log::warning('Pesapal unreachable', ['path' => 'api/Auth/RequestToken', 'error' => $e->getMessage()]);
            throw new GatewayException('We couldn’t reach Pesapal. Please try again in a moment.');
        }
        $token = $this->decode($response, 'api/Auth/RequestToken')['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new GatewayException('Pesapal did not accept our merchant keys. Please pay on arrival or call us.');
        }
        Cache::put($this->tokenKey(), $token, self::TOKEN_SECONDS);

        return $token;
    }

    /**
     * @param  list<string>  $expectedErrors
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $path, array $expectedErrors = []): array
    {
        $json = $response->json();
        $json = is_array($json) ? $json : [];
        $error = $json['error'] ?? null;
        if ($response->successful() && is_array($error) && in_array($error['code'] ?? null, $expectedErrors, true)) {
            return $json;
        }
        $apiStatus = (string) ($json['status'] ?? '200');
        if ($response->failed() || (is_array($error) && ($error['code'] ?? $error['message'] ?? null)) || $apiStatus === '500') {
            Log::warning('Pesapal request failed', ['path' => $path, 'http' => $response->status(), 'error' => $error, 'message' => $json['message'] ?? null]);
            throw new GatewayException(is_array($error) && ! empty($error['message'])
                ? "Pesapal: {$error['message']}"
                : 'Pesapal could not process the request. Please try again.');
        }

        return $json;
    }

    /** One token per merchant account and environment (sandbox and live never share). */
    private function tokenKey(): string
    {
        return 'pesapal:token:'.sha1($this->baseUrl.'|'.$this->consumerKey);
    }
}
