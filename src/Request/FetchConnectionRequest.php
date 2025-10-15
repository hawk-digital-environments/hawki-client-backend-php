<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Request;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use Hawk\HawkiClientBackend\Exception\FailedToFetchClientBackendUserException;
use Hawk\HawkiClientBackend\Value\Connection;
use Psr\Http\Client\ClientExceptionInterface;

class FetchConnectionRequest
{
    public function __construct(
        protected string|\Stringable|int $localUserId,
    )
    {
    }
    
    public function execute(ClientInterface $client): Connection|null
    {
        try {
            $response = $client->send(
                new Request(
                    'GET',
                    'api/apps/connection/' . $this->localUserId
                )
            );
            
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            
            return new Connection($data);
        } catch (ClientExceptionInterface $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            
            throw new FailedToFetchClientBackendUserException($e);
        }
    }
}
