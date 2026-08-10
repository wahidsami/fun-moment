<?php

namespace Billplz;

class Signature
{
    public const WEBHOOK_PARAMETERS = [
        'id',
        'collection_id',
        'paid',
        'state',
        'amount',
        'paid_amount',
        'x_signature',
    ];

    private string $secret;
    private array $parameters;

    public function __construct(string $secret, array $parameters = [])
    {
        $this->secret = $secret;
        $this->parameters = $parameters;
    }

    public function create(array $payload): string
    {
        $filtered = [];

        foreach ($this->parameters as $key) {
            if (array_key_exists($key, $payload)) {
                $filtered[$key] = (string) $payload[$key];
            }
        }

        ksort($filtered);

        return hash_hmac('sha256', http_build_query($filtered), $this->secret);
    }
}
