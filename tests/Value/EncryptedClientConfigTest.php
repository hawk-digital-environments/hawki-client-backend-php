<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Tests\Value;


use Hawk\HawkiClientBackend\Value\EncryptedClientConfig;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptedClientConfig::class)]
class EncryptedClientConfigTest extends TestCase
{
    private static HybridCryptoValue $dummyValue;
    
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$dummyValue = new HybridCryptoValue(
            passphrase: 'string',
            value: new SymmetricCryptoValue(
                iv: random_bytes(16),
                tag: random_bytes(16),
                ciphertext: random_bytes(32)
            )
        );
    }
    
    public function testItConstructs(): void
    {
        $sut = new EncryptedClientConfig(self::$dummyValue);
        $this->assertInstanceOf(EncryptedClientConfig::class, $sut);
        $this->assertSame(self::$dummyValue, $sut->clientConfig);
    }
    
    public function testItJsonSerializes(): void
    {
        $sut = new EncryptedClientConfig(self::$dummyValue);
        $expected = [
            'hawkiClientConfig' => self::$dummyValue->jsonSerialize(),
        ];
        $this->assertSame($expected, $sut->jsonSerialize());
    }
}
