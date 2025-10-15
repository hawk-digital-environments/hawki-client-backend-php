<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Tests\Value;


use Hawk\HawkiClientBackend\Value\ClientConfig;
use Hawk\HawkiClientBackend\Value\ClientConfigType;
use Hawk\HawkiClientBackend\Value\Connection;
use Hawk\HawkiClientBackend\Value\RequestConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientConfig::class)]
#[CoversClass(ClientConfigType::class)]
class ClientConfigTest extends TestCase
{
    public function testItConstructs(): void
    {
        $this->assertInstanceOf(ClientConfig::class, new ClientConfig(
            $this->createStub(Connection::class)
        ));
    }
    
    public function testItDetectsConnectionType(): void
    {
        $sut = new ClientConfig(
            $this->createStub(Connection::class)
        );
        $this->assertSame(ClientConfigType::CONNECTED, $sut->type);
    }
    
    public function testItDetectsConnectionRequestType(): void
    {
        $sut = new ClientConfig(
            $this->createStub(RequestConnection::class)
        );
        $this->assertSame(ClientConfigType::CONNECTION_REQUEST, $sut->type);
    }
    
    public static function provideForTestItCanJsonSerialize(): iterable
    {
        yield 'connected' => [static::createStub(Connection::class)];
        yield 'connection_request' => [static::createStub(RequestConnection::class)];
    }
    
    #[DataProvider('provideForTestItCanJsonSerialize')]
    public function testItCanJsonSerialize(Connection|RequestConnection $payload): void
    {
        $payload->method('jsonSerialize')->willReturn(['foo' => 'bar']);
        $sut = new ClientConfig($payload);
        $this->assertSame([
            'type' => $sut->type->value,
            'payload' => ['foo' => 'bar'],
        ], $sut->jsonSerialize());
    }
}
