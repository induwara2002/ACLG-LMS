<?php

namespace Tests\Feature;

use App\Services\TextLkSmsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TextLkSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.textlk.token' => 'test-access-token',
            'services.textlk.sender_id' => 'LibrarySMS',
        ]);
    }

    public function test_it_sends_a_single_sms_through_the_v3_oauth_endpoint(): void
    {
        Http::fake([
            'https://app.text.lk/api/v3/sms/send' => Http::response([
                'status' => true,
                'message' => 'SMS queued successfully',
                'data' => [
                    'sms_id' => 'sms-test-123',
                    'recipient' => '94771234567',
                ],
            ]),
        ]);

        $smsId = app(TextLkSmsService::class)->send('077 123 4567', 'Test message');

        $this->assertSame('sms-test-123', $smsId);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://app.text.lk/api/v3/sms/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request->hasHeader('Accept', 'application/json')
                && $request->data() === [
                    'recipient' => '94771234567',
                    'sender_id' => 'LibrarySMS',
                    'type' => 'plain',
                    'message' => 'Test message',
                ];
        });
    }

    public function test_it_accepts_an_international_sri_lankan_mobile_number(): void
    {
        Http::fake([
            'https://app.text.lk/api/v3/sms/send' => Http::response([
                'status' => true,
                'data' => ['sms_id' => 'sms-test-456'],
            ]),
        ]);

        $this->assertSame(
            'sms-test-456',
            app(TextLkSmsService::class)->send('+94771234567', 'Test message'),
        );

        Http::assertSent(fn (Request $request): bool => $request->data()['recipient'] === '94771234567');
    }

    public function test_it_rejects_invalid_recipients_without_making_a_request(): void
    {
        Http::fake();

        try {
            app(TextLkSmsService::class)->send('0111234567', 'Test message');
            $this->fail('A landline number must not be accepted as an SMS mobile recipient.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Recipient must be a valid Sri Lankan mobile number.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_rejects_an_empty_message_without_making_a_request(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS message cannot be empty.');

        try {
            app(TextLkSmsService::class)->send('0771234567', '  ');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_an_invalid_sender_id_without_making_a_request(): void
    {
        config(['services.textlk.sender_id' => 'LibrarySenderID']);
        Http::fake();

        try {
            app(TextLkSmsService::class)->send('0771234567', 'Test message');
            $this->fail('An alphanumeric sender ID longer than 11 characters must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('at most 11 characters', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_reports_provider_rejections_without_exposing_the_response_body(): void
    {
        Http::fake([
            'https://app.text.lk/api/v3/sms/send' => Http::response([
                'status' => false,
                'message' => 'Invalid recipient number',
            ], 422),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Text.lk rejected the SMS request (HTTP 422).');

        app(TextLkSmsService::class)->send('0771234567', 'Test message');
    }

    public function test_it_reports_connection_failures_without_exposing_request_details(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection failed.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to connect to the Text.lk SMS API.');

        app(TextLkSmsService::class)->send('0771234567', 'Test message');
    }

    public function test_it_requires_an_api_token_without_making_a_request(): void
    {
        config(['services.textlk.token' => null]);
        Http::fake();

        try {
            app(TextLkSmsService::class)->send('0771234567', 'Test message');
            $this->fail('A missing API token must prevent the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Text.lk API token is not configured.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
