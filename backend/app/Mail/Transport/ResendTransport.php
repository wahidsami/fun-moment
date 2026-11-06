<?php

namespace App\Mail\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class ResendTransport extends AbstractTransport
{
    protected string $apiKey;
    protected Client $client;

    public function __construct(string $apiKey, ?Client $client = null)
    {
        parent::__construct();
        $this->apiKey = $apiKey;
        $this->client = $client ?? new Client();
    }

    protected function doSend(SentMessage $message): void
    {
        if (empty(trim($this->apiKey))) {
            $msg = 'Resend API key is not configured. Please set RESEND_API_KEY in environment.';
            Log::error($msg);
            throw new TransportException($msg);
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom();
        $fromAddress = !empty($from) ? $from[0]->getAddress() : (config('mail.from.address') ?: 'onboarding@resend.dev');
        $fromName = !empty($from) && $from[0]->getName() ? $from[0]->getName() : config('mail.from.name');

        $to = array_map(fn($address) => $address->getAddress(), $email->getTo());
        $cc = array_map(fn($address) => $address->getAddress(), $email->getCc());
        $bcc = array_map(fn($address) => $address->getAddress(), $email->getBcc());

        $payload = [
            'from' => $fromName ? "{$fromName} <{$fromAddress}>" : $fromAddress,
            'to' => $to,
            'subject' => $email->getSubject(),
        ];

        if ($html = $email->getHtmlBody()) {
            $payload['html'] = $html;
        }

        if ($text = $email->getTextBody()) {
            $payload['text'] = $text;
        }

        if (!empty($cc)) {
            $payload['cc'] = $cc;
        }

        if (!empty($bcc)) {
            $payload['bcc'] = $bcc;
        }

        try {
            $response = $this->client->post('https://api.resend.com/emails', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => true,
                'timeout' => 15,
            ]);

            $body = (string) $response->getBody();
            Log::info("Resend email successfully dispatched to [" . implode(', ', $to) . "]: " . $body);
        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            Log::error("Resend API delivery failure [HTTP {$statusCode}]: {$responseBody}");
            throw new TransportException("Resend API delivery failure [HTTP {$statusCode}]: {$responseBody}", $e->getCode(), $e);
        }
    }

    public function __toString(): string
    {
        return 'resend';
    }
}
