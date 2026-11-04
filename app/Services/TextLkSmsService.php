<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class TextLkSmsService
{
    private const SEND_ENDPOINT = 'https://app.text.lk/api/v3/sms/send';

    public function send(string $recipient, string $message): string
    {
        $token = config('services.textlk.token');
        $senderId = config('services.textlk.sender_id');

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('Text.lk API token is not configured.');
        }
        $token = trim($token);

        if (! is_string($senderId) || trim($senderId) === '') {
            throw new RuntimeException('Text.lk sender ID is not configured.');
        }

        $senderId = trim($senderId);
        if (! preg_match('/^[A-Za-z0-9]{1,11}$/D', $senderId)
            && ! preg_match('/^\+?[1-9]\d{7,14}$/D', $senderId)) {
            throw new InvalidArgumentException('Text.lk sender ID must be an alphanumeric ID of at most 11 characters or an international phone number.');
        }

        $recipient = $this->normalizeSriLankanMobileNumber($recipient);
        if (trim($message) === '') {
            throw new InvalidArgumentException('SMS message cannot be empty.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($token)
                ->timeout(10)
                ->post(self::SEND_ENDPOINT, [
                    'recipient' => $recipient,
                    'sender_id' => $senderId,
                    'type' => 'plain',
                    'message' => $message,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Unable to connect to the Text.lk SMS API.');
        }

        if (! $response->successful() || $response->json('status') !== true) {
            throw new RuntimeException('Text.lk rejected the SMS request (HTTP '.$response->status().').');
        }

        $smsId = $response->json('data.sms_id');
        if (! is_string($smsId) || $smsId === '') {
            throw new RuntimeException('Text.lk accepted the request but returned no SMS ID.');
        }

        return $smsId;
    }

    private function normalizeSriLankanMobileNumber(string $recipient): string
    {
        $number = preg_replace('/[\s().-]/', '', trim($recipient));

        if (! is_string($number)) {
            throw new InvalidArgumentException('Recipient must be a valid Sri Lankan mobile number.');
        }

        if (preg_match('/^07\d{8}$/D', $number)) {
            return '94'.substr($number, 1);
        }

        if (preg_match('/^\+?947\d{8}$/D', $number)) {
            return ltrim($number, '+');
        }

        throw new InvalidArgumentException('Recipient must be a valid Sri Lankan mobile number.');
    }
}
