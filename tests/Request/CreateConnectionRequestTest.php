<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Tests\Request;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hawk\HawkiClientBackend\Exception\FailedToCreateConnectionRequestException;
use Hawk\HawkiClientBackend\Request\CreateConnectionRequest;
use Hawk\HawkiClientBackend\Value\RequestConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateConnectionRequest::class)]
#[CoversClass(FailedToCreateConnectionRequestException::class)]
class CreateConnectionRequestTest extends TestCase
{
    public function testItCanRequestValidData(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Request $request) {
                if ($request->getMethod() !== 'POST') {
                    return false;
                }
                if ($request->getUri()->getPath() !== 'api/apps/connection/123') {
                    return false;
                }
                return true;
            }))
            ->willReturn(new Response(200, [], json_encode([
                'foo' => 'bar',
            ])));
        
        $result = (new CreateConnectionRequest('123'))->execute($client);
        $this->assertNotNull($result);
        $this->assertInstanceOf(RequestConnection::class, $result);
    }
    
    public function testItFailsIfClientExceptionIsThrown(): void
    {
        $this->expectException(FailedToCreateConnectionRequestException::class);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('Some error'));
        
        (new CreateConnectionRequest('123'))->execute($client);
    }
    
}
