<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Http;


use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Hawk\HawkiClientBackend\Exception\InvalidHawkiUrlException;
use SensitiveParameter;

class ClientConfigurator
{
    /**
     * Wraps the given Guzzle client (or a new one if null) to add the Authorization header with the given API token
     * and set the base URL to the given Hawki URL.
     *
     * @param ClientInterface|null $concreteClient The Guzzle client to wrap, or null to create a new one
     * @param string $apiToken The API token to use for authentication
     * @param string $hawkiUrl The base URL of the Hawki server
     * @return ClientInterface
     */
    public function configure(
        ClientInterface|null $concreteClient,
        #[SensitiveParameter]
        string               $apiToken,
        string               $hawkiUrl,
    ): ClientInterface
    {
        $concreteClient ??= new Client();
        
        /** @noinspection BypassedUrlValidationInspection */
        if (empty($hawkiUrl) || !filter_var($hawkiUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $hawkiUrl)) {
            throw new InvalidHawkiUrlException($hawkiUrl);
        }
        $hawkiUrl = rtrim($hawkiUrl, ' /');
        
        $stack = HandlerStack::create(static function (Request $request) use ($concreteClient, $hawkiUrl) {
            return $concreteClient->sendAsync(
                $request,
                [
                    'base_uri' => $hawkiUrl . '/',
                ]
            );
        });
        
        // Add a middleware to the stack that adds the Authorization header if it is not already present
        $stack->push(static function (callable $next) use ($apiToken) {
            return static function (Request $request, array $options) use ($next, $apiToken) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $request = $request->withHeader('Accept', 'application/json');
                
                if ($request->hasHeader('Authorization')) {
                    return $next($request, $options);
                }
                
                return $next(
                    $request->withHeader('Authorization', 'Bearer ' . $apiToken),
                    $options
                );
            };
        });
        
        return new Client([
            'handler' => $stack,
        ]);
    }
}
