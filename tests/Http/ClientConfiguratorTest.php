<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Tests\Http;


use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hawk\HawkiClientBackend\Exception\InvalidHawkiUrlException;
use Hawk\HawkiClientBackend\Http\ClientConfigurator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(ClientConfigurator::class)]
#[CoversClass(InvalidHawkiUrlException::class)]
class ClientConfiguratorTest extends TestCase
{
    public function testItConstructs(): void
    {
        $this->assertInstanceOf(
            ClientConfigurator::class,
            new ClientConfigurator()
        );
    }
    
    public function testItCanConfigureAClient(): void
    {
        $request = new Request('GET', '/test-endpoint');
        $client = $this->createMock(Client::class);
        $sut = new ClientConfigurator();
        $apiToken = 'test-api-token';
        $hawkiUrl = 'https://example.com/hawki';
        
        $client->expects($this->once())
            ->method('sendAsync')
            ->with($this->isInstanceOf(RequestInterface::class), $this->callback(function ($options) use ($hawkiUrl) {
                return isset($options['base_uri']) && $options['base_uri'] === rtrim($hawkiUrl, ' /') . '/';
            }))
            ->willReturnCallback(function (Request $request, $options) use ($apiToken, $hawkiUrl) {
                $this->assertEquals(
                    'Bearer ' . $apiToken,
                    $request->getHeaderLine('Authorization')
                );
                $this->assertEquals(
                    'application/json',
                    $request->getHeaderLine('Accept')
                );
                $this->assertEquals(rtrim($hawkiUrl, ' /') . '/', $options['base_uri']);
                return new FulfilledPromise(
                    new Response(200, [], '{"status":"ok"}')
                );
            });
        
        $configuredClient = $sut->configure($client, $apiToken, $hawkiUrl);
        
        $this->assertInstanceOf(Client::class, $configuredClient);
        
        $configuredClient->send($request);
    }
    
    public function testItKeepsExistingAuthorizationHeaderIntact(): void
    {
        $preconfiguredApiToken = 'token123';
        $request = new Request('GET', '/test-endpoint', [
            'Authorization' => 'CustomAuth ' . $preconfiguredApiToken,
        ]);
        $client = $this->createMock(Client::class);
        $sut = new ClientConfigurator();
        $apiToken = 'test-api-token';
        $hawkiUrl = 'https://example.com/hawki';
        
        $client->expects($this->once())
            ->method('sendAsync')
            ->with($this->isInstanceOf(RequestInterface::class), $this->callback(function ($options) use ($hawkiUrl) {
                return isset($options['base_uri']) && $options['base_uri'] === rtrim($hawkiUrl, ' /') . '/';
            }))
            ->willReturnCallback(function (Request $request, $options) use ($preconfiguredApiToken, $hawkiUrl) {
                $this->assertEquals(
                    'CustomAuth ' . $preconfiguredApiToken,
                    $request->getHeaderLine('Authorization')
                );
                $this->assertEquals(
                    'application/json',
                    $request->getHeaderLine('Accept')
                );
                $this->assertEquals(rtrim($hawkiUrl, ' /') . '/', $options['base_uri']);
                return new FulfilledPromise(
                    new Response(200, [], '{"status":"ok"}')
                );
            });
        
        $configuredClient = $sut->configure($client, $apiToken, $hawkiUrl);
        
        $this->assertInstanceOf(Client::class, $configuredClient);
        
        $configuredClient->send($request);
    }
    
    public function testItStripsTrailingSlashesFromHawkiUrl(): void
    {
        $request = new Request('GET', '/test-endpoint');
        $client = $this->createMock(Client::class);
        $sut = new ClientConfigurator();
        $apiToken = 'test-api-token';
        $hawkiUrl = 'https://example.com/hawki///';
        
        $client->expects($this->once())
            ->method('sendAsync')
            ->willReturnCallback(function (Request $request, $options) use ($hawkiUrl) {
                $this->assertEquals(rtrim($hawkiUrl, ' /') . '/', $options['base_uri']);
                return new FulfilledPromise(
                    new Response(200, [], '{"status":"ok"}')
                );
            });
        
        $configuredClient = $sut->configure($client, $apiToken, $hawkiUrl);
        
        $configuredClient->send($request);
    }
    
    public static function provideDataForItFailsToConfigureOnEmptyOrInvalidHawkiUrl(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace string' => ['   '];
        yield 'invalid URL' => ['not-a-valid-url'];
        yield 'missing scheme' => ['example.com/hawki'];
        yield 'invalid scheme' => ['ftp://example.com/hawki'];
        yield 'missing host' => ['http:///hawki'];
    }
    
    #[DataProvider('provideDataForItFailsToConfigureOnEmptyOrInvalidHawkiUrl')]
    public function testItFailsToConfigureOnEmptyOrInvalidHawkiUrl($hawkiUrl): void
    {
        $this->expectException(InvalidHawkiUrlException::class);
        $this->expectExceptionMessage(sprintf(
            'The given HAWKI server URL "%s" is invalid. It must be a valid URL.',
            $hawkiUrl
        ));
        (new ClientConfigurator())->configure(null, 'api-token', $hawkiUrl);
    }
}
