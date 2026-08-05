<?php

namespace App\Services\Gateway;

/**
 * A payment gateway adapter. Concrete providers (Flutterwave,
 * ...) sit behind this interface so the rest of the app never talks to a vendor
 * SDK directly and providers are swappable/mocked. Money is only ever moved
 * after a server-side verify(), never trusting a client redirect's status.
 */
interface PaymentGateway
{
    /**
     * Create a hosted payment session for an amount.
     *
     * @param  array{tx_ref:string,amount:string,currency:string,redirect_url:string,customer:array<string,string|null>,meta?:array<string,mixed>}  $data
     */
    public function initialize(array $data): GatewayCharge;

    /** Server-side verify a transaction by the provider's own id. */
    public function verify(string $providerTransactionId): GatewayVerification;

    /**
     * Verify by OUR reference rather than the provider's transaction id.
     *
     * Needed for recovery: when the browser never came back from the gateway
     * we have no transaction id, only the tx_ref we generated.
     */
    public function verifyByReference(string $txRef): GatewayVerification;

    /** True if an inbound webhook's signature header authenticates it. */
    public function verifyWebhookSignature(?string $signatureHeader): bool;
}
