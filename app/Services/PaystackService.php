<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl = 'https://api.paystack.co',
    ) {}

    public static function fromConfig(): self
    {
        $secret = config('paystack.secret_key');
        if (! $secret) {
            throw new RuntimeException('PAYSTACK_SECRET_KEY is not configured.');
        }

        return new self($secret, config('paystack.base_url'));
    }

    public function createCustomer(string $email, ?string $firstName = null, ?string $lastName = null): array
    {
        return $this->parse($this->client()->post('/customer', array_filter([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ])));
    }

    public function fetchCustomer(string $emailOrCode): array
    {
        return $this->parse($this->client()->get('/customer/'.rawurlencode($emailOrCode)));
    }

    public function initializeTransaction(string $email, int $amountNgn, array $metadata = [], ?string $callbackUrl = null, ?string $planCode = null): array
    {
        return $this->parse($this->client()->post('/transaction/initialize', array_filter([
            'email' => $email,
            'amount' => $amountNgn * 100,
            'currency' => config('paystack.currency', 'NGN'),
            'callback_url' => $callbackUrl ?? url(config('paystack.callback_url')),
            'plan' => $planCode,
            'metadata' => $metadata ?: null,
        ], static fn ($v) => $v !== null)));
    }

    public function verifyTransaction(string $reference): array
    {
        return $this->parse($this->client()->get('/transaction/verify/'.rawurlencode($reference)));
    }

    public function fetchSubscription(string $codeOrId): array
    {
        return $this->parse($this->client()->get('/subscription/'.rawurlencode($codeOrId)));
    }

    public function disableSubscription(string $code, string $emailToken): array
    {
        return $this->parse($this->client()->post('/subscription/disable', [
            'code' => $code,
            'token' => $emailToken,
        ]));
    }

    public function refundTransaction(string $reference, ?int $amountNgn = null): array
    {
        return $this->parse($this->client()->post('/refund', array_filter([
            'transaction' => $reference,
            'amount' => $amountNgn !== null ? $amountNgn * 100 : null,
        ], static fn ($v) => $v !== null)));
    }

    public function verifyWebhookSignature(string $signatureHeader, string $rawBody): bool
    {
        $webhookSecret = config('paystack.webhook_secret') ?: $this->secretKey;
        $computed = hash_hmac('sha512', $rawBody, $webhookSecret);

        return hash_equals($computed, $signatureHeader);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->secretKey)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 250);
    }

    private function parse(Response $response): array
    {
        $body = $response->json() ?? [];
        if (! $response->successful() || data_get($body, 'status') !== true) {
            $message = data_get($body, 'message', 'Paystack request failed.');
            throw new RuntimeException("Paystack API error ({$response->status()}): {$message}");
        }

        return data_get($body, 'data', []);
    }
}
