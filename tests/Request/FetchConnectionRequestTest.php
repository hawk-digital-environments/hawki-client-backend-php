<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Tests\Request;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hawk\HawkiClientBackend\Exception\FailedToFetchClientBackendUserException;
use Hawk\HawkiClientBackend\Request\FetchConnectionRequest;
use Hawk\HawkiClientBackend\Value\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FetchConnectionRequest::class)]
#[CoversClass(FailedToFetchClientBackendUserException::class)]
class FetchConnectionRequestTest extends TestCase
{
    
    public function testItCanRequestValidData(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Request $request) {
                if ($request->getMethod() !== 'GET') {
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
        
        $result = (new FetchConnectionRequest('123'))->execute($client);
        $this->assertNotNull($result);
        $this->assertInstanceOf(Connection::class, $result);
    }
    
    public function testItFailsIfClientExceptionThrownThatIsNot404Error(): void
    {
        $this->expectException(FailedToFetchClientBackendUserException::class);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->willThrowException(new ClientException(
                'Error Communicating with Server',
                new Request('GET', 'test'),
                new Response(400)
            ));
        
        (new FetchConnectionRequest('123'))->execute($client);
    }
    
    public function testItReturnsNullIf404ErrorIsThrown(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('send')
            ->willThrowException(new ClientException(
                'Error Communicating with Server',
                new Request('GET', 'test'),
                new Response(404)
            ));
        
        $result = (new FetchConnectionRequest('123'))->execute($client);
        $this->assertNull($result);
    }
    
}
