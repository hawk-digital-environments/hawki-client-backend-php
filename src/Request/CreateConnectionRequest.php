<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Request;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use Hawk\HawkiClientBackend\Exception\FailedToCreateConnectionRequestException;
use Hawk\HawkiClientBackend\Value\RequestConnection;

class CreateConnectionRequest
{
    public function __construct(
        protected string|\Stringable|int $localUserId
    )
    {
    }
    
    public function execute(ClientInterface $client): RequestConnection
    {
        try {
            $response = $client->send(
                new Request(
                    'POST',
                    'api/apps/connection/' . $this->localUserId,
                )
            );
            
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return new RequestConnection($data);
        } catch (\Throwable $e) {
            throw new FailedToCreateConnectionRequestException($e);
        }
    }
}
