<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Value;


use Hawk\HawkiCrypto\Value\HybridCryptoValue;

/**
 * A wrapper for the encrypted client configuration.
 * This is the format in which the client configuration is
 * transmitted from the backend to the client application.
 */
readonly class EncryptedClientConfig implements \JsonSerializable
{
    public function __construct(
        public HybridCryptoValue $clientConfig
    )
    {
    }
    
    public function jsonSerialize(): array
    {
        return [
            'hawkiClientConfig' => $this->clientConfig->jsonSerialize(),
        ];
    }
}
