<?php

namespace CinetPay;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class CinetPay
{
    private ?string $siteId = null;
    private ?string $appKey = null;
    private bool $env = false;
    private string $currency = 'USD';
    private float $exchangeRate = 1.0;
    private ?string $transId = null;
    private ?string $designation = null;
    private ?string $notifyUrl = null;
    private ?string $returnUrl = null;
    private ?string $cancelUrl = null;

    public function __construct(...$args)
    {
        if (isset($args[0])) {
            $this->siteId = (string) $args[0];
        }

        if (isset($args[1])) {
            $this->appKey = (string) $args[1];
        }

        if (isset($args[2])) {
            $this->env = (bool) $args[2];
        }
    }

    public function setSiteId($siteId): self
    {
        $this->siteId = (string) $siteId;

        return $this;
    }

    public function setAppKey($appKey): self
    {
        $this->appKey = (string) $appKey;

        return $this;
    }

    public function setEnv($env): self
    {
        $this->env = (bool) $env;

        return $this;
    }

    public function setCurrency($currency): self
    {
        $this->currency = strtoupper(trim((string) $currency));

        return $this;
    }

    public function setExchangeRate($exchangeRate): self
    {
        $this->exchangeRate = (float) $exchangeRate;

        return $this;
    }

    public function setTransId($transId): self
    {
        $this->transId = (string) $transId;

        return $this;
    }

    public function setDesignation($designation): self
    {
        $this->designation = (string) $designation;

        return $this;
    }

    public function setNotifyUrl($notifyUrl): self
    {
        $this->notifyUrl = (string) $notifyUrl;

        return $this;
    }

    public function setReturnUrl($returnUrl): self
    {
        $this->returnUrl = (string) $returnUrl;

        return $this;
    }

    public function setCancelUrl($cancelUrl): self
    {
        $this->cancelUrl = (string) $cancelUrl;

        return $this;
    }

    public function charge_customer(array $payload)
    {
        $transactionId = (string) ($payload['track'] ?? $this->transId ?? $this->generateTransactionId());
        $amount = $this->normalizeAmount($payload['amount'] ?? 0);
        $description = (string) ($payload['description'] ?? $this->designation ?? ($payload['title'] ?? 'Payment'));
        $notifyUrl = (string) ($payload['ipn_url'] ?? $this->notifyUrl ?? '');
        $returnUrl = (string) ($payload['success_url'] ?? $this->returnUrl ?? '');
        $cancelUrl = (string) ($payload['cancel_url'] ?? $this->cancelUrl ?? $returnUrl);

        $requestPayload = [
            'apikey' => $this->appKey,
            'site_id' => $this->siteId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $this->currency,
            'description' => $description,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'customer_name' => (string) ($payload['name'] ?? ''),
            'customer_email' => (string) ($payload['email'] ?? ''),
            'channels' => 'ALL',
            'metadata' => [
                'order_id' => $payload['order_id'] ?? null,
                'payment_type' => $payload['payment_type'] ?? null,
            ],
            'is_payment_platform' => 1,
        ];

        $response = $this->sendRequest([
            'https://api-checkout.cinetpay.com/v2/payment',
            'https://api.cinetpay.com/v1/payment',
        ], $requestPayload);

        $paymentUrl = $response['data']['payment_url']
            ?? $response['payment_url']
            ?? $response['data']['redirect_url']
            ?? $response['redirect_url']
            ?? null;

        if (is_string($paymentUrl) && $paymentUrl !== '') {
            return $this->makeRedirect($paymentUrl);
        }

        if (!empty($response['data']['paymentToken'])) {
            return (string) $response['data']['paymentToken'];
        }

        if (!empty($response['message'])) {
            throw new RuntimeException((string) $response['message']);
        }

        throw new RuntimeException('CinetPay payment initialization failed.');
    }

