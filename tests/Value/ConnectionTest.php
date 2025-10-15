<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Tests\Value;


use Hawk\HawkiClientBackend\Exception\ConnectionNotDecryptedException;
use Hawk\HawkiClientBackend\Exception\FailedToDecryptSecretsException;
use Hawk\HawkiClientBackend\Value\Connection;
use Hawk\HawkiCrypto\AsymmetricCrypto;
use Hawk\HawkiCrypto\HybridCrypto;
use Hawk\HawkiCrypto\Value\AsymmetricKeypair;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Connection::class)]
#[CoversClass(FailedToDecryptSecretsException::class)]
#[CoversClass(ConnectionNotDecryptedException::class)]
class ConnectionTest extends TestCase
{
    private static AsymmetricKeypair $appKeypair;
    private static AsymmetricKeypair $userKeypair;
    private static array $connectionData = [
        'foo' => 'bar',
        'secrets' => [
            'passkey' => 'user-passkey',
            'apiToken' => 'user-api-token',
        ],
    ];
    private static array $encryptedConnectionData;
    
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $asymCrypto = new AsymmetricCrypto();
        self::$appKeypair = $asymCrypto->generateKeypair();
        self::$userKeypair = $asymCrypto->generateKeypair();
        self::$encryptedConnectionData = array_merge(
            self::$connectionData,
            [
                'secrets' => [
                    'privateKey' => (string)(new HybridCrypto())->encrypt(
                        (string)self::$userKeypair->privateKey,
                        self::$appKeypair->publicKey
                    ),
                    'passkey' => (string)(new HybridCrypto())->encrypt(
                        self::$connectionData['secrets']['passkey'],
                        self::$userKeypair->publicKey
                    ),
                    'apiToken' => (string)(new HybridCrypto())->encrypt(
                        self::$connectionData['secrets']['apiToken'],
                        self::$appKeypair->publicKey
                    ),
                ]
            ]
        );
    }
    
    public function testItConstructs(): void
    {
        $sut = new Connection(self::$connectionData);
        $this->assertInstanceOf(Connection::class, $sut);
    }
    
    public function testItDecryptsSecrets(): void
    {
        $sut = new Connection(self::$encryptedConnectionData);
        $result = $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
        
        $this->assertSame($sut, $result);
        $this->assertTrue($this->getPrivateProperty($sut, 'isDecrypted'));
        $expectedData = self::$connectionData;
        $this->assertEquals($expectedData, $this->getPrivateProperty($sut, 'data'));
    }
    
    public function testItDoesNotDecryptTwice(): void
    {
        $sut = new Connection(self::$encryptedConnectionData);
        $hybridCrypto = $this->createMock(HybridCrypto::class);
        $hybridCrypto->expects($this->exactly(3))
            ->method('decrypt')
            ->willReturnCallback(fn($data, $key) => (new HybridCrypto())->decrypt($data, $key));
        
        $sut->decrypt($hybridCrypto, self::$appKeypair->privateKey);
        $result = $sut->decrypt($hybridCrypto, self::$appKeypair->privateKey);
        
        $this->assertSame($sut, $result);
        // decrypt called only 3 times total
    }
    
    public function testItJsonSerializesAfterDecryption(): void
    {
        $sut = new Connection(self::$encryptedConnectionData);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
        
        $this->assertEquals(self::$connectionData, $sut->jsonSerialize());
    }
    
    public function testItThrowsWhenJsonSerializingWithoutDecryption(): void
    {
        $sut = new Connection(self::$connectionData);
        
        $this->expectException(ConnectionNotDecryptedException::class);
        
        $sut->jsonSerialize();
    }
    
    public function testItThrowsWhenDecryptingWithMissingSecrets(): void
    {
        $data = ['foo' => 'bar'];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    public function testItThrowsWhenDecryptingWithSecretsNotArray(): void
    {
        $data = ['secrets' => 'not array'];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    public function testItThrowsWhenDecryptingWithMissingPrivateKey(): void
    {
        $data = ['secrets' => [
            'passkey' => self::$encryptedConnectionData['secrets']['passkey'],
            'apiToken' => self::$encryptedConnectionData['secrets']['apiToken']
        ]];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    public function testItThrowsWhenDecryptingWithEmptyPrivateKey(): void
    {
        $data = ['secrets' => [
            'privateKey' => '',
            'passkey' => self::$encryptedConnectionData['secrets']['passkey'],
            'apiToken' => self::$encryptedConnectionData['secrets']['apiToken']
        ]];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    public function testItThrowsWhenDecryptingWithMissingPasskey(): void
    {
        $data = ['secrets' => [
            'privateKey' => self::$encryptedConnectionData['secrets']['privateKey'],
            'apiToken' => self::$encryptedConnectionData['secrets']['apiToken']
        ]];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    public function testItThrowsWhenDecryptingWithEmptyPasskey(): void
    {
        $data = ['secrets' => [
            'privateKey' => self::$encryptedConnectionData['secrets']['privateKey'],
            'passkey' => '',
            'apiToken' => self::$encryptedConnectionData['secrets']['apiToken']
        ]];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    public function testItThrowsWhenDecryptingWithMissingApiToken(): void
    {
        $data = ['secrets' => [
            'privateKey' => self::$encryptedConnectionData['secrets']['privateKey'],
            'passkey' => self::$encryptedConnectionData['secrets']['passkey'],
        ]];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    public function testItThrowsWhenDecryptingWithEmptyApiToken(): void
    {
        $data = ['secrets' => [
            'privateKey' => self::$encryptedConnectionData['secrets']['privateKey'],
            'passkey' => self::$encryptedConnectionData['secrets']['passkey'],
            'apiToken' => ''
        ]];
        $sut = new Connection($data);
        
        $this->expectException(FailedToDecryptSecretsException::class);
        
        $sut->decrypt(new HybridCrypto(), self::$appKeypair->privateKey);
    }
    
    private function getPrivateProperty(object $object, string $property): mixed
    {
        $prop = (new \ReflectionClass($object))->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}
