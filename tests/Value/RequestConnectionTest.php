<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Tests\Value;


use Hawk\HawkiClientBackend\Value\RequestConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestConnection::class)]
class RequestConnectionTest extends TestCase
{
    public function testItConstructs(): void
    {
        $data = ['key' => 'value'];
        $sut = new RequestConnection($data);
        $this->assertInstanceOf(RequestConnection::class, $sut);
    }
    
    public function testItJsonSerializesData(): void
    {
        $data = ['foo' => 'bar', 'number' => 123];
        $sut = new RequestConnection($data);
        $this->assertSame($data, $sut->jsonSerialize());
    }
    
    public function testItJsonSerializesEmptyArray(): void
    {
        $data = [];
        $sut = new RequestConnection($data);
        $this->assertSame($data, $sut->jsonSerialize());
    }
}