    public function ipn_response(): array
    {
        $requestData = $this->requestData();

        $transactionId = (string) ($requestData['transaction_id']
            ?? $requestData['cpm_trans_id']
            ?? $requestData['merchant_transaction_id']
            ?? $this->transId
            ?? '');

        $status = strtoupper((string) ($requestData['status'] ?? $requestData['cpm_result'] ?? ''));
        $code = (string) ($requestData['code'] ?? $requestData['cpm_code'] ?? '');

        if ($status === 'SUCCESS' || $code === '100') {
            return [
                'status' => 'complete',
                'order_id' => $requestData['order_id'] ?? $requestData['merchant_transaction_id'] ?? $requestData['cpm_trans_id'] ?? null,
                'transaction_id' => $transactionId,
            ];
        }

        if ($transactionId !== '' && $this->siteId !== null && $this->appKey !== null) {
            $verification = $this->verifyTransaction($transactionId);

            $verifiedStatus = strtoupper((string) ($verification['status'] ?? $verification['data']['status'] ?? ''));
            $verifiedCode = (string) ($verification['code'] ?? $verification['data']['code'] ?? '');

            if ($verifiedStatus === 'SUCCESS' || $verifiedCode === '100') {
                return [
                    'status' => 'complete',
                    'order_id' => $verification['merchant_transaction_id']
                        ?? $verification['data']['merchant_transaction_id']
                        ?? $requestData['order_id']
                        ?? $requestData['merchant_transaction_id']
                        ?? null,
                    'transaction_id' => $verification['transaction_id']
                        ?? $verification['data']['transaction_id']
                        ?? $transactionId,
                ];
            }
        }

        return [
            'status' => 'failed',
            'order_id' => $requestData['order_id'] ?? $requestData['merchant_transaction_id'] ?? null,
            'transaction_id' => $transactionId,
        ];
    }

    private function verifyTransaction(string $transactionId): array
    {
        return $this->sendRequest([
            sprintf('https://api.cinetpay.com/v1/payment/%s', rawurlencode($transactionId)),
        ], [], 'GET');
    }

    private function sendRequest(array $urls, array $payload = [], string $method = 'POST'): array
    {
        $client = new Client([
            'timeout' => 30,
            'http_errors' => false,
        ]);

        $lastError = null;

        foreach ($urls as $url) {
            try {
                $options = [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                ];

                if ($method === 'GET') {
                    $options['query'] = array_filter([
                        'apikey' => $this->appKey,
                        'site_id' => $this->siteId,
                    ]);
                } else {
                    $options['json'] = $payload;
                }

                $response = $client->request($method, $url, $options);
                $body = (string) $response->getBody();
                $decoded = json_decode($body, true);

                if (is_array($decoded)) {
                    return $decoded;
                }

                $lastError = $body !== '' ? $body : null;
            } catch (GuzzleException $exception) {
                $lastError = $exception->getMessage();
            } catch (\Throwable $throwable) {
                $lastError = $throwable->getMessage();
            }
        }

        if ($lastError !== null) {
            throw new RuntimeException((string) $lastError);
        }

        throw new RuntimeException('CinetPay request failed.');
    }

    private function requestData(): array
    {
        if (function_exists('request')) {
            try {
                $request = request();

                if ($request && method_exists($request, 'all')) {
                    $data = $request->all();

                    if (is_array($data)) {
                        return $data;
                    }
                }
            } catch (\Throwable $throwable) {
                // fall back to superglobals
            }
        }

        return array_merge($_GET ?? [], $_POST ?? []);
    }

    private function normalizeAmount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function generateTransactionId(): string
    {
        try {
            return 'fm-' . bin2hex(random_bytes(12));
        } catch (\Throwable $throwable) {
            return 'fm-' . uniqid('', true);
        }
    }

    private function makeRedirect(string $url)
    {
        if (function_exists('redirect')) {
            return redirect()->away($url);
        }

        return $url;
    }
}
