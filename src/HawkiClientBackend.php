<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend;

use GuzzleHttp\ClientInterface;
use Hawk\HawkiClientBackend\Http\ClientConfigurator;
use Hawk\HawkiClientBackend\Request\CreateConnectionRequest;
use Hawk\HawkiClientBackend\Request\FetchConnectionRequest;
use Hawk\HawkiClientBackend\Value\ClientConfig;
use Hawk\HawkiClientBackend\Value\EncryptedClientConfig;
use Hawk\HawkiCrypto\AsymmetricCrypto;
use Hawk\HawkiCrypto\HybridCrypto;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Hawk\HawkiCrypto\Value\AsymmetricPrivateKey;
use SensitiveParameter;

/**
 * HawkiClientBackend provides secure communication with your Hawki instance.
 *
 * Configuration Details:
 *
 * hawkiUrl: The URL of your Hawki instance (e.g. https://hawki.example.com).
 * This URL will NEVER be propagated to the frontend user, so if Hawki is available
 * through a private proxy/hostname only, this is not a problem. For example, when
 * running in a docker compose / kubernetes setup, you can use the internal
 * service name here, e.g. `http://hawki-nginx`.
 *
 * apiToken: The API token that got created when the app was created in Hawki.
 * This token will NEVER be propagated to the frontend user.
 *
 * privateKey: The private key of this application, that belongs to the public key
 * stored in your Hawki instance. This key will NEVER be propagated to the frontend
 * user and is used to ensure secure communication between this backend and your
 * Hawki instance.
 *
 * httpClient: An optional pre-configured HTTP client. If not provided, a default
 * client will be created. This can be useful if your Hawki instance uses a self-signed
 * SSL certificate. Example:
 *
 * ```php
 * use GuzzleHttp\Client;
 *
 * $httpClient = new Client([
 *   'allow_redirects' => ['strict' => true],
 *   'verify' => false, // Disable SSL verification for local development
 * ]);
 *
 * $hawkiApp = new HawkiClientBackend(
 *   hawkiUrl: 'https://hawki.example.com',
 *   apiToken: 'your-token',
 *   privateKey: 'your-private-key',
 *   httpClient: $httpClient
 * );
 */
readonly class HawkiClientBackend
{
    private AsymmetricPrivateKey $privateKey;
    private ClientInterface $client;
    private HybridCrypto $hybridCrypto;
    private AsymmetricCrypto $asymmetricCrypto;
    
    /**
     * @param string $hawkiUrl The URL of your Hawki instance
     * @param string $apiToken The API token from your Hawki app
     * @param string $privateKey The private key for secure communication
     * @param ClientInterface|null $httpClient Optional pre-configured HTTP client
     * @param HybridCrypto|null $hybridCrypto Optional crypto implementation (testing only)
     * @param AsymmetricCrypto|null $asymmetricCrypto Optional crypto implementation (testing only)
     * @param ClientConfigurator|null $clientConfigurator Optional client configurator (testing only)
     */
    public function __construct(
        string                  $hawkiUrl,
        #[SensitiveParameter]
        string                  $apiToken,
        #[SensitiveParameter]
        string                  $privateKey,
        ClientInterface|null    $httpClient = null,
        HybridCrypto|null       $hybridCrypto = null,
        AsymmetricCrypto|null   $asymmetricCrypto = null,
        ClientConfigurator|null $clientConfigurator = null
    )
    {
        $clientConfigurator = $clientConfigurator ?? new ClientConfigurator();
        $this->client = $clientConfigurator->configure($httpClient, $apiToken, $hawkiUrl);
        $this->asymmetricCrypto = $asymmetricCrypto ?? new AsymmetricCrypto();
        $this->hybridCrypto = $hybridCrypto ?? new HybridCrypto(new SymmetricCrypto(), $this->asymmetricCrypto);
        $this->privateKey = AsymmetricPrivateKey::fromString($privateKey);
    }
    
    /**
     * Asks your configured Hawki instance for the connection details for the given user.
     * If the user is not yet linked to a Hawki user, a new connection request will be created.
     * The returned client config is encrypted with the given public key, so it can be safely
     * transmitted to the frontend.
     *
     * When using our provided Javascript client, the `clientConfigUrl` expects the JSON encoded
     * output of this method.
     *
     * As a general example on how to use this method, see the following code snippet:
     * Create a POST route in your backend application that is authenticated (so you have a local user ID)
     * and calls this method. Return the JSON encoded result to the frontend:
     *
     * ```php
     * use Hawk\HawkiClientBackend\HawkiClientBackend;
     *
     * $hawkiClientBackend = new HawkiClientBackend(
     *  hawkiUrl: 'https://hawki.example.com',
     *  apiToken: 'your-token',
     *  privateKey: 'your-private-key'
     * );
     *
     * // In your route handler:
     * $localUserId = getAuthenticatedLocalUserId(); // Get the local user ID
     *
     * // Get the public key from the frontend, (when using `clientConfigUrl` in our JS client, this will
     * always send as POST parameter `public_key`)
     * $publicKey = $_POST['public_key'];
     *
     * // The method will fetch or create the connection and return the encrypted client config
     * $clientConfig = $hawkiClientBackend->getClientConfig($localUserId, $publicKey);
     *
     * header('Content-Type: application/json');
     *
     * // Simply return the JSON encoded result and our JS client will be able to use it directly.
     * echo json_encode($clientConfig);
     * ```
     *
     * @param string|\Stringable|int $localUserId The local user ID - really, the user ID of YOUR application,
     *                                            not the Hawki user ID! This can be any string or integer that uniquely
     *                                            identifies the user in your application. Hawki will manage the link
     *                                            between this ID and the Hawki user ID internally.
     * @param string $publicKey
     * @return EncryptedClientConfig
     * @throws \JsonException
     */
    public function getClientConfig(
        string|\Stringable|int $localUserId,
        string                 $publicKey
    ): EncryptedClientConfig
    {
        $payload = (new FetchConnectionRequest($localUserId))->execute($this->client);
        if ($payload) {
            $payload = $payload->decrypt($this->hybridCrypto, $this->privateKey);
        } else {
            $payload = (new CreateConnectionRequest($localUserId))->execute($this->client);
        }
        
        return new EncryptedClientConfig(
            $this->hybridCrypto->encrypt(
                json_encode(new ClientConfig($payload), JSON_THROW_ON_ERROR),
                $this->asymmetricCrypto->loadPublicKeyFromWeb($publicKey)
            )
        );
    }
}
