<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Value;


use Hawk\HawkiClientBackend\Exception\ConnectionNotDecryptedException;
use Hawk\HawkiClientBackend\Exception\FailedToDecryptSecretsException;
use Hawk\HawkiCrypto\HybridCrypto;
use Hawk\HawkiCrypto\Value\AsymmetricPrivateKey;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;

/**
 * Represents the client connection details, when the local user has been connected to a Hawki user.
 * The connection details include the user secrets, which are encrypted and need to be decrypted
 * using the app's private key and the user's private key.
 */
class Connection implements \JsonSerializable
{
    private bool $isDecrypted = false;
    
    public function __construct(
        private array $data,
    )
    {
    }
    
    /**
     * The user secrets are additionally encrypted with the app's public key.
     * This method decrypts the secrets using the provided hybrid crypto
     * instance and the app's private key.
     *
     * @param HybridCrypto $hybridCrypto
     * @param AsymmetricPrivateKey $appPrivateKey
     * @return $this
     */
    public function decrypt(HybridCrypto $hybridCrypto, AsymmetricPrivateKey $appPrivateKey): self
    {
        if ($this->isDecrypted) {
            return $this;
        }
        
        $this->decryptSecrets($hybridCrypto, $appPrivateKey);
        
        $this->isDecrypted = true;
        
        return $this;
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        if (!$this->isDecrypted) {
            throw new ConnectionNotDecryptedException();
        }
        
        return $this->data;
    }
    
    private function decryptSecrets(HybridCrypto $hybridCrypto, AsymmetricPrivateKey $appPrivateKey): void
    {
        if (!is_array($this->data['secrets'] ?? null)) {
            throw new FailedToDecryptSecretsException('secrets', 'the secrets field is missing or not an array');
        }
        
        $assertFieldIsNotEmptyString = function (string $fieldName) {
            $value = $this->data['secrets'][$fieldName] ?? null;
            if (!is_string($value) || $value === '') {
                throw new FailedToDecryptSecretsException($fieldName, 'the field is missing or an empty string');
            }
        };
        
        $assertFieldIsNotEmptyString('privateKey');
        $userPrivateKey = AsymmetricPrivateKey::fromString($hybridCrypto->decrypt(
            HybridCryptoValue::fromString($this->data['secrets']['privateKey']),
            $appPrivateKey
        ));
        // The private key is only used to decrypt the passkey, so we can remove it from the data after decryption
        unset($this->data['secrets']['privateKey']);
        
        $assertFieldIsNotEmptyString('passkey');
        $decryptedPasskey = $hybridCrypto->decrypt(
            HybridCryptoValue::fromString($this->data['secrets']['passkey']),
            $userPrivateKey
        );
        $this->data['secrets']['passkey'] = $decryptedPasskey;
        
        $assertFieldIsNotEmptyString('apiToken');
        $decryptedApiToken = $hybridCrypto->decrypt(
            HybridCryptoValue::fromString($this->data['secrets']['apiToken']),
            $appPrivateKey
        );
        $this->data['secrets']['apiToken'] = $decryptedApiToken;
    }
}
