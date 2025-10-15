<?php
declare(strict_types=1);

namespace Hawk\HawkiClientBackend\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Utils;
use Hawk\HawkiClientBackend\HawkiClientBackend;
use Hawk\HawkiClientBackend\Http\ClientConfigurator;
use Hawk\HawkiClientBackend\Value\EncryptedClientConfig;
use Hawk\HawkiCrypto\AsymmetricCrypto;
use Hawk\HawkiCrypto\HybridCrypto;
use Hawk\HawkiCrypto\Value\AsymmetricKeypair;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HawkiClientBackend::class)]
class HawkiClientBackendTest extends TestCase
{
    private static AsymmetricKeypair $appKeypair;
    private static AsymmetricKeypair $userKeypair;
    private static AsymmetricKeypair $clientKeypair;
    private ClientInterface $httpClient;
    private ClientConfigurator $clientConfigurator;
    private HawkiClientBackend $backend;
    
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $asymCrypto = new AsymmetricCrypto();
        self::$appKeypair = $asymCrypto->generateKeypair();
        self::$userKeypair = $asymCrypto->generateKeypair();
        self::$clientKeypair = $asymCrypto->generateKeypair();
    }
    
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->clientConfigurator = $this->createMock(ClientConfigurator::class);
        $this->clientConfigurator->method('configure')->willReturn($this->httpClient);
        
        $this->backend = new HawkiClientBackend(
            'https://example.com',
            'app-api-token',
            (string)self::$appKeypair->privateKey,
            clientConfigurator: $this->clientConfigurator
        );
    }
    
    public function testItConstructs(): void
    {
        $this->assertInstanceOf(HawkiClientBackend::class, $this->backend);
        
        $this->assertInstanceOf(HawkiClientBackend::class, new HawkiClientBackend(
            'https://example.com',
            'app-api-token',
            (string)self::$appKeypair->privateKey
        ));
    }
    
    public function testItCanGetClientConfigIfConnectionExists(): void
    {
        $encryptedUserPrivateKey = (new HybridCrypto())->encrypt(
            (string)self::$userKeypair->privateKey,
            self::$appKeypair->publicKey
        );
        
        $encryptedPasskey = (new HybridCrypto())->encrypt(
            'user-passkey',
            self::$userKeypair->publicKey
        );
        
        $encryptedUserApiToken = (new HybridCrypto())->encrypt(
            'user-api-token',
            self::$appKeypair->publicKey
        );
        
        $localUserId = 'user123';
        $connectionData = [
            'some' => 'data',
            'secrets' => [
                'privateKey' => $encryptedUserPrivateKey,
                'passkey' => $encryptedPasskey,
                'apiToken' => $encryptedUserApiToken
            ]
        ];
        
        $clientPublicKeyString = self::$clientKeypair->publicKey->web;
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn(Utils::streamFor(json_encode($connectionData)));
        
        $this->httpClient->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($request) use ($localUserId) {
                return $request->getMethod() === 'GET' && str_contains((string)$request->getUri(), 'api/apps/connection/' . $localUserId);
            }))
            ->willReturn($response);
        
        $result = $this->backend->getClientConfig($localUserId, $clientPublicKeyString);
        
        $this->assertInstanceOf(EncryptedClientConfig::class, $result);
        
        $decryptedJson = (new HybridCrypto())->decrypt($result->clientConfig, self::$clientKeypair->privateKey);
        
        $this->assertJsonStringEqualsJsonString(
            '{"type":"connected","payload":{"some":"data","secrets":{"passkey":"user-passkey","apiToken":"user-api-token"}}}',
            $decryptedJson
        );
    }
    
    public function testItCanGetClientConfigIfThereIsNoConnection(): void
    {
        $localUserId = 'user123';
        $connectionData = [
            'some' => 'data'
        ];
        
        $clientPublicKeyString = self::$clientKeypair->publicKey->web;
        
        $invocation = $this->exactly(2);
        $this->httpClient->expects($invocation)
            ->method('send')
            ->with($this->callback(function ($request) use ($invocation, $localUserId) {
                if ($invocation->numberOfInvocations() === 1) {
                    // First call simulates no existing connection (404)
                    return $request->getMethod() === 'GET' && str_contains((string)$request->getUri(), 'api/apps/connection/' . $localUserId);
                }
                
                if ($invocation->numberOfInvocations() === 2) {
                    // Second call simulates creating a new connection (201)
                    return $request->getMethod() === 'POST' && str_contains((string)$request->getUri(), 'api/apps/connection/' . $localUserId);
                }
                
                return false;
            }))
            ->willReturnCallback(function () use ($invocation, $connectionData) {
                if ($invocation->numberOfInvocations() === 1) {
                    $response = $this->createMock(ResponseInterface::class);
                    $response->method('getBody')->willReturn(Utils::streamFor('Not Found'));
                    $response->method('getStatusCode')->willReturn(404);
                    $ex = new ClientException(
                        '',
                        $this->createMock(RequestInterface::class),
                        $response
                    );
                    throw $ex;
                }
                if ($invocation->numberOfInvocations() === 2) {
                    $response = $this->createMock(ResponseInterface::class);
                    $response->method('getBody')->willReturn(Utils::streamFor(json_encode($connectionData)));
                    return $response;
                }
                return null;
            });
        
        $result = $this->backend->getClientConfig($localUserId, $clientPublicKeyString);
        
        $this->assertInstanceOf(EncryptedClientConfig::class, $result);
        
        $decryptedJson = (new HybridCrypto())->decrypt($result->clientConfig, self::$clientKeypair->privateKey);
        
        $this->assertJsonStringEqualsJsonString(
            '{"type":"connect_request","payload":{"some":"data"}}',
            $decryptedJson
        );
    }
    
}
