<?php

namespace Tests\Support;

use App\Services\Gateway\GatewayCharge;
use App\Services\Gateway\GatewayVerification;
use App\Services\Gateway\PaymentGateway;

/**
 * A test double for the Flutterwave adapter, no real HTTP calls. Bind via
 * `$this->app->instance(PaymentGateway::class, new FakePaymentGateway())` and call
 * `succeedNext($txRef, $amount, $currency)` before exercising GatewayPaymentService::settle().
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, array{amount: string, currency: string}> */
    private array $verifications = [];

    public bool $webhookSignatureValid = true;

    public function initialize(array $data): GatewayCharge
    {
        return new GatewayCharge(ok: true, reference: $data['tx_ref'], link: 'https://flutterwave.test/pay/'.$data['tx_ref']);
    }

    public function succeedNext(string $txRef, string $amount, string $currency): void
    {
        $this->verifications[$txRef] = ['amount' => $amount, 'currency' => $currency];
    }

    public function verify(string $providerTransactionId): GatewayVerification
    {
        // Real Flutterwave gives verify_by_reference a transaction id in
        // data.id, which is then passed to verify(). The fake mints that id as
        // "FLW-{tx_ref}", so accept it here too or the reconcile path, which
        // deliberately settles through verify(), could never be exercised.
        $txRef = str_starts_with($providerTransactionId, 'FLW-')
            ? substr($providerTransactionId, 4)
            : $providerTransactionId;

        if (! isset($this->verifications[$txRef])) {
            return new GatewayVerification(successful: false, reference: '', providerRef: null, amount: '0.00', currency: 'UGX');
        }

        return new GatewayVerification(
            successful: true,
            reference: $txRef,
            providerRef: 'FLW-'.$txRef,
            amount: $this->verifications[$txRef]['amount'],
            currency: $this->verifications[$txRef]['currency'],
            paymentType: 'card',
        );
    }

    public function verifyByReference(string $txRef): GatewayVerification
    {
        return $this->verify($txRef);
    }

    public function verifyWebhookSignature(?string $signatureHeader): bool
    {
        return $this->webhookSignatureValid;
    }
}
