<?php

namespace Billplz;

class Bill
{
    public function create(
        string $collectionName,
        string $email,
        string $phone,
        string $name,
        $amount,
        string $callbackUrl,
        string $description,
        array $options = []
    ): BillResponse {
        $payload = [
            'id' => 'bill_' . substr(md5($collectionName . $email . $name . microtime(true)), 0, 16),
            'url' => $options['redirect_url'] ?? $callbackUrl,
            'collection_name' => $collectionName,
            'email' => $email,
            'phone' => $phone,
            'name' => $name,
            'amount' => $amount,
            'callback_url' => $callbackUrl,
            'description' => $description,
        ];

        return new BillResponse($payload);
    }
}
