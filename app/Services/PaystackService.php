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

    /**
     * Charge a previously-saved card authorization without further user
     * interaction. Used for Nolbase-managed-server monthly billing
     * (variable amount across months as the tenant adds/removes servers,
     * so it can't ride the fixed-price subscription plan).
     */
    public function chargeAuthorization(string $authorizationCode, string $email, int $amountNgn, ?string $reference = null, array $metadata = []): array
    {
        return $this->parse($this->client()->post('/transaction/charge_authorization', array_filter([
            'authorization_code' => $authorizationCode,
            'email' => $email,
            'amount' => $amountNgn * 100,
            'currency' => config('paystack.currency', 'NGN'),
            'reference' => $reference,
            'metadata' => $metadata ?: null,
        ], static fn ($v) => $v !== null)));
    }

    /**
     * List supported NGN banks (used in the payout-account dropdown).
     */
    public function getBanks(string $country = 'nigeria'): array
    {
        return $this->parse($this->client()->get('/bank', ['country' => $country]));
    }

    /**
     * Verify a bank account belongs to a real person/business.
     * Paystack returns account_name + account_number on success.
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        return $this->parse($this->client()->get('/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]));
    }

    /**
     * Create a Paystack Transfer Recipient — required before we can initiate
     * a Transfer to a bank account. Returns the recipient_code we store on
     * PayoutAccount.
     */
    public function createTransferRecipient(string $accountName, string $accountNumber, string $bankCode, string $type = 'nuban'): array
    {
        return $this->parse($this->client()->post('/transferrecipient', [
            'type' => $type,
            'name' => $accountName,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => config('paystack.currency', 'NGN'),
        ]));
    }

    /**
     * Initiate a single transfer to a previously-created recipient. The
     * actual settlement may require an OTP-confirm step on the Paystack
     * dashboard for certain account balances; in test mode it's automatic.
     */
    public function initiateTransfer(string $recipientCode, int $amountNgn, string $reason, ?string $reference = null): array
    {
        return $this->parse($this->client()->post('/transfer', array_filter([
            'source' => 'balance',
            'recipient' => $recipientCode,
            'amount' => $amountNgn * 100,
            'reason' => $reason,
            'reference' => $reference,
            'currency' => config('paystack.currency', 'NGN'),
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
            ->retry(2, 250, throw: false);
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
