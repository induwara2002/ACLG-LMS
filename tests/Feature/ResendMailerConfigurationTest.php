<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Resend\Laravel\Transport\ResendTransportFactory;
use Tests\TestCase;

class ResendMailerConfigurationTest extends TestCase
{
    public function test_resend_mailer_resolves_through_laravel_without_sending(): void
    {
        config([
            'mail.default' => 'resend',
            'mail.mailers.resend' => ['transport' => 'resend'],
            'resend.api_key' => 'test-api-key',
        ]);
        Mail::purge('resend');

        $transport = Mail::mailer('resend')->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransportFactory::class, $transport);
    }
}
