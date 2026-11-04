<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Services\Messaging\WebPushSender;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * Runs the real library with real keys, stopping only at the network. Other tests
 * fake the whole sender, so this is what proves the integration itself works.
 */
class WebPushSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (@openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) === false) {
            $this->markTestSkipped('This PHP has no working OpenSSL configuration for EC keys.');
        }

        config(['messages.push' => [
            'subject' => 'mailto:ops@example.test',
            'public_key' => 'BJM8iYC91p22iPt7VxhSPtU8t-VfQ0spFgfB7r3ie_uFNQYlNKLDj38OLAnK5St94mPgXAj6HEcTAtY2TEq9zok',
            'private_key' => 'nnA2iF_XCUMZHVIiQfqjfG-eBH-hS8oFN6lsIpWav_k',
        ]]);
    }

    private function subscription(): PushSubscription
    {
        return new PushSubscription([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'public_key' => 'BFD-7c5kV0_RU90Q7hlEiky20CjzmJgHRPCUZttXGwcZp76ieQ8v29ef6uR2P5Fa4b2FH4RZbL1f5ZqkEeUD72A',
            'auth_token' => 'OCXDfruZQnF-RCosmzJLVw',
        ]);
    }

    private function client(int $status): ClientInterface
    {
        return new class($status) implements ClientInterface
        {
            public ?RequestInterface $request = null;

            public function __construct(private readonly int $status) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response($this->status);
            }
        };
    }

    public function test_it_sends_an_encrypted_signed_request_to_the_endpoint(): void
    {
        $client = $this->client(201);

        $delivered = (new WebPushSender($client))->send($this->subscription(), ['title' => 'Secret title', 'body' => 'Secret body', 'url' => '/patient']);

        $this->assertTrue($delivered);
        $this->assertSame('POST', $client->request->getMethod());
        $this->assertSame('fcm.googleapis.com', $client->request->getUri()->getHost());
        $this->assertStringStartsWith('vapid', $client->request->getHeaderLine('Authorization'));
        $this->assertSame('aes128gcm', $client->request->getHeaderLine('Content-Encoding'));
        $this->assertStringNotContainsString('Secret', (string) $client->request->getBody());
    }

    public function test_a_gone_subscription_is_reported_as_expired(): void
    {
        $this->assertFalse((new WebPushSender($this->client(410)))->send($this->subscription(), ['title' => 't', 'body' => 'b', 'url' => '/']));
    }
}
